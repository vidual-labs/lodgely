<?php

namespace Tests\Feature;

use App\Importers\Meta\MetaAdsSource;
use App\Livewire\Settings\AdPlatformsPage;
use App\Models\AdPlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdPlatformsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    public function test_secrets_are_encrypted_and_effective_getters_fall_back_to_config(): void
    {
        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->setMetaAccessToken('plain-token');
        $row->save();

        // Ciphertext on disk, never the plaintext.
        $this->assertNotNull($row->meta_access_token_encrypted);
        $this->assertNotSame('plain-token', $row->meta_access_token_encrypted);
        $this->assertSame('plain-token', $row->fresh()->metaAccessToken());

        // Effective getter falls back to env config when the column is empty
        // (ad_account_id defaults to '', so it is genuinely unset here).
        config()->set('lodgely.reporting.meta.ad_account_id', '777');
        $fresh = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertSame('777', $fresh->effectiveMetaAccountId());
    }

    public function test_active_source_keys_combine_env_and_ui_toggles(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock']);

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->meta_enabled = true;
        $row->google_enabled = false;
        $row->save();

        $this->assertEqualsCanonicalizing(
            ['meta_mock', 'meta'],
            AdPlatformSetting::activeSourceKeys(Tenant::DEFAULT_ID),
        );
    }

    public function test_meta_adapter_reads_credentials_from_the_database(): void
    {
        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->meta_ad_account_id = '555';
        $row->setMetaAccessToken('db-token');
        $row->save();

        // Config holds a different token; the DB value must win.
        config()->set('lodgely.reporting.meta.access_token', 'env-token');
        config()->set('lodgely.reporting.meta.ad_account_id', '111');

        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []], 200)]);

        iterator_to_array((new MetaAdsSource)->fetch(Tenant::DEFAULT_ID, new \DateTimeImmutable('2026-05-17')));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'act_555/insights')
                && $request['access_token'] === 'db-token';
        });
    }

    public function test_operator_saves_settings_and_client_secret_is_encrypted(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(AdPlatformsPage::class)
            ->set('form.google_client_id', 'client-123.apps.googleusercontent.com')
            ->set('form.google_client_secret', 'super-secret')
            ->set('form.google_customer_id', '1234567890')
            ->set('form.google_developer_token', 'dev-token')
            ->set('form.google_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertTrue($row->google_enabled);
        $this->assertSame('client-123.apps.googleusercontent.com', $row->google_client_id);
        $this->assertSame('super-secret', $row->googleClientSecret());
        $this->assertSame('dev-token', $row->googleDeveloperToken());
        $this->assertNotSame('super-secret', $row->google_client_secret_encrypted);
    }

    public function test_changing_client_id_clears_the_refresh_token(): void
    {
        $op = $this->operator();

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->google_client_id = 'old-client';
        $row->setGoogleClientSecret('secret');
        $row->setGoogleRefreshToken('refresh-abc');
        $row->save();

        Livewire::actingAs($op)
            ->test(AdPlatformsPage::class)
            ->set('form.google_client_id', 'new-client')
            ->call('save');

        $this->assertNull($row->fresh()->googleRefreshToken());
    }

    public function test_clients_cannot_open_the_page(): void
    {
        $this->operator();
        $client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        Livewire::actingAs($client)
            ->test(AdPlatformsPage::class)
            ->assertStatus(403);
    }

    public function test_google_oauth_callback_captures_the_refresh_token(): void
    {
        $op = $this->operator();

        $row = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $row->google_client_id = 'client-123';
        $row->setGoogleClientSecret('secret');
        $row->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token'  => 'at',
                'refresh_token' => 'captured-refresh',
                'expires_in'    => 3600,
            ], 200),
        ]);

        $this->actingAs($op)
            ->withSession(['google_ads_oauth_state' => 'state-xyz'])
            ->get('/settings/ad-platforms/google/callback?code=auth-code&state=state-xyz')
            ->assertRedirect(route('settings.ad-platforms'))
            ->assertSessionHas('oauth_success');

        $this->assertSame('captured-refresh', $row->fresh()->googleRefreshToken());
    }

    public function test_google_oauth_callback_rejects_state_mismatch(): void
    {
        $op = $this->operator();

        $this->actingAs($op)
            ->withSession(['google_ads_oauth_state' => 'expected'])
            ->get('/settings/ad-platforms/google/callback?code=auth-code&state=wrong')
            ->assertRedirect(route('settings.ad-platforms'))
            ->assertSessionHas('oauth_error');
    }
}
