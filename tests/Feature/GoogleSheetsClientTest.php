<?php

namespace Tests\Feature;

use App\Importers\GoogleSheets\GoogleSheetsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleSheetsClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function configureCredentials(): void
    {
        config()->set('lodgely.importers.google_sheets', [
            'client_id' => 'cid',
            'client_secret' => 'csec',
            'refresh_token' => 'rtok',
            'scopes' => ['https://www.googleapis.com/auth/spreadsheets.readonly'],
            'http_timeout_sec' => 30,
        ]);
    }

    public function test_fetch_values_refreshes_token_and_returns_rows(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3599], 200),
            'sheets.googleapis.com/*' => Http::response([
                'range' => 'Sheet1!A1:B2',
                'values' => [['name', 'email'], ['Alice', 'a@example.com']],
            ], 200),
        ]);

        $rows = (new GoogleSheetsClient)->fetchValues('SHEET_ID', 'Sheet1!A1:B2');

        $this->assertSame([
            ['name', 'email'],
            ['Alice', 'a@example.com'],
        ], $rows);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return $request['grant_type'] === 'refresh_token'
                    && $request['refresh_token'] === 'rtok';
            }
            if (str_contains($request->url(), 'sheets.googleapis.com')) {
                return str_contains($request->url(), '/spreadsheets/SHEET_ID/values/Sheet1')
                    && $request->hasHeader('Authorization', 'Bearer fresh');
            }

            return false;
        });
    }

    public function test_access_token_is_cached_across_calls(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3599], 200),
            'sheets.googleapis.com/*' => Http::response(['values' => []], 200),
        ]);

        $client = new GoogleSheetsClient;
        $client->fetchValues('A', 'Sheet1!A1');
        $client->fetchValues('B', 'Sheet1!A1');

        $tokenCalls = 0;
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                $tokenCalls++;
            }
        }
        $this->assertSame(1, $tokenCalls);
    }

    public function test_fetch_throws_when_refresh_token_missing(): void
    {
        config()->set('lodgely.importers.google_sheets', [
            'client_id' => 'cid',
            'client_secret' => 'csec',
            'refresh_token' => '',
            'scopes' => [],
            'http_timeout_sec' => 30,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refresh_token must all be set');

        (new GoogleSheetsClient)->fetchValues('SHEET', 'A1');
    }

    public function test_fetch_throws_on_sheets_api_error(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3599], 200),
            'sheets.googleapis.com/*' => Http::response(['error' => 'PERMISSION_DENIED'], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Sheets values fetch failed (403)');

        (new GoogleSheetsClient)->fetchValues('SHEET', 'A1');
    }

    public function test_build_authorization_url_includes_required_params(): void
    {
        $this->configureCredentials();

        $url = (new GoogleSheetsClient)->buildAuthorizationUrl('https://app.test/cb', 'STATE123');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $this->assertSame('code', $params['response_type']);
        $this->assertSame('cid', $params['client_id']);
        $this->assertSame('https://app.test/cb', $params['redirect_uri']);
        $this->assertSame('offline', $params['access_type']);
        $this->assertSame('consent', $params['prompt']);
        $this->assertSame('STATE123', $params['state']);
        $this->assertSame('https://www.googleapis.com/auth/spreadsheets.readonly', $params['scope']);
    }

    public function test_build_authorization_url_throws_when_client_id_missing(): void
    {
        config()->set('lodgely.importers.google_sheets.client_id', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LODGELY_GOOGLE_SHEETS_CLIENT_ID');

        (new GoogleSheetsClient)->buildAuthorizationUrl('https://app.test/cb', 'STATE');
    }

    public function test_exchange_authorization_code_returns_payload(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at',
                'refresh_token' => 'rt-new',
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            ], 200),
        ]);

        $payload = (new GoogleSheetsClient)->exchangeAuthorizationCode('AUTH_CODE', 'https://app.test/cb');

        $this->assertSame('rt-new', $payload['refresh_token']);
        $this->assertSame('at', $payload['access_token']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'AUTH_CODE'
                && $request['redirect_uri'] === 'https://app.test/cb';
        });
    }

    public function test_exchange_authorization_code_throws_on_failure(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google OAuth code exchange failed (400)');

        (new GoogleSheetsClient)->exchangeAuthorizationCode('BAD', 'https://app.test/cb');
    }
}
