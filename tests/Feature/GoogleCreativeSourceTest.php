<?php

namespace Tests\Feature;

use App\Importers\Google\GoogleCreativeSource;
use App\Models\AdCreativeReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleCreativeSourceTest extends TestCase
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

    public function test_fetch_maps_keyword_and_ad_rows_to_snapshots(): void
    {
        $this->configureCredentials();

        // First search call: keyword_view. Second: ad_group_ad.
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3599], 200),
            'googleads.googleapis.com/*' => Http::sequence()
                ->push([
                    'results' => [
                        [
                            'adGroup' => ['id' => '555'],
                            'adGroupCriterion' => [
                                'criterionId' => '777',
                                'keyword' => ['text' => 'holiday lodges', 'matchType' => 'PHRASE'],
                            ],
                            'campaign' => ['id' => '111', 'name' => 'Branded'],
                            'customer' => ['currencyCode' => 'GBP'],
                            'metrics' => [
                                'impressions' => '400',
                                'clicks' => '20',
                                'costMicros' => '5000000', // 5.00 → 500 cents
                                'conversions' => 1.4,
                            ],
                        ],
                    ],
                ])
                ->push([
                    'results' => [
                        [
                            'adGroupAd' => ['ad' => ['id' => '8888']], // RSA without a name
                            'adGroup' => ['name' => 'Lodges – Exact'],
                            'campaign' => ['id' => '111', 'name' => 'Branded'],
                            'customer' => ['currencyCode' => 'GBP'],
                            'metrics' => [
                                'impressions' => '900',
                                'clicks' => '45',
                                'costMicros' => '12500000',
                                'conversions' => 3.6,
                            ],
                        ],
                    ],
                ]),
        ]);

        $snapshots = iterator_to_array((new GoogleCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));

        $this->assertCount(2, $snapshots);

        [$keyword, $ad] = $snapshots;

        $this->assertSame('google', $keyword->platform);
        $this->assertSame(AdCreativeReport::DIMENSION_KEYWORD, $keyword->dimension);
        $this->assertSame('555~777', $keyword->externalId);
        $this->assertSame('holiday lodges (phrase)', $keyword->label);
        $this->assertSame('111', $keyword->campaignId);
        $this->assertSame(400, $keyword->impressions);
        $this->assertSame(500, $keyword->spendCents);
        $this->assertSame('GBP', $keyword->currency);
        $this->assertSame(1, $keyword->platformLeads);

        $this->assertSame(AdCreativeReport::DIMENSION_AD, $ad->dimension);
        $this->assertSame('8888', $ad->externalId);
        // Nameless RSA falls back to ad-group name + id.
        $this->assertSame('Lodges – Exact · Ad #8888', $ad->label);
        $this->assertSame(1250, $ad->spendCents);
        $this->assertSame(4, $ad->platformLeads);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleads.googleapis.com')
            && str_contains($request['query'] ?? '', 'FROM keyword_view'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleads.googleapis.com')
            && str_contains($request['query'] ?? '', 'FROM ad_group_ad'));
    }

    public function test_fetch_throws_when_credentials_missing(): void
    {
        config()->set('lodgely.reporting.google', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('customer id and developer token must both be set');

        iterator_to_array((new GoogleCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));
    }

    public function test_fetch_throws_on_search_failure(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 't', 'expires_in' => 3599], 200),
            'googleads.googleapis.com/*' => Http::response(['error' => 'PERMISSION_DENIED'], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Ads creative search call failed (403)');

        iterator_to_array((new GoogleCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));
    }
}
