<?php

namespace App\Livewire\Settings;

use App\Importers\Google\GoogleAdsSource;
use App\Importers\Meta\MetaAdsSource;
use App\Models\AdPlatformSetting;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Operator-only UI for connecting Meta Ads and Google Ads, so going live no
 * longer requires editing .env. Secrets are write-only in the form (blank =
 * "leave as-is") and stored encrypted on the AdPlatformSetting row.
 */
#[Layout('components.layouts.app')]
class AdPlatformsPage extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        // Meta
        'meta_enabled'        => false,
        'meta_ad_account_id'  => '',
        'meta_currency'       => 'USD',
        'meta_api_version'    => 'v21.0',
        'meta_access_token'   => '',   // write-only
        'has_meta_token'      => false,
        // Google
        'google_enabled'             => false,
        'google_customer_id'         => '',
        'google_login_customer_id'   => '',
        'google_api_version'         => 'v18',
        'google_client_id'           => '',
        'google_client_secret'       => '',  // write-only
        'has_google_secret'          => false,
        'google_developer_token'     => '',  // write-only
        'has_google_developer'       => false,
        'has_google_refresh'         => false,
    ];

    public ?string $metaTestResult = null;

    public ?string $googleTestResult = null;

    public function mount(): void
    {
        $this->guardOperator();
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);

        $this->form = [
            'meta_enabled'        => (bool) $row->meta_enabled,
            'meta_ad_account_id'  => (string) $row->meta_ad_account_id,
            'meta_currency'       => (string) ($row->meta_currency ?: 'USD'),
            'meta_api_version'    => (string) ($row->meta_api_version ?: 'v21.0'),
            'meta_access_token'   => '',
            'has_meta_token'      => $row->effectiveMetaAccessToken() !== '',

            'google_enabled'           => (bool) $row->google_enabled,
            'google_customer_id'       => (string) $row->google_customer_id,
            'google_login_customer_id' => (string) $row->google_login_customer_id,
            'google_api_version'       => (string) ($row->google_api_version ?: 'v18'),
            'google_client_id'         => (string) $row->google_client_id,
            'google_client_secret'     => '',
            'has_google_secret'        => $row->effectiveGoogleClientSecret() !== '',
            'google_developer_token'   => '',
            'has_google_developer'     => $row->effectiveGoogleDeveloperToken() !== '',
            'has_google_refresh'       => $row->effectiveGoogleRefreshToken() !== '',
        ];
    }

    public function save(): void
    {
        $this->guardOperator();

        $data = $this->validate([
            'form.meta_enabled'        => ['boolean'],
            'form.meta_ad_account_id'  => ['nullable', 'string', 'max:64'],
            'form.meta_currency'       => ['nullable', 'string', 'max:8'],
            'form.meta_api_version'    => ['nullable', 'string', 'max:16'],
            'form.meta_access_token'   => ['nullable', 'string', 'max:1000'],

            'form.google_enabled'           => ['boolean'],
            'form.google_customer_id'       => ['nullable', 'string', 'max:32'],
            'form.google_login_customer_id' => ['nullable', 'string', 'max:32'],
            'form.google_api_version'       => ['nullable', 'string', 'max:16'],
            'form.google_client_id'         => ['nullable', 'string', 'max:255'],
            'form.google_client_secret'     => ['nullable', 'string', 'max:255'],
            'form.google_developer_token'   => ['nullable', 'string', 'max:255'],
        ])['form'];

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);

        // Meta
        $row->meta_enabled       = (bool) $data['meta_enabled'];
        $row->meta_ad_account_id = trim((string) $data['meta_ad_account_id']);
        $row->meta_currency      = trim((string) $data['meta_currency']) ?: 'USD';
        $row->meta_api_version   = trim((string) $data['meta_api_version']) ?: 'v21.0';
        if (! empty($data['meta_access_token'])) {
            $row->setMetaAccessToken(trim((string) $data['meta_access_token']));
        }

        // Google
        $row->google_enabled           = (bool) $data['google_enabled'];
        $row->google_customer_id       = trim((string) $data['google_customer_id']);
        $row->google_login_customer_id = trim((string) $data['google_login_customer_id']);
        $row->google_api_version       = trim((string) $data['google_api_version']) ?: 'v18';

        $newClientId = trim((string) $data['google_client_id']);
        $clientIdChanged = $newClientId !== (string) $row->google_client_id;
        $row->google_client_id = $newClientId;

        if (! empty($data['google_client_secret'])) {
            $row->setGoogleClientSecret(trim((string) $data['google_client_secret']));
        }
        if (! empty($data['google_developer_token'])) {
            $row->setGoogleDeveloperToken(trim((string) $data['google_developer_token']));
        }

        // Changing the OAuth client invalidates the captured refresh token —
        // it was issued for the old client pair, so force a fresh connect.
        if ($clientIdChanged || ! empty($data['google_client_secret'])) {
            $row->setGoogleRefreshToken(null);
        }

        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('Ad platform settings saved.'), type: 'success');
    }

    public function disconnectGoogle(): void
    {
        $this->guardOperator();

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->setGoogleRefreshToken(null);
        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('Disconnected from Google Ads.'), type: 'success');
    }

    public function testMeta(): void
    {
        $this->guardOperator();
        $this->metaTestResult = $this->runTest(new MetaAdsSource(), AdPlatformSetting::forTenant(Tenant::DEFAULT_ID));
    }

    public function testGoogle(): void
    {
        $this->guardOperator();
        $this->googleTestResult = $this->runTest(new GoogleAdsSource(), AdPlatformSetting::forTenant(Tenant::DEFAULT_ID));
    }

    /**
     * Pulls yesterday's metrics against the saved credentials and reports the
     * outcome. Calls fetchOne() directly (bypassing the enabled toggle, which
     * only gates the scheduled fetch) so an operator can test a connection
     * before deciding to switch on the daily pull.
     */
    private function runTest(MetaAdsSource|GoogleAdsSource $source, AdPlatformSetting $settings): string
    {
        try {
            $date = new \DateTimeImmutable('yesterday');
            $snapshots = iterator_to_array($source->fetchOne($settings, $date));

            return 'success:'.__(':count campaign(s) returned for :date.', [
                'count' => count($snapshots),
                'date'  => $date->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            return 'error:'.$e->getMessage();
        }
    }

    public function render(): View
    {
        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $appUrl = rtrim((string) config('app.url'), '/');

        $connectors = AdPlatformSetting::connectorsForTenant(Tenant::DEFAULT_ID)
            ->filter(fn (AdPlatformSetting $c) => $c->client_name !== null);

        return view('livewire.settings.ad-platforms-page', [
            'isMetaConnected'   => $row->isMetaConnected(),
            'isGoogleConnected' => $row->isGoogleConnected(),
            'googleConnectUrl'  => route('settings.ad-platforms.google.connect'),
            'googleRedirectUri' => $appUrl.'/settings/ad-platforms/google/callback',
            'appUrlIsHttps'     => str_starts_with($appUrl, 'https://'),
            'oauthSuccess'      => session('oauth_success'),
            'oauthError'        => session('oauth_error'),
            'connectors'        => $connectors,
        ]);
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
