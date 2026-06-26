<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\CampaignRollup;
use App\Livewire\Reporting\ReportingPage;
use App\Models\AdSpendReport;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingClientFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function seedSpend(string $campaignId, int $spendCents, int $clicks = 10): void
    {
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignId.' campaign',
            'impressions' => 1000,
            'clicks' => $clicks,
            'spend_cents' => $spendCents,
            'currency' => 'EUR',
            'reach' => 800,
            'platform_leads' => 5,
        ]);
    }

    private function lead(string $client, ?string $campaignId): void
    {
        Lead::factory()->create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => $client,
            'campaign_id' => $campaignId,
            'created_at' => now()->subDay(),
        ]);
    }

    public function test_client_filter_scopes_lead_counts_and_spend_via_campaigns(): void
    {
        // Acme owns campaign C1, Globex owns C2.
        $this->seedSpend('C1', 100_00);
        $this->seedSpend('C2', 400_00);
        $this->lead('Acme', 'C1');
        $this->lead('Acme', 'C1');
        $this->lead('Globex', 'C2');

        $rollup = new CampaignRollup;
        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        // Unscoped: everything.
        $all = $rollup->kpis(Tenant::DEFAULT_ID, $from, $to);
        $this->assertSame(500_00, $all['total_spend_cents']);
        $this->assertSame(3, $all['total_lodgely_leads']);

        // Scoped to Acme: only C1 spend, only Acme leads.
        $acme = $rollup->kpis(Tenant::DEFAULT_ID, $from, $to, null, 'Acme');
        $this->assertSame(100_00, $acme['total_spend_cents']);
        $this->assertSame(2, $acme['total_lodgely_leads']);

        // By-campaign rollup for Acme returns only C1.
        $campaigns = $rollup->forTenant(Tenant::DEFAULT_ID, $from, $to, null, 'Acme');
        $this->assertCount(1, $campaigns);
        $this->assertSame('C1', $campaigns->first()->campaign_id);
        $this->assertSame(2, $campaigns->first()->lodgely_leads);
    }

    public function test_client_filter_is_case_insensitive(): void
    {
        $this->seedSpend('C1', 100_00);
        $this->lead('Acme', 'C1');

        $rollup = new CampaignRollup;
        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        $kpis = $rollup->kpis(Tenant::DEFAULT_ID, $from, $to, null, 'acme');
        $this->assertSame(100_00, $kpis['total_spend_cents']);
        $this->assertSame(1, $kpis['total_lodgely_leads']);
    }

    public function test_client_with_no_campaign_leads_yields_zero_spend(): void
    {
        // Spend exists, but the client's lead carries no campaign_id.
        $this->seedSpend('C1', 100_00);
        $this->lead('Acme', null);

        $rollup = new CampaignRollup;
        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        $kpis = $rollup->kpis(Tenant::DEFAULT_ID, $from, $to, null, 'Acme');
        $this->assertSame(0, $kpis['total_spend_cents']);
        $this->assertSame(1, $kpis['total_lodgely_leads']);
        $this->assertFalse($kpis['has_data']);
    }

    public function test_reporting_page_renders_client_pills_and_filters_via_url_prop(): void
    {
        $this->seedSpend('C1', 1_000_00);
        $this->seedSpend('C2', 4_000_00);
        $this->lead('Acme', 'C1');
        $this->lead('Globex', 'C2');

        $operator = User::create([
            'name' => 'Op',
            'email' => 'ops@example.com',
            'password' => Hash::make('p'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        Livewire::actingAs($operator)
            ->test(ReportingPage::class)
            ->assertSee('All clients')
            ->assertSee('Acme')
            ->assertSee('Globex')
            ->assertSee('€5,000.00')   // unscoped total spend (C1 + C2)
            ->set('client', 'Acme')
            ->assertSee('€1,000.00')   // scoped to Acme's campaign C1
            ->assertDontSee('€5,000.00');
    }
}
