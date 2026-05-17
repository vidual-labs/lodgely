<?php

namespace Tests\Feature;

use App\Domain\Reporting\Enums\ReportEmailCadence;
use App\Domain\Reporting\Enums\ReportEmailSendStatus;
use App\Jobs\SendClientReportEmail;
use App\Livewire\Reporting\ReportEmailsPage;
use App\Models\ClientReportEmail;
use App\Models\ClientReportEmailSchedule;
use App\Models\ClientReportEmailSend;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ReportEmailsPageTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name'      => 'Op',
            'email'     => 'op@example.com',
            'password'  => Hash::make('p'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    private function client(string $email = 'c@example.com'): User
    {
        return User::create([
            'name'      => 'Client',
            'email'     => $email,
            'password'  => Hash::make('p'),
            'role'      => 'client',
            'is_active' => true,
        ]);
    }

    public function test_clients_cannot_open_the_report_emails_page(): void
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
        $client = $this->client();

        $this->actingAs($client)
            ->get('/reporting/emails')
            ->assertForbidden();
    }

    public function test_operator_can_create_a_template_with_recipients_and_a_weekly_schedule(): void
    {
        $op     = $this->operator();
        $client = $this->client('alice@example.com');

        Livewire::actingAs($op)
            ->test(ReportEmailsPage::class)
            ->call('openCreate')
            ->set('form.name', 'Weekly snapshot')
            ->set('form.intro_markdown', 'Hello!')
            ->set('form.period_months', 1)
            ->set('form.recipient_ids', [(string) $client->id])
            ->set('form.schedule.is_active', true)
            ->set('form.schedule.cadence', ReportEmailCadence::Weekly->value)
            ->set('form.schedule.day_of_week', 1)
            ->set('form.schedule.hour', 9)
            ->set('form.schedule.timezone', 'UTC')
            ->call('save')
            ->assertHasNoErrors();

        $email = ClientReportEmail::where('name', 'Weekly snapshot')->firstOrFail();
        $this->assertTrue($email->recipients->contains('id', $client->id));

        $schedule = $email->schedules->first();
        $this->assertNotNull($schedule);
        $this->assertSame(ReportEmailCadence::Weekly, $schedule->cadence);
        $this->assertTrue($schedule->is_active);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_send_now_queues_the_job_and_creates_a_send_row(): void
    {
        Bus::fake();
        $op     = $this->operator();
        $client = $this->client();

        $email = ClientReportEmail::create([
            'tenant_id'             => Tenant::DEFAULT_ID,
            'name'                  => 'Ad-hoc',
            'include_kpi_strip'     => false,
            'include_metrics_table' => false,
            'include_ai_summary'    => false,
            'period_months'         => 1,
            'subject_template'      => 'Test',
            'is_active'             => true,
            'created_by'            => $op->id,
        ]);
        $email->recipients()->sync([$client->id]);

        Livewire::actingAs($op)
            ->test(ReportEmailsPage::class)
            ->call('sendNow', $email->id);

        $this->assertSame(1, ClientReportEmailSend::count());
        $send = ClientReportEmailSend::first();
        $this->assertSame(ReportEmailSendStatus::Queued, $send->status);
        $this->assertSame([$client->id], $send->recipient_user_ids);

        Bus::assertDispatched(SendClientReportEmail::class);
    }

    public function test_send_test_overrides_recipients_to_the_actor(): void
    {
        Bus::fake();
        $op     = $this->operator();
        $client = $this->client();

        $email = ClientReportEmail::create([
            'tenant_id'             => Tenant::DEFAULT_ID,
            'name'                  => 'Ad-hoc',
            'include_kpi_strip'     => false,
            'include_metrics_table' => false,
            'include_ai_summary'    => false,
            'period_months'         => 1,
            'subject_template'      => 'Test',
            'is_active'             => true,
            'created_by'            => $op->id,
        ]);
        $email->recipients()->sync([$client->id]);

        Livewire::actingAs($op)
            ->test(ReportEmailsPage::class)
            ->call('sendTest', $email->id);

        $send = ClientReportEmailSend::first();
        $this->assertNotNull($send);
        $this->assertSame([$op->id], $send->recipient_user_ids);
        $this->assertSame($op->id, $send->triggered_by);
    }

    public function test_send_now_without_recipients_is_a_no_op_and_does_not_queue(): void
    {
        Bus::fake();
        $op = $this->operator();

        $email = ClientReportEmail::create([
            'tenant_id'             => Tenant::DEFAULT_ID,
            'name'                  => 'No one',
            'include_kpi_strip'     => false,
            'include_metrics_table' => false,
            'include_ai_summary'    => false,
            'period_months'         => 1,
            'subject_template'      => 'Test',
            'is_active'             => true,
            'created_by'            => $op->id,
        ]);

        Livewire::actingAs($op)
            ->test(ReportEmailsPage::class)
            ->call('sendNow', $email->id);

        $this->assertSame(0, ClientReportEmailSend::count());
        Bus::assertNotDispatched(SendClientReportEmail::class);
    }
}
