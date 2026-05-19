<?php

namespace App\Livewire\Settings;

use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Models\GoogleSheetsSetting;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class GoogleSheetsSettingsPage extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        'client_id'     => '',
        'client_secret' => '',      // write-only; blank = "leave as-is"
        'has_secret'    => false,
    ];

    public bool $isConnected = false;

    public ?string $testResult = null;

    public function mount(): void
    {
        $this->guardOperator();
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);

        $this->form = [
            'client_id'     => (string) $row->client_id,
            'client_secret' => '',
            'has_secret'    => (bool) $row->client_secret_encrypted,
        ];

        $this->isConnected = $row->isConnected();
    }

    public function save(): void
    {
        $this->guardOperator();

        $data = $this->validate([
            'form.client_id'     => ['required', 'string', 'max:255'],
            'form.client_secret' => ['nullable', 'string', 'max:500'],
        ])['form'];

        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);

        $row->client_id = trim((string) $data['client_id']);

        if (! empty($data['client_secret'])) {
            $row->setClientSecret($data['client_secret']);

            // A new secret invalidates the existing refresh token because
            // the token was issued for the old credential pair.
            $row->setRefreshToken(null);
            Cache::forget('lodgely.google_sheets.access_token.'.sha1($row->client_id.'|'));
        }

        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('Credentials saved. Click "Connect to Google" to authorize.'), type: 'success');
    }

    public function disconnect(): void
    {
        $this->guardOperator();

        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);

        Cache::forget(
            'lodgely.google_sheets.access_token.'.sha1($row->client_id.'|'.($row->refreshToken() ?? ''))
        );

        $row->setRefreshToken(null);
        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('Disconnected from Google Sheets.'), type: 'success');
    }

    public function testConnection(GoogleSheetsClient $client): void
    {
        $this->guardOperator();

        try {
            $config = (array) config('lodgely.importers.google_sheets');
            $timeout = (int) ($config['http_timeout_sec'] ?? 30);

            $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
            $merged = array_merge($config, [
                'client_id'     => $row->client_id,
                'client_secret' => $row->clientSecret() ?? '',
                'refresh_token' => $row->refreshToken() ?? '',
            ]);

            $client->accessToken($merged, $timeout);

            $this->testResult = 'success:'.__('Connected — access token retrieved successfully.');
        } catch (\Throwable $e) {
            $this->testResult = 'error:'.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.settings.google-sheets-settings-page', [
            'connectUrl' => route('settings.google-sheets.connect'),
            'oauthSuccess' => session('oauth_success'),
            'oauthError'   => session('oauth_error'),
        ]);
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
