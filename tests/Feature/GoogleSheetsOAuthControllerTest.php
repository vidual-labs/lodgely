<?php

namespace Tests\Feature;

use App\Models\GoogleSheetsSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetsOAuthControllerTest extends TestCase
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

    private function configureDbCredentials(): void
    {
        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->client_id = 'cid';
        $row->setClientSecret('csec');
        $row->save();
    }

    public function test_connect_requires_authentication(): void
    {
        $this->get('/settings/google-sheets/connect')->assertRedirect('/login');
    }

    public function test_client_role_is_forbidden(): void
    {
        $this->actingAs($this->client())
            ->get('/settings/google-sheets/connect')
            ->assertForbidden();
    }

    public function test_operator_is_redirected_to_google_consent_with_state(): void
    {
        $this->configureDbCredentials();

        $response = $this->actingAs($this->operator())
            ->get('/settings/google-sheets/connect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $params);

        $this->assertSame('cid', $params['client_id']);
        $this->assertSame(route('settings.google-sheets.callback'), $params['redirect_uri']);
        $this->assertNotEmpty($params['state']);
        $this->assertSame($params['state'], session('google_sheets_oauth_state'));
    }

    public function test_connect_redirects_with_error_when_client_id_missing(): void
    {
        GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);

        $this->actingAs($this->operator())
            ->get('/settings/google-sheets/connect')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_error');
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->configureDbCredentials();

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'EXPECTED'])
            ->get('/settings/google-sheets/callback?code=abc&state=WRONG')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_error', fn ($v) => str_contains($v, 'state mismatch'));
    }

    public function test_callback_rejects_google_error_query(): void
    {
        $this->configureDbCredentials();

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?error=access_denied&state=S')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_error', fn ($v) => str_contains($v, 'access_denied'));
    }

    public function test_callback_saves_refresh_token_to_db_and_redirects(): void
    {
        $this->configureDbCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at',
                'refresh_token' => 'NEW-REFRESH-TOKEN',
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            ], 200),
        ]);

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'STATE-OK'])
            ->get('/settings/google-sheets/callback?code=AUTH_CODE&state=STATE-OK')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_success');

        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertSame('NEW-REFRESH-TOKEN', $row->refreshToken());
        $this->assertTrue($row->isConnected());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['code'] === 'AUTH_CODE'
                && $request['grant_type'] === 'authorization_code';
        });
    }

    public function test_callback_redirects_with_error_when_no_refresh_token_returned(): void
    {
        $this->configureDbCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at',
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            ], 200),
        ]);

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?code=AUTH_CODE&state=S')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_error', fn ($v) => str_contains($v, 'refresh token'));
    }

    public function test_callback_redirects_with_error_when_token_exchange_fails(): void
    {
        $this->configureDbCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?code=BAD&state=S')
            ->assertRedirect(route('settings.google-sheets'))
            ->assertSessionHas('oauth_error', fn ($v) => str_contains($v, 'code exchange failed'));
    }
}
