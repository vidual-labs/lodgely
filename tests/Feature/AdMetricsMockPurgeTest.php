<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\AdMetricsImporter;
use App\Models\AdPlatformSetting;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Once an operator connects a live platform, leftover demo mock rows must be
 * removed — otherwise the fabricated demo campaigns keep showing next to the
 * real ones in reporting (campaign names right, "other numbers made up").
 */
class AdMetricsMockPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function seedRow(string $platform, string $campaignId, ?array $rawPayload): void
    {
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => $platform,
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignId,
            'impressions' => 1000,
            'clicks' => 50,
            'spend_cents' => 12345,
            'currency' => 'EUR',
            'reach' => 800,
            'platform_leads' => 5,
            'raw_payload' => $rawPayload,
        ]);
    }

    public function test_connecting_live_meta_drops_leftover_meta_mock_rows(): void
    {
        // Live Meta connected; Google stays on the demo mock.
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        $setting = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $setting->meta_enabled = true;
        $setting->meta_ad_account_id = '123456';
        $setting->meta_currency = 'EUR';
        $setting->setMetaAccessToken('test-token');
        $setting->save();

        $this->seedRow('meta', 'META_C_001', ['mock' => true]);   // demo → should be purged
        $this->seedRow('meta', 'REAL_42', ['mock' => false]);     // real → must stay
        $this->seedRow('google', 'GOOG_C_001', ['mock' => true]); // google still demo → must stay

        // The live Meta adapter will be invoked; return no rows so the test
        // exercises only the purge, not ingestion.
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        $this->assertSame(0, AdSpendReport::where('campaign_id', 'META_C_001')->count());
        $this->assertSame(1, AdSpendReport::where('campaign_id', 'REAL_42')->count());
        $this->assertSame(1, AdSpendReport::where('campaign_id', 'GOOG_C_001')->count());
    }

    public function test_mock_only_install_keeps_its_mock_rows(): void
    {
        // Nothing connected live → mocks remain the source of truth.
        config()->set('lodgely.reporting.sources', ['meta_mock']);

        $seededDate = now()->subDay()->toDateString();
        $this->seedRow('meta', 'META_C_001', ['mock' => true]);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        // The seeded demo row must survive (the purge only fires once a live
        // platform is connected). The mock importer separately adds a today-
        // dated row, which is expected.
        $this->assertSame(
            1,
            AdSpendReport::where('campaign_id', 'META_C_001')->whereDate('date', $seededDate)->count(),
        );
    }
}
