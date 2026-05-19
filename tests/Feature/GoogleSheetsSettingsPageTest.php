<?php

namespace Tests\Feature;

use App\Livewire\Settings\GoogleSheetsSettingsPage;
use App\Models\GoogleSheetsSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleSheetsSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function client(): User
    {
        return User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
    }

    public function test_settings_page_requires_authentication(): void
    {
        $this->get('/settings/google-sheets')->assertRedirect('/login');
    }

    public function test_client_cannot_access_settings_page(): void
    {
        $this->actingAs($this->client())
            ->get('/settings/google-sheets')
            ->assertForbidden();
    }

    public function test_operator_can_load_settings_page(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsSettingsPage::class)
            ->assertOk()
            ->assertSee('Google Sheets');
    }

    public function test_operator_can_save_client_id_and_secret(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->set('form.client_id', 'my-client-id.apps.googleusercontent.com')
            ->set('form.client_secret', 'my-secret')
            ->call('save')
            ->assertDispatched('toast');

        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertSame('my-client-id.apps.googleusercontent.com', $row->client_id);
        $this->assertSame('my-secret', $row->clientSecret());
        $this->assertNull($row->refreshToken());
        $this->assertFalse($row->isConnected());
    }

    public function test_saving_new_secret_clears_refresh_token(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('old-secret');
        $row->setRefreshToken('old-refresh');
        $row->save();

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->set('form.client_id', 'cid')
            ->set('form.client_secret', 'new-secret')
            ->call('save');

        $row->refresh();
        $this->assertNull($row->refreshToken());
    }

    public function test_blank_secret_preserves_existing_secret(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('existing-secret');
        $row->save();

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->set('form.client_id', 'cid')
            ->set('form.client_secret', '')   // blank = keep existing
            ->call('save');

        $row->refresh();
        $this->assertSame('existing-secret', $row->clientSecret());
    }

    public function test_disconnect_clears_refresh_token(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('secret');
        $row->setRefreshToken('refresh-tok');
        $row->save();

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->call('disconnect')
            ->assertDispatched('toast');

        $row->refresh();
        $this->assertNull($row->refreshToken());
        $this->assertFalse($row->isConnected());
    }

    public function test_test_connection_succeeds_with_valid_token(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('secret');
        $row->setRefreshToken('refresh-tok');
        $row->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3599], 200),
        ]);

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->call('testConnection')
            ->assertSet('testResult', fn ($v) => str_starts_with($v, 'success:'));
    }

    public function test_test_connection_reports_error_on_failure(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('secret');
        $row->setRefreshToken('bad-token');
        $row->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->call('testConnection')
            ->assertSet('testResult', fn ($v) => str_starts_with($v, 'error:'));
    }

    public function test_page_shows_connected_status_when_fully_configured(): void
    {
        $op = $this->operator();
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('secret');
        $row->setRefreshToken('refresh-tok');
        $row->save();

        Livewire::actingAs($op)
            ->test(GoogleSheetsSettingsPage::class)
            ->assertSet('isConnected', true);
    }
}
