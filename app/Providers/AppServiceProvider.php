<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\LlmProvider;
use App\Domain\Ai\Providers\OllamaProvider;
use App\Domain\Ai\Providers\OpenAiCompatibleProvider;
use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureAiEnabled;
use App\Importers\Contracts\LeadSource;
use App\Importers\Csv\CsvLeadSource;
use App\Importers\Email\ImapLeadSource;
use App\Importers\EmailMock\EmailMockLeadSource;
use App\Importers\Google\GoogleAdsSource;
use App\Importers\Google\GoogleCreativeSource;
use App\Importers\GoogleMock\GoogleMockAdMetricsSource;
use App\Importers\GoogleMock\GoogleMockCreativeSource;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Importers\Manual\ManualLeadSource;
use App\Importers\Meta\MetaAdsSource;
use App\Importers\Meta\MetaCreativeSource;
use App\Importers\Meta\MetaLeadsSource;
use App\Importers\MetaMock\MetaMockAdMetricsSource;
use App\Importers\MetaMock\MetaMockCreativeSource;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\MailSetting;
use App\Models\WebhookEndpoint;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Importer adapters known to the app. Adding a new source means: drop a
     * class in app/Importers/<Name>/, register it here, and add UI to wire it.
     *
     * @var array<string, class-string<LeadSource>>
     */
    public const IMPORTERS = [
        'csv' => CsvLeadSource::class,
        'email_mock' => EmailMockLeadSource::class,
        'email_imap' => ImapLeadSource::class,
        'manual' => ManualLeadSource::class,
        'google_sheets' => GoogleSheetsLeadSource::class,
        'meta_leads' => MetaLeadsSource::class,
        'openflow' => OpenflowLeadSource::class,
    ];

    /**
     * Ad metrics source adapters. Each fetches aggregate spend/click data from
     * an ad platform for a given date and returns AdMetricsSnapshot DTOs.
     *
     * @var array<string, class-string<AdMetricsSource>>
     */
    public const AD_METRICS_SOURCES = [
        'meta_mock' => MetaMockAdMetricsSource::class,
        'google_mock' => GoogleMockAdMetricsSource::class,
        'meta' => MetaAdsSource::class,
        'google' => GoogleAdsSource::class,
    ];

    /**
     * Creative-level metrics source adapters (top ads / keywords / segments),
     * keyed by the same source keys as AD_METRICS_SOURCES so the operator's
     * platform toggles govern both levels at once.
     *
     * @var array<string, class-string<CreativeMetricsSource>>
     */
    public const CREATIVE_METRICS_SOURCES = [
        'meta_mock' => MetaMockCreativeSource::class,
        'google_mock' => GoogleMockCreativeSource::class,
        'meta' => MetaCreativeSource::class,
        'google' => GoogleCreativeSource::class,
    ];

    /**
     * LLM provider adapters. Resolution from `ai_settings.provider` key.
     * Implementations must implement {@see LlmProvider}.
     *
     * @var array<string, class-string<LlmProvider>>
     */
    public const LLM_PROVIDERS = [
        'openai_compatible' => OpenAiCompatibleProvider::class,
        'ollama' => OllamaProvider::class,
    ];

    public function register(): void
    {
        foreach (self::IMPORTERS as $class) {
            $this->app->singleton($class);
        }

        foreach (self::AD_METRICS_SOURCES as $class) {
            $this->app->singleton($class);
        }

        foreach (self::CREATIVE_METRICS_SOURCES as $class) {
            $this->app->singleton($class);
        }

        foreach (self::LLM_PROVIDERS as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Register the AI kill-switch middleware as a route alias so it can be
        // attached to specific Livewire routes.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ai.enabled', EnsureAiEnabled::class);

        // Apply the operator's UI mail (SMTP) settings over the .env mail config
        // so reporting emails, password resets, etc. honour Settings → Email.
        // Once here for the web request, and again before every queued job so a
        // long-lived queue worker picks up changes without a restart — the
        // reporting-email job runs on the queue, so this is what makes it send.
        // resolveSafe() no-ops if the table or row is absent (fresh install).
        MailSetting::applyForDefaultTenant();
        Queue::before(static function (): void {
            MailSetting::applyForDefaultTenant();
        });

        $this->bootRateLimiters();
        $this->pinPasswordResetLinksToAppUrl();
        $this->warnAboutInsecureProductionConfig();
    }

    /**
     * Build password-reset links from APP_URL, never from the request.
     *
     * The reset mail is sent while handling POST /forgot-password, and by
     * default Laravel builds the link from that request's host. With
     * TRUSTED_PROXIES='*' an attacker can set X-Forwarded-Host, so the victim
     * would receive a genuine email whose link carries their reset token to the
     * attacker's domain. Pinning the root to APP_URL closes that, and APP_URL
     * is already what every queued mail and OAuth callback uses.
     */
    private function pinPasswordResetLinksToAppUrl(): void
    {
        ResetPassword::createUrlUsing(static function ($user, string $token): string {
            $path = route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false);

            return rtrim((string) config('app.url'), '/').$path;
        });
    }

    /**
     * Named rate limiters for the unauthenticated endpoints.
     *
     * Why these exist instead of the inline `throttle:5,1` they replace: the
     * inline limiter keys on the client IP, and the client IP is only as
     * trustworthy as `TRUSTED_PROXIES` (see bootstrap/app.php), which defaults
     * to trusting every proxy. That means `$request->ip()` is really "whatever the
     * caller wrote in X-Forwarded-For", and an attacker who rotates that header
     * gets an unlimited number of password guesses against a real account.
     *
     * Every limiter below therefore keys on something the caller cannot rotate
     * — the email they are trying to log in as, or the webhook token they are
     * posting to — with an IP-keyed bucket kept alongside as a second, wider
     * net. Returning several Limits from one callback makes Laravel require
     * *all* of them to pass.
     */
    private function bootRateLimiters(): void
    {
        RateLimiter::for('login', static fn (Request $request) => [
            // The bucket that actually matters: spoofing X-Forwarded-For does
            // not change which account is being attacked.
            Limit::perMinute(5)->by('login:'.self::normalizedEmail($request)),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', static fn (Request $request) => [
            Limit::perMinute(5)->by('pwreset:'.self::normalizedEmail($request)),
            Limit::perMinute(20)->by('pwreset-ip:'.$request->ip()),
        ]);

        // Second login step. Keyed on the user parked in the session by
        // LoginController, so neither a new IP nor a fresh session buys more
        // guesses at one account's codes; the hourly cap bounds a slow,
        // patient guesser who already knows the password.
        RateLimiter::for('two-factor', static function (Request $request) {
            $pending = $request->session()->get(LoginController::PENDING_TWO_FACTOR);
            $key = is_array($pending) && isset($pending['id']) ? 'user:'.$pending['id'] : 'ip:'.$request->ip();

            return [
                Limit::perMinute(5)->by('2fa:'.$key),
                Limit::perHour(30)->by('2fa-hour:'.$key),
            ];
        });

        // Password / code checks on the profile 2FA forms.
        RateLimiter::for('two-factor-manage', static fn (Request $request) => [
            Limit::perMinute(6)->by('2fa-manage:'.($request->user()?->id ?? $request->ip())),
        ]);

        // Keyed on the endpoint token from the route, so one noisy integration
        // cannot exhaust another's budget and a caller cannot widen its own by
        // changing its apparent IP. Tokens that don't exist share one
        // IP-keyed bucket — otherwise every guessed token would get a fresh
        // allowance and scanning for valid tokens would be unbounded.
        RateLimiter::for('webhook', static function (Request $request) {
            $token = (string) $request->route('token');

            if ($token !== '' && WebhookEndpoint::where('token', $token)->exists()) {
                return Limit::perMinute(60)->by('webhook:'.sha1($token));
            }

            return Limit::perMinute(10)->by('webhook-ip:'.$request->ip());
        });
    }

    /**
     * The reset form and the login form both submit `email`; normalizing here
     * keeps "Bob@Example.com " and "bob@example.com" in the same bucket rather
     * than handing an attacker a fresh allowance per casing.
     */
    private static function normalizedEmail(Request $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        return $email !== '' ? $email : 'unknown';
    }

    /**
     * Log a loud warning for the two production misconfigurations that leak
     * the most: debug mode (renders request/config context on any 500, and
     * this install holds live ad-platform, SMTP and AI credentials) and a
     * session cookie without the Secure flag.
     *
     * Warning rather than throwing on purpose — refusing to boot would turn a
     * misconfiguration into an outage.
     */
    private function warnAboutInsecureProductionConfig(): void
    {
        // An https:// APP_URL means a real deployment even when APP_ENV was
        // left at the docker-compose default of "local".
        $publiclyDeployed = $this->app->environment('production')
            || str_starts_with((string) config('app.url'), 'https://');

        if (! $publiclyDeployed || $this->app->runningInConsole()) {
            return;
        }

        if (config('app.debug')) {
            Log::warning('lodgely.security.debug_enabled_in_production', [
                'hint' => 'Set APP_DEBUG=false. Debug mode renders configuration and request details on error pages.',
            ]);
        }

        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if (in_array($appHost, ['', 'localhost', '127.0.0.1'], true)) {
            Log::warning('lodgely.security.app_url_not_public', [
                'hint' => 'Set APP_URL to your public https:// address. Password-reset links are built from it.',
            ]);
        }

        if (! config('session.secure')) {
            Log::warning('lodgely.security.insecure_session_cookie', [
                'hint' => 'Set SESSION_SECURE_COOKIE=true when serving over HTTPS so session cookies are not sent over plain HTTP.',
            ]);
        }
    }
}
