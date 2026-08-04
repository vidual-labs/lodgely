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
        $filter = new LeadFilter();

        return Lead::query()
            ->orderBy(...$filter->sortBy($sort))
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
}
