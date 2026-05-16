<?php

namespace Tests\Feature;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Services\AiSummarizer;
use App\Domain\Ai\Services\ReportSummaryDataAssembler;
use App\Domain\Reporting\Enums\ReportColumn;
use App\Livewire\Reporting\MyReportsPage;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ReportViewSummaryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ClientViewDataBuilder uses Postgres-specific DATE_TRUNC. Tests run
        // against SQLite, so swap the assembler with a deterministic stub
        // that returns an empty data block.
        $this->app->bind(ReportSummaryDataAssembler::class, function () {
            return new class extends ReportSummaryDataAssembler {
                public function __construct() {}
                public function assemble($view, $user, int $tenantId, string $from, string $to): array
                {
                    return [
                        'view' => ['name' => $view->name, 'columns' => []],
                        'period' => ['from' => $from, 'to' => $to],
                        'monthly' => [],
                        'totals'  => [],
                    ];
                }
            };
        });
    }

    private function bootstrap(): array
    {
        config()->set('lodgely.ai.enabled', true);

        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $op = User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['report_view' => true, 'lead_qualification' => false];
        $row->save();

        $view = ClientReportingView::create([
            'tenant_id'  => Tenant::DEFAULT_ID,
            'name'       => 'Monthly performance',
            'columns'    => [ReportColumn::LeadCount->value, ReportColumn::NewLeads->value],
            'created_by' => $op->id,
        ]);

        return [$op, $view];
    }

    public function test_operator_can_queue_a_report_view_summary(): void
    {
        Queue::fake();
        [$op, $view] = $this->bootstrap();

        $summary = app(AiSummarizer::class)->requestReportSummary(
            $view,
            $op,
            now()->subMonths(5)->startOfMonth()->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        $this->assertSame(AiSummaryKind::ReportView, $summary->kind);
        $this->assertSame(ClientReportingView::class, $summary->subject_type);
        $this->assertSame($view->id, $summary->subject_id);
        $this->assertSame(AiSummaryStatus::Pending, $summary->status);
        $this->assertNotNull($summary->period_start);
        $this->assertNotNull($summary->period_end);

        Queue::assertPushed(\App\Jobs\GenerateAiSummary::class);
    }

    public function test_shared_summary_appears_on_my_reports_for_assigned_client(): void
    {
        if (\DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('MyReportsPage renders Postgres-only DATE_TRUNC SQL; integration verified manually.');
        }

        [$op, $view] = $this->bootstrap();

        $client = User::create([
            'name' => 'Brand A', 'email' => 'a@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
        $view->assignedUsers()->sync([$client->id]);

        AiSummary::create([
            'tenant_id'    => Tenant::DEFAULT_ID,
            'kind'         => AiSummaryKind::ReportView->value,
            'subject_type' => ClientReportingView::class,
            'subject_id'   => $view->id,
            'period_start' => now()->subMonths(5)->startOfMonth()->format('Y-m-d'),
            'period_end'   => now()->format('Y-m-d'),
            'prompt'       => 'p',
            'response'     => 'Approved and shared summary',
            'status'       => AiSummaryStatus::Shared->value,
            'requested_by' => $op->id,
            'operator_id'  => $op->id,
            'approved_at'  => now(),
            'shared_at'    => now(),
        ]);

        Livewire::actingAs($client)
            ->test(MyReportsPage::class)
            ->assertSee('Approved and shared summary');
    }
}
