<?php

namespace App\Livewire\Reporting;

use App\Domain\Leads\Enums\UserRole;
use App\Domain\Reporting\Enums\ReportEmailCadence;
use App\Domain\Reporting\Services\ReportEmailDispatcher;
use App\Domain\Reporting\Services\ReportEmailScheduleRunner;
use App\Models\ClientReportEmail;
use App\Models\ClientReportEmailSchedule;
use App\Models\ClientReportEmailSend;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportEmailsPage extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public bool $confirmingDeleteId = false;
    public ?int $deletingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name'                     => '',
        'client_reporting_view_id' => null,
        'intro_markdown'           => '',
        'include_kpi_strip'        => true,
        'include_metrics_table'    => true,
        'include_ai_summary'       => false,
        'period_months'            => 1,
        'subject_template'         => 'Your {{period}} report',
        'is_active'                => true,
        'recipient_ids'            => [],
        'schedule'                 => [
            'cadence'      => 'one_off',
            'day_of_week'  => 1,
            'day_of_month' => 1,
            'hour'         => 9,
            'timezone'     => 'UTC',
            'is_active'    => false,
            'send_at'      => '',   // ISO local datetime for one-off schedules
        ],
    ];

    public function mount(): void
    {
        $this->guardOperator();
        $this->form['schedule']['timezone'] = config('app.timezone', 'UTC');
    }

    public function openCreate(): void
    {
        $this->guardOperator();
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->guardOperator();
        $email = ClientReportEmail::where('tenant_id', Tenant::DEFAULT_ID)
            ->with(['recipients', 'schedules'])
            ->findOrFail($id);

        $schedule = $email->schedules->first();

        $this->editingId = $email->id;
        $this->form = [
            'name'                     => $email->name,
            'client_reporting_view_id' => $email->client_reporting_view_id,
            'intro_markdown'           => (string) $email->intro_markdown,
            'include_kpi_strip'        => (bool) $email->include_kpi_strip,
            'include_metrics_table'    => (bool) $email->include_metrics_table,
            'include_ai_summary'       => (bool) $email->include_ai_summary,
            'period_months'            => (int) $email->period_months,
            'subject_template'         => $email->subject_template,
            'is_active'                => (bool) $email->is_active,
            'recipient_ids'            => $email->recipients->pluck('id')->map(fn ($v) => (string) $v)->all(),
            'schedule'                 => [
                'cadence'      => $schedule?->cadence?->value ?? 'one_off',
                'day_of_week'  => $schedule?->day_of_week ?? 1,
                'day_of_month' => $schedule?->day_of_month ?? 1,
                'hour'         => $schedule?->hour ?? 9,
                'timezone'     => $schedule?->timezone ?? config('app.timezone', 'UTC'),
                'is_active'    => (bool) ($schedule?->is_active ?? false),
                'send_at'      => $schedule?->next_run_at?->format('Y-m-d\TH:i') ?? '',
            ],
        ];
        $this->showForm = true;
    }

    public function close(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(ReportEmailScheduleRunner $runner): void
    {
        $this->guardOperator();

        $cadenceValues  = array_column(ReportEmailCadence::cases(), 'value');

        $data = $this->validate([
            'form.name'                     => ['required', 'string', 'max:120'],
            'form.client_reporting_view_id' => ['nullable', 'exists:client_reporting_views,id'],
            'form.intro_markdown'           => ['nullable', 'string', 'max:10000'],
            'form.include_kpi_strip'        => ['boolean'],
            'form.include_metrics_table'    => ['boolean'],
            'form.include_ai_summary'       => ['boolean'],
            'form.period_months'            => ['required', 'integer', 'min:1', 'max:24'],
            'form.subject_template'         => ['required', 'string', 'max:200'],
            'form.is_active'                => ['boolean'],
            'form.recipient_ids'            => ['array'],
            'form.recipient_ids.*'          => ['exists:users,id'],
            'form.schedule.cadence'         => ['required', Rule::in($cadenceValues)],
            'form.schedule.day_of_week'     => ['nullable', 'integer', 'min:0', 'max:6'],
            'form.schedule.day_of_month'    => ['nullable', 'integer', 'min:1', 'max:28'],
            'form.schedule.hour'            => ['required', 'integer', 'min:0', 'max:23'],
            'form.schedule.timezone'        => ['required', 'string', 'max:64'],
            'form.schedule.is_active'       => ['boolean'],
            'form.schedule.send_at'         => ['nullable', 'string', 'max:32'],
        ]);

        $f = $data['form'];

        $attrs = [
            'tenant_id'                => Tenant::DEFAULT_ID,
            'name'                     => $f['name'],
            'client_reporting_view_id' => $f['client_reporting_view_id'] ?: null,
            'intro_markdown'           => $f['intro_markdown'] !== '' ? $f['intro_markdown'] : null,
            'include_kpi_strip'        => (bool) $f['include_kpi_strip'],
            'include_metrics_table'    => (bool) $f['include_metrics_table'],
            'include_ai_summary'       => (bool) $f['include_ai_summary'],
            'period_months'            => (int) $f['period_months'],
            'subject_template'         => $f['subject_template'],
            'is_active'                => (bool) $f['is_active'],
            'created_by'               => auth()->id(),
        ];

        if ($this->editingId) {
            $email = ClientReportEmail::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($this->editingId);
            $email->update($attrs);
        } else {
            $email = ClientReportEmail::create($attrs);
        }

        $recipientIds = array_map('intval', $f['recipient_ids'] ?? []);
        $email->recipients()->sync($recipientIds);

        $this->upsertSchedule($email, $f['schedule'], $runner);

        $this->close();
        $this->dispatch('toast', message: __('Report email saved.'), type: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->guardOperator();
        $this->deletingId = $id;
        $this->confirmingDeleteId = true;
    }

    public function delete(): void
    {
        $this->guardOperator();
        if ($this->deletingId) {
            ClientReportEmail::where('tenant_id', Tenant::DEFAULT_ID)
                ->findOrFail($this->deletingId)
                ->delete();
        }
        $this->confirmingDeleteId = false;
        $this->deletingId = null;
        $this->dispatch('toast', message: __('Report email deleted.'), type: 'success');
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = false;
        $this->deletingId = null;
    }

    public function sendNow(int $id, ReportEmailDispatcher $dispatcher): void
    {
        $this->guardOperator();

        $email = ClientReportEmail::where('tenant_id', Tenant::DEFAULT_ID)
            ->with('recipients')
            ->findOrFail($id);

        $send = $dispatcher->dispatchNow($email, auth()->user());

        if ($send === null) {
            $this->dispatch('toast', message: __('No active recipients on this template.'), type: 'error');
            return;
        }

        $this->dispatch('toast', message: __('Report email queued for :n recipient(s).', ['n' => count($send->recipient_user_ids ?? [])]), type: 'success');
    }

    public function sendTest(int $id, ReportEmailDispatcher $dispatcher): void
    {
        $this->guardOperator();

        $email = ClientReportEmail::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id);

        $send = $dispatcher->dispatchNow($email, auth()->user(), [auth()->user()]);

        if ($send === null) {
            $this->dispatch('toast', message: __('Could not queue test send.'), type: 'error');
            return;
        }

        $this->dispatch('toast', message: __('Test email queued — check your inbox.'), type: 'success');
    }

    public function render(): View
    {
        $emails = ClientReportEmail::with(['recipients', 'schedules', 'reportingView'])
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->orderBy('name')
            ->get();

        $clientUsers = User::where('role', UserRole::Client->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $views = ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)
            ->orderBy('name')
            ->get();

        $recentSends = ClientReportEmailSend::with(['email', 'actor'])
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('livewire.reporting.report-emails-page', [
            'emails'      => $emails,
            'clientUsers' => $clientUsers,
            'views'       => $views,
            'cadences'    => ReportEmailCadence::cases(),
            'recentSends' => $recentSends,
        ]);
    }

    /** @param  array<string, mixed>  $sched */
    private function upsertSchedule(ClientReportEmail $email, array $sched, ReportEmailScheduleRunner $runner): void
    {
        $cadence = ReportEmailCadence::from($sched['cadence']);

        $scheduleRow = $email->schedules()->first()
            ?? new ClientReportEmailSchedule(['client_report_email_id' => $email->id]);

        $scheduleRow->client_report_email_id = $email->id;
        $scheduleRow->cadence                = $cadence;
        $scheduleRow->hour                   = (int) $sched['hour'];
        $scheduleRow->timezone               = $sched['timezone'] ?: config('app.timezone', 'UTC');
        $scheduleRow->is_active              = (bool) $sched['is_active'];

        $scheduleRow->day_of_week  = $cadence === ReportEmailCadence::Weekly  ? (int) $sched['day_of_week']  : null;
        $scheduleRow->day_of_month = $cadence === ReportEmailCadence::Monthly ? (int) $sched['day_of_month'] : null;

        if (! $scheduleRow->is_active) {
            $scheduleRow->next_run_at = null;
        } elseif ($cadence === ReportEmailCadence::OneOff) {
            $scheduleRow->next_run_at = $sched['send_at']
                ? \Carbon\Carbon::parse($sched['send_at'], $scheduleRow->timezone)->setTimezone('UTC')
                : now();
        } else {
            // Save first so the runner has the new cadence/day/hour values, then compute.
            $scheduleRow->next_run_at = $scheduleRow->next_run_at ?? now();
            $scheduleRow->save();
            $scheduleRow->next_run_at = $runner->computeNextRunAt($scheduleRow);
        }

        $scheduleRow->save();
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name'                     => '',
            'client_reporting_view_id' => null,
            'intro_markdown'           => '',
            'include_kpi_strip'        => true,
            'include_metrics_table'    => true,
            'include_ai_summary'       => false,
            'period_months'            => 1,
            'subject_template'         => 'Your {{period}} report',
            'is_active'                => true,
            'recipient_ids'            => [],
            'schedule'                 => [
                'cadence'      => 'one_off',
                'day_of_week'  => 1,
                'day_of_month' => 1,
                'hour'         => 9,
                'timezone'     => config('app.timezone', 'UTC'),
                'is_active'    => false,
                'send_at'      => '',
            ],
        ];
        $this->resetErrorBag();
    }
}
