<?php

namespace App\Livewire\Reporting;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Services\AiSummarizer;
use App\Domain\Reporting\Services\ClientViewDataBuilder;
use App\Models\AdSpendReport;
use App\Models\AiSummary;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MyReportsPage extends Component
{
    #[Url(except: '')]
    public string $view = '';

    #[Url(except: '6')]
    public string $months = '6';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function selectView(string $id): void
    {
        $this->view = $id;
    }

    public function generateAiSummary(AiSummarizer $summarizer): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        if ($this->view === '') {
            return;
        }

        $view = ClientReportingView::where('tenant_id', Tenant::DEFAULT_ID)->find((int) $this->view);
        if (! $view) {
            return;
        }

        $monthCount = max(1, min(24, (int) $this->months));
        $to         = now()->format('Y-m-d');
        $from       = now()->subMonths($monthCount - 1)->startOfMonth()->format('Y-m-d');

        try {
            $summarizer->requestReportSummary($view, auth()->user(), $from, $to);
            $this->dispatch('toast', message: __('AI summary queued. Review it in AI drafts.'), type: 'success');
        } catch (AiDisabledException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(ClientViewDataBuilder $builder): View
    {
        $user = auth()->user();

        $views = $user->isOperator()
            ? ClientReportingView::with('assignedUsers')
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->orderBy('name')
                ->get()
            : $user->reportingViews()
                ->where('client_reporting_views.tenant_id', Tenant::DEFAULT_ID)
                ->where('client_reporting_views.is_live', true)
                ->orderBy('name')
                ->get();

        // Auto-select first view if none selected
        if ($this->view === '' && $views->isNotEmpty()) {
            $this->view = (string) $views->first()->id;
        }

        $selectedView = $views->firstWhere('id', (int) $this->view);

        $rows    = collect();
        $totals  = [];
        $columns = [];

        if ($selectedView) {
            $monthCount = max(1, min(24, (int) $this->months));
            $to         = now()->format('Y-m-d');
            $from       = now()->subMonths($monthCount - 1)->startOfMonth()->format('Y-m-d');

            $columns = $selectedView->columnEnums();
            $rows    = $builder->build($selectedView, $user, Tenant::DEFAULT_ID, $from, $to);
            $totals  = $builder->totals($rows, $columns);
        }

        $aiSummary = null;
        if ($selectedView && config('lodgely.ai.enabled')) {
            $visibleStatuses = $user->isOperator()
                ? [AiSummaryStatus::Approved->value, AiSummaryStatus::Shared->value]
                : [AiSummaryStatus::Shared->value];

            $aiSummary = AiSummary::query()
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->where('kind', AiSummaryKind::ReportView->value)
                ->where('subject_type', ClientReportingView::class)
                ->where('subject_id', $selectedView->id)
                ->whereIn('status', $visibleStatuses)
                ->with('operator')
                ->latest('approved_at')
                ->first();
        }

        return view('livewire.reporting.my-reports-page', [
            'views'        => $views,
            'selectedView' => $selectedView,
            'rows'         => $rows,
            'columns'      => $columns,
            'totals'       => $totals,
            'aiSummary'    => $aiSummary,
            'currency'     => AdSpendReport::dominantCurrency(Tenant::DEFAULT_ID),
        ]);
    }
}
