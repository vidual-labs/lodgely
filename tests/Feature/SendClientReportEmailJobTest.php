<?php

namespace Tests\Feature;

use App\Domain\Reporting\Enums\ReportEmailSendStatus;
use App\Domain\Reporting\Services\ReportEmailComposer;
use App\Jobs\SendClientReportEmail;
use App\Mail\ClientReportEmailMessage;
use App\Models\ClientReportEmail;
use App\Models\ClientReportEmailSend;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendClientReportEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private function setup_world(): array
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $op = User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
        $client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $email = ClientReportEmail::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'name' => 'Plain',
            'include_kpi_strip' => false,
            'include_metrics_table' => false,
            'include_ai_summary' => false,
            'period_months' => 1,
            'subject_template' => 'Hi {{client}} — your {{period}} report',
            'intro_markdown' => 'Quick note: this is the test body.',
            'is_active' => true,
            'created_by' => $op->id,
        ]);
        $email->recipients()->sync([$client->id]);

        $send = ClientReportEmailSend::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_report_email_id' => $email->id,
            'triggered_by' => $op->id,
            'period_from' => now()->subMonth()->format('Y-m-d'),
            'period_to' => now()->format('Y-m-d'),
            'recipient_user_ids' => [$client->id],
            'status' => ReportEmailSendStatus::Queued->value,
        ]);

        return [$op, $client, $email, $send];
    }

    public function test_job_sends_mail_to_each_recipient_and_marks_the_send_as_sent(): void
    {
        Mail::fake();
        [, $client, , $send] = $this->setup_world();

        (new SendClientReportEmail($send->id))->handle(app(ReportEmailComposer::class));

        Mail::assertQueued(ClientReportEmailMessage::class, function (ClientReportEmailMessage $m) use ($client) {
            return $m->hasTo($client->email);
        });

        $send->refresh();
        $this->assertSame(ReportEmailSendStatus::Sent, $send->status);
        $this->assertNotNull($send->sent_at);
    }

    public function test_job_marks_failed_when_no_active_recipients(): void
    {
        Mail::fake();
        [, $client, , $send] = $this->setup_world();

        $client->forceFill(['is_active' => false])->save();

        (new SendClientReportEmail($send->id))->handle(app(ReportEmailComposer::class));

        $send->refresh();
        $this->assertSame(ReportEmailSendStatus::Failed, $send->status);
        $this->assertStringContainsString('No active recipients', (string) $send->error);
    }

    public function test_intro_html_strips_non_http_link_schemes(): void
    {
        [, $client, $email] = $this->setup_world();
        $email->forceFill(['intro_markdown' => 'Click [here](javascript:alert(1)) or [here](https://lodgely.test).'])->save();

        $data = app(ReportEmailComposer::class)->compose($email, $client);

        $this->assertStringNotContainsString('javascript:', $data['intro_html']);
        $this->assertStringContainsString('href="https://lodgely.test"', $data['intro_html']);
    }
}
