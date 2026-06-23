<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadIngestor;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Importers hand the ingestor whatever the upstream source says — a Google
 * Sheet "Status" column might hold "CREATED", a CRM export "OPEN", etc. Those
 * must not reach the enum-cast columns verbatim (that threw a ValueError on
 * save and broke the whole import); they should coerce to a known value.
 */
class LeadIngestorStatusCoercionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    public function test_unknown_status_and_priority_fall_back_to_defaults(): void
    {
        $lead = app(LeadIngestor::class)->ingest([
            'source'   => 'google_sheets',
            'full_name' => 'Alice',
            'email'    => 'alice@example.com',
            'status'   => 'CREATED',   // not a lodgely status
            'priority' => 'URGENT',    // not a lodgely priority
        ]);

        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame(LeadPriority::Medium, $lead->priority);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new', 'priority' => 'medium']);
    }

    public function test_recognized_values_are_accepted_case_insensitively(): void
    {
        $lead = app(LeadIngestor::class)->ingest([
            'source'   => 'csv',
            'full_name' => 'Bob',
            'email'    => 'bob@example.com',
            'status'   => 'Reviewed',
            'priority' => 'HIGH',
        ]);

        $this->assertSame(LeadStatus::Reviewed, $lead->status);
        $this->assertSame(LeadPriority::High, $lead->priority);
    }

    public function test_missing_status_uses_defaults(): void
    {
        $lead = app(LeadIngestor::class)->ingest([
            'source'   => 'manual',
            'full_name' => 'Carol',
            'email'    => 'carol@example.com',
        ]);

        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame(LeadPriority::Medium, $lead->priority);
    }
}
