<?php

namespace Tests\Feature;

use App\Importers\Google\GoogleAdsSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleAdsSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function configureCredentials(): void
    {
        config()->set('lodgely.reporting.google', [
            'client_id' => 'cid',
            'client_secret' => 'csec',
            'refresh_token' => 'rtok',
            'developer_token' => 'dtok',
            'login_customer_id' => '111-222-3333',
            'customer_id' => '9999999999',
            'api_version' => 'v18',
        ]);
    }

    public function test_fetch_refreshes_token_and_maps_results(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3599], 200),
            'googleads.googleapis.com/*' => Http::response([
                'results' => [
                    [
                        'campaign' => ['id' => '111', 'name' => 'Branded'],
                        'metrics' => [
                            'impressions' => '1234',
                            'clicks' => '56',
                            'costMicros' => '12500000', // 12.50 currency units → 1250 cents
                            'conversions' => 4.6,
                        ],
                        'customer' => ['currencyCode' => 'GBP'],
                    ],
                ],
            ], 200),
        ]);

        $source = new GoogleAdsSource;
        $snapshots = iterator_to_array($source->fetch(1, new \DateTimeImmutable('2026-05-17')));

        $this->assertCount(1, $snapshots);
        $snap = $snapshots[0];

        $this->assertSame('google', $snap->platform);
        $this->assertSame('2026-05-17', $snap->date);
        $this->assertSame('111', $snap->campaignId);
        $this->assertSame('Branded', $snap->campaignName);
        $this->assertSame(1234, $snap->impressions);
        $this->assertSame(56, $snap->clicks);
        $this->assertSame(1250, $snap->spendCents);
        $this->assertSame('GBP', $snap->currency);
        // Conversions round half-up to 5.
        $this->assertSame(5, $snap->platformLeads);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return $request['grant_type'] === 'refresh_token'
                    && $request['refresh_token'] === 'rtok';
            }
            if (str_contains($request->url(), 'googleads.googleapis.com')) {
                return str_contains($request->url(), '/customers/9999999999/googleAds:search')
                    && $request->hasHeader('developer-token', 'dtok')
                    && $request->hasHeader('login-customer-id', '1112223333')
                    && $request->hasHeader('Authorization', 'Bearer fresh-token')
                    && str_contains($request['query'], "segments.date = '2026-05-17'");
            }

            return false;
        });
    }

    public function test_access_token_is_cached_across_calls(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3599], 200),
            'googleads.googleapis.com/*' => Http::response(['results' => []], 200),
        ]);

        $source = new GoogleAdsSource;
        iterator_to_array($source->fetch(1, new \DateTimeImmutable('2026-05-17')));
        iterator_to_array($source->fetch(1, new \DateTimeImmutable('2026-05-18')));

        // OAuth endpoint should only be hit once because the access token is cached.
        $tokenCalls = 0;
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                $tokenCalls++;
            }
        }
        $this->assertSame(1, $tokenCalls);
    }

    public function test_fetch_throws_when_credentials_missing(): void
    {
        config()->set('lodgely.reporting.google', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LODGELY_GOOGLE_ADS_CUSTOMER_ID');

        iterator_to_array((new GoogleAdsSource)->fetch(1, new \DateTimeImmutable('2026-05-17')));
    }

    public function test_fetch_throws_on_oauth_failure(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google OAuth token refresh failed (400)');

        iterator_to_array((new GoogleAdsSource)->fetch(1, new \DateTimeImmutable('2026-05-17')));
    }

    public function test_fetch_throws_on_search_failure(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 't', 'expires_in' => 3599], 200),
            'googleads.googleapis.com/*' => Http::response(['error' => 'PERMISSION_DENIED'], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Ads search call failed (403)');

        iterator_to_array((new GoogleAdsSource)->fetch(1, new \DateTimeImmutable('2026-05-17')));
    }
}
