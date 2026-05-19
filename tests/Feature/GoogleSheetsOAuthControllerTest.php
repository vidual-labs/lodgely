<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetsOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function client(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
    }

    private function configureCredentials(): void
    {
        config()->set('lodgely.importers.google_sheets', [
            'client_id' => 'cid',
            'client_secret' => 'csec',
            'refresh_token' => '',
            'scopes' => ['https://www.googleapis.com/auth/spreadsheets.readonly'],
            'http_timeout_sec' => 30,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_connect_requires_authentication(): void
    {
        $this->get('/settings/google-sheets/connect')->assertRedirect('/login');
    }

    public function test_client_role_is_forbidden(): void
    {
        $this->configureCredentials();

        $this->actingAs($this->client())
            ->get('/settings/google-sheets/connect')
            ->assertForbidden();
    }

    public function test_operator_is_redirected_to_google_consent_with_state(): void
    {
        $this->configureCredentials();

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

    public function test_connect_renders_error_view_when_client_id_missing(): void
    {
        config()->set('lodgely.importers.google_sheets', [
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
            'scopes' => [],
            'http_timeout_sec' => 30,
        ]);

        $this->actingAs($this->operator())
            ->get('/settings/google-sheets/connect')
            ->assertOk()
            ->assertSee('LODGELY_GOOGLE_SHEETS_CLIENT_ID');
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->configureCredentials();

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'EXPECTED'])
            ->get('/settings/google-sheets/callback?code=abc&state=WRONG')
            ->assertOk()
            ->assertSee('state mismatch', false);
    }

    public function test_callback_rejects_google_error_query(): void
    {
        $this->configureCredentials();

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?error=access_denied&state=S')
            ->assertOk()
            ->assertSee('access_denied');
    }

    public function test_callback_exchanges_code_and_displays_refresh_token(): void
    {
        $this->configureCredentials();

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
            ->assertOk()
            ->assertSee('NEW-REFRESH-TOKEN')
            ->assertSee('LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN=NEW-REFRESH-TOKEN');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['code'] === 'AUTH_CODE'
                && $request['grant_type'] === 'authorization_code';
        });
    }

    public function test_callback_warns_when_no_refresh_token_returned(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at',
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            ], 200),
        ]);

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?code=AUTH_CODE&state=S')
            ->assertOk()
            ->assertSee('did not return a refresh token');
    }

    public function test_callback_shows_error_when_token_exchange_fails(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->actingAs($this->operator())
            ->withSession(['google_sheets_oauth_state' => 'S'])
            ->get('/settings/google-sheets/callback?code=BAD&state=S')
            ->assertOk()
            ->assertSee('Google OAuth code exchange failed (400)');
    }
}
