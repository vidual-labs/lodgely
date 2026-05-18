<?php

namespace Tests\Feature;

use App\Importers\Meta\MetaAdsSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MetaAdsSourceTest extends TestCase
{
    private function configureCredentials(): void
    {
        config()->set('lodgely.reporting.meta.access_token', 'test-token');
        config()->set('lodgely.reporting.meta.ad_account_id', '1234567890');
        config()->set('lodgely.reporting.meta.api_version', 'v21.0');
        config()->set('lodgely.reporting.meta.currency', 'EUR');
    }

    public function test_fetch_maps_insights_rows_to_snapshots(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'campaign_id' => '23800000001',
                        'campaign_name' => 'Spring – Lead Gen',
                        'impressions' => '4321',
                        'clicks' => '210',
                        'spend' => '42.50',
                        'reach' => '3000',
                        'actions' => [
                            ['action_type' => 'link_click', 'value' => '210'],
                            ['action_type' => 'lead', 'value' => '7'],
                            ['action_type' => 'offsite_conversion.fb_pixel_lead', 'value' => '3'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source = new MetaAdsSource;
        $snapshots = iterator_to_array($source->fetch(1, new \DateTimeImmutable('2026-05-17')));

        $this->assertCount(1, $snapshots);
        $snap = $snapshots[0];

        $this->assertSame('meta', $snap->platform);
        $this->assertSame('2026-05-17', $snap->date);
        $this->assertSame('23800000001', $snap->campaignId);
        $this->assertSame('Spring – Lead Gen', $snap->campaignName);
        $this->assertSame(4321, $snap->impressions);
        $this->assertSame(210, $snap->clicks);
        $this->assertSame(4250, $snap->spendCents);
        $this->assertSame('EUR', $snap->currency);
        $this->assertSame(3000, $snap->reach);
        // 7 (lead) + 3 (pixel lead) — link_click is ignored.
        $this->assertSame(10, $snap->platformLeads);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/act_1234567890/insights')
                && str_contains($request->url(), 'access_token=test-token')
                && str_contains($request->url(), 'level=campaign');
        });
    }

    public function test_fetch_follows_paging_next_link(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/v21.0/act_1234567890/insights*' => Http::response([
                'data' => [[
                    'campaign_id' => 'C1', 'campaign_name' => 'A',
                    'impressions' => '100', 'clicks' => '5', 'spend' => '1.00',
                    'actions' => [],
                ]],
                'paging' => ['next' => 'https://graph.facebook.com/next-page-cursor'],
            ], 200),
            'graph.facebook.com/next-page-cursor' => Http::response([
                'data' => [[
                    'campaign_id' => 'C2', 'campaign_name' => 'B',
                    'impressions' => '50', 'clicks' => '2', 'spend' => '0.50',
                    'actions' => [],
                ]],
            ], 200),
        ]);

        $source = new MetaAdsSource;
        $snapshots = iterator_to_array($source->fetch(1, new \DateTimeImmutable('2026-05-17')));

        $this->assertCount(2, $snapshots);
        $this->assertSame('C1', $snapshots[0]->campaignId);
        $this->assertSame('C2', $snapshots[1]->campaignId);
    }

    public function test_fetch_throws_when_credentials_missing(): void
    {
        config()->set('lodgely.reporting.meta.access_token', '');
        config()->set('lodgely.reporting.meta.ad_account_id', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LODGELY_META_ADS_ACCESS_TOKEN');

        iterator_to_array((new MetaAdsSource)->fetch(1, new \DateTimeImmutable('2026-05-17')));
    }

    public function test_fetch_throws_on_api_error(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meta Ads insights call failed (401)');

        iterator_to_array((new MetaAdsSource)->fetch(1, new \DateTimeImmutable('2026-05-17')));
    }
}
