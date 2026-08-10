<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Services\LeadFilter;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sorting by priority has to follow {@see LeadPriority::weight()}, not the
 * alphabetical order of the stored enum strings — "high" sorts before "low"
 * before "medium" as text, so ordering on the raw column put High *last* when
 * an operator asked for highest-priority-first.
 */
class LeadPrioritySortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        foreach ([LeadPriority::Medium, LeadPriority::High, LeadPriority::Low] as $priority) {
            Lead::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => 'csv',
                'full_name' => $priority->label(),
                'status'    => 'new',
                'priority'  => $priority,
            ]);
        }
    }

    /** @return list<string> */
    private function sorted(string $sort): array
    {
        return (new LeadFilter())->applySort(Lead::query(), $sort)
            ->pluck('priority')
            ->map(fn ($p) => $p instanceof LeadPriority ? $p->value : (string) $p)
            ->all();
    }

    public function test_priority_desc_puts_high_first(): void
    {
        $this->assertSame(['high', 'medium', 'low'], $this->sorted('priority_desc'));
    }

    public function test_priority_asc_puts_low_first(): void
    {
        $this->assertSame(['low', 'medium', 'high'], $this->sorted('priority_asc'));
    }

    public function test_other_sorts_are_unaffected(): void
    {
        $filter = new LeadFilter();

        $this->assertSame(['created_at', 'desc'], $filter->sortBy('created_desc'));
        $this->assertSame(['full_name', 'asc'], $filter->sortBy('name_asc'));
    }

    /**
     * Priority has three distinct values across the whole table, so without a
     * tiebreaker the order inside a band is whatever the database returns —
     * in Postgres usually heap order, i.e. oldest first, which is how a batch
     * of weeks-old High leads ended up at the very top of the inbox.
     */
    public function test_leads_within_one_priority_band_are_ordered_newest_first(): void
    {
        Lead::query()->delete();

        foreach (['oldest' => 40, 'middle' => 20, 'newest' => 1] as $name => $daysAgo) {
            Lead::create([
                'tenant_id'  => Tenant::DEFAULT_ID,
                'source'     => 'csv',
                'full_name'  => $name,
                'status'     => 'new',
                'priority'   => LeadPriority::High,
                'created_at' => now()->subDays($daysAgo),
            ]);
        }

        $names = (new LeadFilter())->applySort(Lead::query(), 'priority_desc')
            ->pluck('full_name')->all();

        $this->assertSame(['newest', 'middle', 'oldest'], $names);
    }

    /**
     * created_at alone isn't unique enough to paginate on — a CSV or API import
     * writes many rows in the same second — so id carries the final ordering.
     */
    public function test_leads_sharing_a_timestamp_get_a_deterministic_order(): void
    {
        Lead::query()->delete();

        $sameInstant = now()->subWeek();
        $ids = [];
        foreach (['a', 'b', 'c'] as $name) {
            $ids[] = Lead::create([
                'tenant_id'  => Tenant::DEFAULT_ID,
                'source'     => 'csv',
                'full_name'  => $name,
                'status'     => 'new',
                'priority'   => LeadPriority::Medium,
                'created_at' => $sameInstant,
            ])->id;
        }

        $filter = new LeadFilter();

        // Stable across both a low-cardinality sort and the recency sort.
        $this->assertSame(
            array_reverse($ids),
            $filter->applySort(Lead::query(), 'status_desc')->pluck('id')->all(),
        );
        $this->assertSame(
            array_reverse($ids),
            $filter->applySort(Lead::query(), 'created_desc')->pluck('id')->all(),
        );
        $this->assertSame(
            $ids,
            $filter->applySort(Lead::query(), 'created_asc')->pluck('id')->all(),
        );
    }
}
