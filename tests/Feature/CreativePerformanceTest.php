<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\AdMetricsImporter;
use App\Livewire\Reporting\ReportingPage;
use App\Models\AdCreativeReport;
use App\Models\AdPlatformSetting;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CreativePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('p'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_import_populates_creative_rows_from_mock_sources(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        // Meta mock: 4 ads + 6 segments; Google mock: 5 keywords + 3 ads.
        $this->assertSame(4, AdCreativeReport::where('platform', 'meta')->where('dimension', 'ad')->count());
        $this->assertSame(6, AdCreativeReport::where('platform', 'meta')->where('dimension', 'segment')->count());
        $this->assertSame(5, AdCreativeReport::where('platform', 'google')->where('dimension', 'keyword')->count());
        $this->assertSame(3, AdCreativeReport::where('platform', 'google')->where('dimension', 'ad')->count());
    }

    public function test_import_is_idempotent_per_day(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        $importer = app(AdMetricsImporter::class);
        $importer->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);
        $countAfterFirst = AdCreativeReport::count();

        $importer->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        $this->assertSame($countAfterFirst, AdCreativeReport::count());
    }

    public function test_debug_idempotency_diagnostics(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        $importer = app(AdMetricsImporter::class);
        $importer->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        $row = AdCreativeReport::where('external_id', 'META_AD_001')->first();

        $debug = sprintf(
            "driver=%s date_raw=%s date_cast=%s client_name=%s tenant_id_col_type=%s\n",
            \DB::connection()->getDriverName(),
            var_export($row?->getRawOriginal('date'), true),
            var_export($row?->date?->toDateString(), true),
            var_export($row?->client_name, true),
            gettype($row?->tenant_id),
        );

        $matchQuery = AdCreativeReport::where('tenant_id', Tenant::DEFAULT_ID)
            ->where('platform', 'meta')
            ->where('date', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->where('dimension', 'ad')
            ->where('external_id', 'META_AD_001')
            ->when(
                true,
                fn ($q) => $q->whereNull('client_name'),
                fn ($q) => $q->where('client_name', 'x'),
            );

        $debug .= 'sql='.$matchQuery->toSql()."\n";
        $debug .= 'bindings='.json_encode($matchQuery->getBindings())."\n";
        $debug .= 'found='.($matchQuery->exists() ? 'YES' : 'NO')."\n";

        // No client_name clause at all — matches the pre-migration query shape.
        $noClientFilter = AdCreativeReport::where('tenant_id', Tenant::DEFAULT_ID)
            ->where('platform', 'meta')
            ->where('date', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->where('dimension', 'ad')
            ->where('external_id', 'META_AD_001');
        $debug .= 'found_no_client_filter='.($noClientFilter->exists() ? 'YES' : 'NO')."\n";

        // Original array-form where(), exactly as the pre-migration code called it.
        $arrayForm = AdCreativeReport::where([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'meta',
            'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'dimension' => 'ad',
            'external_id' => 'META_AD_001',
        ]);
        $debug .= 'found_array_form='.($arrayForm->exists() ? 'YES' : 'NO')."\n";

        $this->fail($debug);
    }

    public function test_reporting_page_shows_creative_performance_sections(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        // Anchor on yesterday: SQLite stores the date as 'Y-m-d 00:00:00', so a
        // today-dated row sorts above the page's 'Y-m-d' upper range bound.
        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('yesterday'), 1);

        Livewire::actingAs($this->user('operator', 'ops@example.com'))
            ->test(ReportingPage::class)
            ->assertOk()
            ->assertSee('Creative performance')
            ->assertSee('Top Meta ads')
            ->assertSee('Top Meta segments')
            ->assertSee('Top Google keywords')
            ->assertSee('Top Google ads')
            ->assertSee('Lakeside cabin – carousel')
            // Top-5 of 6 mock segments: at least two 'female' rows always
            // survive the spend ranking, whichever segment drops out.
            ->assertSee('female');
    }

    public function test_platform_filter_hides_other_platforms_sections(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('yesterday'), 1);

        Livewire::actingAs($this->user('operator', 'ops@example.com'))
            ->test(ReportingPage::class)
            ->set('platform', 'google')
            ->assertSee('Top Google keywords')
            ->assertDontSee('Top Meta ads')
            ->assertDontSee('Top Meta segments');
    }

    public function test_page_renders_without_creative_data(): void
    {
        // Campaign-level spend exists but no creative rows yet (e.g. data
        // imported before this feature shipped) — section simply stays hidden.
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => 'C1',
            'campaign_name' => 'Spring Push',
            'impressions' => 1000,
            'clicks' => 50,
            'spend_cents' => 123456,
            'currency' => 'EUR',
            'platform_leads' => 5,
        ]);

        Livewire::actingAs($this->user('operator', 'ops@example.com'))
            ->test(ReportingPage::class)
            ->assertOk()
            ->assertDontSee('Creative performance');
    }

    public function test_purge_removes_creative_rows_too(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);
        $this->assertGreaterThan(0, AdCreativeReport::count());

        $this->actingAs($this->user('operator', 'ops@example.com'))
            ->post(route('reporting.ad-metrics.purge'))
            ->assertRedirect(route('reporting'));

        $this->assertSame(0, AdCreativeReport::count());
        $this->assertSame(0, AdSpendReport::count());
    }

    public function test_connecting_live_meta_drops_leftover_mock_creative_rows(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock', 'google_mock']);

        $setting = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $setting->meta_enabled = true;
        $setting->meta_ad_account_id = '123456';
        $setting->meta_currency = 'EUR';
        $setting->setMetaAccessToken('test-token');
        $setting->save();

        AdCreativeReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'dimension' => 'ad',
            'external_id' => 'META_AD_001',
            'label' => 'Demo ad',
            'impressions' => 100,
            'clicks' => 10,
            'spend_cents' => 500,
            'currency' => 'EUR',
            'platform_leads' => 1,
            'raw_payload' => ['mock' => true],
        ]);
        AdCreativeReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'google',
            'date' => now()->subDay()->toDateString(),
            'dimension' => 'keyword',
            'external_id' => '100~9001',
            'label' => 'Demo keyword',
            'impressions' => 100,
            'clicks' => 10,
            'spend_cents' => 500,
            'currency' => 'EUR',
            'platform_leads' => 1,
            'raw_payload' => ['mock' => true],
        ]);

        // The live Meta adapters get invoked; return no rows so the test
        // exercises only the purge, not ingestion.
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        // Meta demo creatives purged; Google still on the mock → kept.
        $this->assertSame(0, AdCreativeReport::where('platform', 'meta')->where('external_id', 'META_AD_001')->count());
        $this->assertSame(1, AdCreativeReport::where('platform', 'google')->where('external_id', '100~9001')->count());
    }
}
