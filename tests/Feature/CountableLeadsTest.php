<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\CampaignRollup;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporting counts only countable leads — the definition shared with vigilo
 * (ROADMAP → Shared with vigilo, V6): no duplicates (by status or flag), no
 * incomplete leads.
 */
class CountableLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    /** @param array<string, mixed> $attributes */
    private function lead(array $attributes): Lead
    {
        return Lead::factory()->create($attributes + [
            'client_name'    => 'Acme',
            'campaign_id'    => 'C1',
            'source'         => 'csv',
            'status'         => 'new',
            'duplicate_flag' => false,
            'created_at'     => now()->subDay(),
        ]);
    }

    private function seedMixedLeads(): void
    {
        $this->lead([]);
        $this->lead(['status' => 'successful']);
        $this->lead(['status' => 'duplicate', 'duplicate_flag' => true]);
        // A person changed the status, but the detector still knows it is a repeat.
        $this->lead(['status' => 'reviewed', 'duplicate_flag' => true]);
        $this->lead(['status' => 'duplicate']);
        $this->lead(['status' => 'incomplete']);
    }

    public function test_the_scope_excludes_duplicates_and_incomplete_leads(): void
    {
        $this->seedMixedLeads();

        $this->assertSame(6, Lead::query()->count());
        $this->assertSame(['new', 'successful'], Lead::query()->countable()->orderBy('id')->get()->map(fn ($l) => $l->status->value)->all());
    }

    public function test_campaign_rollup_counts_only_countable_leads(): void
    {
        $this->seedMixedLeads();
        $rollup = new CampaignRollup;
        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        $this->assertSame(2, $rollup->kpis(Tenant::DEFAULT_ID, $from, $to)['total_lodgely_leads']);
        $this->assertSame(2, $rollup->kpis(Tenant::DEFAULT_ID, $from, $to, null, 'Acme')['total_lodgely_leads']);
        $this->assertSame(2, $rollup->dailySeries(Tenant::DEFAULT_ID, $from, $to)->sum('lodgely_leads'));
        $this->assertSame(2, (int) $rollup->bySource(Tenant::DEFAULT_ID, $from, $to)->sum('lead_count'));
    }
}
