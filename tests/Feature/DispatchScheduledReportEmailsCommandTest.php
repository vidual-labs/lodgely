<?php

namespace Tests\Feature;

use App\Domain\Reporting\Enums\ReportEmailCadence;
use App\Jobs\SendClientReportEmail;
use App\Models\ClientReportEmail;
use App\Models\ClientReportEmailSchedule;
use App\Models\ClientReportEmailSend;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchScheduledReportEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function setup_due_schedule(string $cadence = 'weekly'): ClientReportEmailSchedule
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $op = User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $email = ClientReportEmail::create([
            'tenant_id'             => Tenant::DEFAULT_ID,
            'name'                  => 'Test',
            'include_kpi_strip'     => false,
            'include_metrics_table' => false,
            'include_ai_summary'    => false,
            'period_months'         => 1,
            'subject_template'      => 'Test',
            'is_active'             => true,
            'created_by'            => $op->id,
        ]);
        $email->recipients()->sync([$client->id]);

        return ClientReportEmailSchedule::create([
            'client_report_email_id' => $email->id,
            'cadence'                => $cadence,
            'day_of_week'            => 1,
            'day_of_month'           => 1,
            'hour'                   => 9,
            'timezone'               => 'UTC',
            'next_run_at'            => now()->subHour(),
            'is_active'              => true,
        ]);
    }

    public function test_command_queues_send_for_due_schedule_and_advances_next_run_at(): void
    {
        Bus::fake();
        $schedule = $this->setup_due_schedule('weekly');
        $originalRun = $schedule->next_run_at?->copy();

        $this->artisan('lodgely:report-emails:dispatch')
            ->expectsOutputToContain('Dispatched 1')
            ->assertSuccessful();

        $this->assertSame(1, ClientReportEmailSend::count());
        Bus::assertDispatched(SendClientReportEmail::class);

        $schedule->refresh();
        $this->assertTrue($schedule->next_run_at?->greaterThan($originalRun));
        $this->assertTrue($schedule->is_active);
        $this->assertNotNull($schedule->last_run_at);
    }

    public function test_one_off_schedule_deactivates_after_dispatch(): void
    {
        Bus::fake();
        $schedule = $this->setup_due_schedule('one_off');

        $this->artisan('lodgely:report-emails:dispatch')->assertSuccessful();

        $schedule->refresh();
        $this->assertFalse($schedule->is_active);
        $this->assertNull($schedule->next_run_at);
    }

    public function test_dry_run_does_not_create_send_rows(): void
    {
        Bus::fake();
        $this->setup_due_schedule('weekly');

        $this->artisan('lodgely:report-emails:dispatch', ['--dry-run' => true])
            ->expectsOutputToContain('Would dispatch')
            ->assertSuccessful();

        $this->assertSame(0, ClientReportEmailSend::count());
        Bus::assertNotDispatched(SendClientReportEmail::class);
    }

    public function test_inactive_template_is_skipped_even_when_schedule_is_due(): void
    {
        Bus::fake();
        $schedule = $this->setup_due_schedule('weekly');

        // Operator deactivated the template after creating the schedule.
        $schedule->email->forceFill(['is_active' => false])->save();

        $this->artisan('lodgely:report-emails:dispatch')->assertSuccessful();

        $this->assertSame(0, ClientReportEmailSend::count());
        Bus::assertNotDispatched(SendClientReportEmail::class);
    }
}
