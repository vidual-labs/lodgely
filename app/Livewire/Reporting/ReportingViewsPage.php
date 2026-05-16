<?php

namespace App\Livewire\Reporting;

use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Services\AiSummarizer;
use App\Domain\Leads\Enums\UserRole;
use App\Domain\Reporting\Enums\ReportColumn;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ReportingViewsPage extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;
    public bool $confirmingDeleteId = false;
    public ?int $deletingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name'     => '',
        'columns'  => [],
        'user_ids' => [],
    ];

    public function mount(): void
    {
        $this->guardOperator();
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
        $view = ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id);

        $this->editingId = $view->id;
        $this->form = [
            'name'     => $view->name,
            'columns'  => $view->columns ?? [],
            'user_ids' => $view->assignedUsers()->pluck('users.id')->map(fn ($v) => (string) $v)->all(),
        ];
        $this->showForm = true;
    }

    public function close(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->guardOperator();

        $data = $this->validate([
            'form.name'      => ['required', 'string', 'max:120'],
            'form.columns'   => ['required', 'array', 'min:1'],
            'form.columns.*' => ['string', Rule::in(array_column(ReportColumn::cases(), 'value'))],
            'form.user_ids'  => ['array'],
            'form.user_ids.*'=> ['exists:users,id'],
        ]);

        $attrs = [
            'tenant_id'  => Tenant::DEFAULT_ID,
            'name'       => $data['form']['name'],
            'columns'    => $data['form']['columns'],
            'created_by' => auth()->id(),
        ];

        if ($this->editingId) {
            $view = ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($this->editingId);
            $view->update($attrs);
        } else {
            $view = ClientReportingView::create($attrs);
        }

        $userIds = array_map('intval', $data['form']['user_ids'] ?? []);
        $view->assignedUsers()->sync($userIds);

        $this->close();
        $this->dispatch('toast', message: __('View saved.'), type: 'success');
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
            ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)
                ->findOrFail($this->deletingId)
                ->delete();
        }
        $this->confirmingDeleteId = false;
        $this->deletingId = null;
        $this->dispatch('toast', message: __('View deleted.'), type: 'success');
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = false;
        $this->deletingId = null;
    }

    public function generateAiSummary(int $viewId, AiSummarizer $summarizer): void
    {
        $this->guardOperator();

        $view = ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($viewId);

        // Use the same 6-month window MyReportsPage defaults to.
        $to   = now()->format('Y-m-d');
        $from = now()->subMonths(5)->startOfMonth()->format('Y-m-d');

        try {
            $summarizer->requestReportSummary($view, auth()->user(), $from, $to);
            $this->dispatch('toast', message: __('AI summary queued. Review it in AI drafts.'), type: 'success');
        } catch (AiDisabledException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $views = ClientReportingView::with(['assignedUsers', 'creator'])
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->orderBy('name')
            ->paginate(15);

        $clientUsers = User::where('role', UserRole::Client->value)
            ->orderBy('name')
            ->get();

        return view('livewire.reporting.reporting-views-page', [
            'views'       => $views,
            'allColumns'  => ReportColumn::cases(),
            'clientUsers' => $clientUsers,
        ]);
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name'     => '',
            'columns'  => [],
            'user_ids' => [],
        ];
        $this->resetErrorBag();
    }
}
