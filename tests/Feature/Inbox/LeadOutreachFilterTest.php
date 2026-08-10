<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Services\LeadFilter;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Outreach" inbox filter — matches the qualified/called/mailed pills
 * already shown on every lead — added alongside the filter-dropdown picker
 * so a client whose workflow lives in outreach status (not priority) can
 * filter by it.
 */
class LeadOutreachFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function seedOneOfEach(): void
    {
        Lead::factory()->create(['full_name' => 'Untouched']);
        Lead::factory()->create(['full_name' => 'Qualified only', 'qualified_at' => now()]);
        Lead::factory()->create(['full_name' => 'Called only', 'called_at' => now()]);
        Lead::factory()->create(['full_name' => 'Mailed only', 'mailed_at' => now()]);
        Lead::factory()->create(['full_name' => 'Qualified and called', 'qualified_at' => now(), 'called_at' => now()]);
    }

    /** @return list<string> */
    private function namesFor(string $value): array
    {
        return Lead::query()->outreachStatus($value)->orderBy('id')->pluck('full_name')->all();
    }

    public function test_not_contacted_matches_only_leads_with_no_outreach_at_all(): void
    {
        $this->seedOneOfEach();

        $this->assertSame(['Untouched'], $this->namesFor('not_contacted'));
    }

    public function test_qualified_matches_any_lead_with_qualified_at_set(): void
    {
        $this->seedOneOfEach();

        $this->assertSame(['Qualified only', 'Qualified and called'], $this->namesFor('qualified'));
    }

    public function test_called_matches_any_lead_with_called_at_set(): void
    {
        $this->seedOneOfEach();

        $this->assertSame(['Called only', 'Qualified and called'], $this->namesFor('called'));
    }

    public function test_mailed_matches_any_lead_with_mailed_at_set(): void
    {
        $this->seedOneOfEach();

        $this->assertSame(['Mailed only'], $this->namesFor('mailed'));
    }

    public function test_unrecognized_value_is_a_no_op_not_an_error(): void
    {
        $this->seedOneOfEach();

        $this->assertCount(5, $this->namesFor('not-a-real-status'));
    }

    public function test_lead_filter_apply_honours_the_outreach_state_key(): void
    {
        $this->seedOneOfEach();

        $result = (new LeadFilter())->apply(Lead::query(), ['outreach' => 'mailed'])
            ->pluck('full_name')->all();

        $this->assertSame(['Mailed only'], $result);
    }

    public function test_lead_filter_apply_ignores_outreach_when_blank(): void
    {
        $this->seedOneOfEach();

        $result = (new LeadFilter())->apply(Lead::query(), ['outreach' => ''])->count();

        $this->assertSame(5, $result);
    }
}
