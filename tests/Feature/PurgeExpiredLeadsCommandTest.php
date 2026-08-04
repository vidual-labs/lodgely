<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredLeadsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    /** Bulk-insert leads directly: the volume matters here, the field values don't. */
    private function seedLeads(int $count, ?string $retentionUntil): void
    {
        $now = now()->toDateTimeString();

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'tenant_id'       => Tenant::DEFAULT_ID,
                'source'          => 'csv',
                'email'           => "purge-{$retentionUntil}-{$i}@example.com",
                'status'          => 'new',
                'priority'        => 'medium',
                'retention_until' => $retentionUntil,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Lead::insert($chunk);
        }
    }

    public function test_it_purges_every_expired_lead_not_just_the_first_chunk(): void
    {
        // Comfortably more than one chunk. Soft-deleting a lead drops it out of
        // the command's own query, so offset-based chunking skipped roughly
        // every second page and left expired leads on disk past their retention
        // date — the one thing this command exists to prevent.
        $this->seedLeads(1200, now()->subDay()->toDateTimeString());
        $this->seedLeads(5, now()->addYear()->toDateTimeString());

        $this->artisan('lodgely:leads:purge')
            ->expectsOutputToContain('Soft-deleted 1200 expired lead(s).')
            ->assertExitCode(0);

        $this->assertSame(0, Lead::query()->where('retention_until', '<', now())->count());
        $this->assertSame(5, Lead::query()->count());
        $this->assertSame(1205, Lead::withTrashed()->count());
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $this->seedLeads(3, now()->subDay()->toDateTimeString());

        $this->artisan('lodgely:leads:purge', ['--dry-run' => true])
            ->expectsOutputToContain('Would soft-delete 3 expired lead(s).')
            ->assertExitCode(0);

        $this->assertSame(3, Lead::query()->count());
    }

    public function test_leads_without_a_retention_date_are_never_purged(): void
    {
        $this->seedLeads(4, null);

        $this->artisan('lodgely:leads:purge')->assertExitCode(0);

        $this->assertSame(4, Lead::query()->count());
    }
}
