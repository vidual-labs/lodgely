<?php

namespace Tests\Feature;

use App\Importers\Meta\MetaCreativeSource;
use App\Models\AdCreativeReport;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MetaCreativeSourceTest extends TestCase
{
    private function configureCredentials(): void
    {
        config()->set('lodgely.reporting.meta.access_token', 'test-token');
        config()->set('lodgely.reporting.meta.ad_account_id', '1234567890');
        config()->set('lodgely.reporting.meta.api_version', 'v21.0');
        config()->set('lodgely.reporting.meta.currency', 'EUR');
    }

    public function test_fetch_maps_ad_and_segment_rows_to_snapshots(): void
    {
        $this->configureCredentials();

        // First call: level=ad insights. Second call: age×gender breakdown.
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'data' => [
                        [
                            'ad_id' => '9001',
                            'ad_name' => 'Lakeside carousel',
                            'campaign_id' => '23800000001',
                            'campaign_name' => 'Spring – Lead Gen',
                            'impressions' => '1500',
                            'clicks' => '90',
                            'spend' => '12.50',
                            'actions' => [
                                ['action_type' => 'lead', 'value' => '4'],
                                ['action_type' => 'link_click', 'value' => '90'],
                            ],
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        [
                            'age' => '25-34',
                            'gender' => 'female',
                            'impressions' => '800',
                            'clicks' => '40',
                            'spend' => '6.00',
                            'actions' => [
                                ['action_type' => 'offsite_conversion.fb_pixel_lead', 'value' => '2'],
                            ],
                        ],
                    ],
                ]),
        ]);

        $snapshots = iterator_to_array((new MetaCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));

        $this->assertCount(2, $snapshots);

        [$ad, $segment] = $snapshots;

        $this->assertSame('meta', $ad->platform);
        $this->assertSame(AdCreativeReport::DIMENSION_AD, $ad->dimension);
        $this->assertSame('9001', $ad->externalId);
        $this->assertSame('Lakeside carousel', $ad->label);
        $this->assertSame('23800000001', $ad->campaignId);
        $this->assertSame(1500, $ad->impressions);
        $this->assertSame(90, $ad->clicks);
        $this->assertSame(1250, $ad->spendCents);
        $this->assertSame('EUR', $ad->currency);
        $this->assertSame(4, $ad->platformLeads);

        $this->assertSame(AdCreativeReport::DIMENSION_SEGMENT, $segment->dimension);
        $this->assertSame('25-34|female', $segment->externalId);
        $this->assertSame('25-34 · female', $segment->label);
        $this->assertNull($segment->campaignId);
        $this->assertSame(600, $segment->spendCents);
        $this->assertSame(2, $segment->platformLeads);

        // The two insights calls: level=ad first, then the breakdown pass.
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'level=ad'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'breakdowns=age%2Cgender'));
    }

    public function test_fetch_throws_when_credentials_missing(): void
    {
        config()->set('lodgely.reporting.meta', []);

        $this->expectException(RuntimeException::class);

        iterator_to_array((new MetaCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));
    }

    public function test_fetch_throws_on_api_failure(): void
    {
        $this->configureCredentials();

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'nope'], 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meta Ads creative insights call failed (500)');

        iterator_to_array((new MetaCreativeSource)->fetch(1, new \DateTimeImmutable('2026-06-01')));
    }
}
