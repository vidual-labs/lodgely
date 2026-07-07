<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\AdPlatformSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operator-only OAuth handshake for the Google Ads API.
 *
 * Removes the hardest step of going live with Google Ads: instead of running
 * an external script to mint a refresh token, the operator clicks "Connect
 * Google Ads", grants access, and the refresh token is captured and stored
 * (encrypted) on the tenant's AdPlatformSetting row. The client id / secret
 * the operator saved in the settings UI drive the exchange.
 */
class GoogleAdsOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'google_ads_oauth_state';

    private const CONNECTOR_SESSION_KEY = 'google_ads_oauth_connector_id';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $settings = $this->resolveConnector($request);
        $redirectRoute = $this->redirectRoute($settings);
        $clientId = trim($settings->effectiveGoogleClientId());

        if ($clientId === '' || trim($settings->effectiveGoogleClientSecret()) === '') {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', __('Save your Google Ads client ID and secret first, then connect.'));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);
        $request->session()->put(self::CONNECTOR_SESSION_KEY, $settings->exists ? $settings->id : null);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPE,
            // offline + consent guarantees Google returns a refresh_token.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return redirect()->away(self::AUTH_URL.'?'.http_build_query($params));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $connectorId = $request->session()->pull(self::CONNECTOR_SESSION_KEY);
        $settings = $connectorId
            ? (AdPlatformSetting::query()->where('tenant_id', Tenant::DEFAULT_ID)->find($connectorId) ?? AdPlatformSetting::forTenant(Tenant::DEFAULT_ID))
            : AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $redirectRoute = $this->redirectRoute($settings);

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === null || $expectedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', __('OAuth state mismatch. Restart the authorize flow from the beginning.'));
        }

        if ($error = $request->query('error')) {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', __('Google returned an error: :error', ['error' => (string) $error]));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', __('Google did not return an authorization code.'));
        }

        $clientId = trim($settings->effectiveGoogleClientId());
        $clientSecret = trim($settings->effectiveGoogleClientSecret());
        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->asForm()
                ->acceptJson()
                ->post(self::TOKEN_URL, [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $this->redirectUri(),
                    'grant_type' => 'authorization_code',
                ]);

            if (! $response->successful()) {
                return redirect()->to($redirectRoute)
                    ->with('oauth_error', __('Google OAuth code exchange failed (:status).', ['status' => $response->status()]));
            }

            $refreshToken = $response->json('refresh_token');
        } catch (Throwable $e) {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', $e->getMessage());
        }

        if (! is_string($refreshToken) || $refreshToken === '') {
            return redirect()->to($redirectRoute)
                ->with('oauth_error', __('Google did not return a refresh token. Revoke access at https://myaccount.google.com/permissions and try again.'));
        }

        $settings->setGoogleRefreshToken($refreshToken);
        $settings->save();

        // Bust any cached access token so the new refresh token takes effect
        // immediately (matches the cache key GoogleAdsSource uses).
        Cache::forget('lodgely.google_ads.access_token.'.sha1($clientId.'|'.$refreshToken));

        return redirect()->to($redirectRoute)
            ->with('oauth_success', __('Google Ads connected successfully.'));
    }

    /** Resolve which connector this request is acting on: ?connector=<id> or the tenant default. */
    private function resolveConnector(Request $request): AdPlatformSetting
    {
        $connectorId = $request->query('connector');

        if ($connectorId) {
            $connector = AdPlatformSetting::query()
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->find($connectorId);

            if ($connector && $connector->client_name !== null) {
                return $connector;
            }
        }

        return AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
    }

    /** Send the operator back to the right settings page: the connector edit page, or the main ad-platforms page for the default. */
    private function redirectRoute(AdPlatformSetting $settings): string
    {
        return $settings->client_name !== null
            ? route('settings.ad-platforms.connectors.edit', $settings)
            : route('settings.ad-platforms');
    }

    private function redirectUri(): string
    {
        // Build from APP_URL so the scheme always matches the operator's
        // configured public URL even behind a plain-HTTP reverse proxy.
        return rtrim((string) config('app.url'), '/').'/settings/ad-platforms/google/callback';
    }
}
