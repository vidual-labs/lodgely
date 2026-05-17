<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadKpis;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadKpisTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): void
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    public function test_compute_returns_zero_counts_when_no_leads_match(): void
    {
        $this->seedTenant();

        $kpis = (new LeadKpis)->compute(Lead::query());

        $this->assertSame(0, $kpis['new']);
        $this->assertSame(0, $kpis['duplicates']);
        $this->assertSame(0, $kpis['incomplete']);
        $this->assertSame(0, $kpis['total']);
        $this->assertTrue($kpis['by_source']->isEmpty());
    }

    public function test_compute_aggregates_status_duplicate_and_source_counts(): void
    {
        $this->seedTenant();

        Lead::factory()->count(3)->create([
            'status' => LeadStatus::New->value,
            'source' => 'csv',
            'duplicate_flag' => false,
        ]);
        Lead::factory()->count(2)->create([
            'status' => LeadStatus::Incomplete->value,
            'source' => 'email_mock',
            'duplicate_flag' => false,
        ]);
        Lead::factory()->create([
            'status' => LeadStatus::Reviewed->value,
            'source' => 'csv',
            'duplicate_flag' => true,
        ]);

        $kpis = (new LeadKpis)->compute(Lead::query());

        $this->assertSame(3, $kpis['new']);
        $this->assertSame(1, $kpis['duplicates']);
        $this->assertSame(2, $kpis['incomplete']);
        $this->assertSame(6, $kpis['total']);

        $bySource = $kpis['by_source']->pluck('total', 'source')->all();
        $this->assertSame(4, (int) $bySource['csv']);
        $this->assertSame(2, (int) $bySource['email_mock']);
    }

    public function test_compute_respects_the_query_scope_passed_in(): void
    {
        $this->seedTenant();

        Lead::factory()->count(2)->create([
            'client_name' => 'Acme',
            'status' => LeadStatus::New->value,
        ]);
        Lead::factory()->count(5)->create([
            'client_name' => 'Other',
            'status' => LeadStatus::New->value,
        ]);

        $kpis = (new LeadKpis)->compute(Lead::query()->where('client_name', 'Acme'));

        $this->assertSame(2, $kpis['total']);
        $this->assertSame(2, $kpis['new']);
    }
}
