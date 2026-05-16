<?php

namespace App\Livewire\Reporting;

use App\Domain\Reporting\Services\ClientViewDataBuilder;
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

    public function render(ClientViewDataBuilder $builder): View
    {
        $user = auth()->user();

        $views = $user->isOperator()
            ? ClientReportingView::with('assignedUsers')
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->orderBy('name')
                ->get()
            : $user->reportingViews()
                ->where('tenant_id', Tenant::DEFAULT_ID)
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

        return view('livewire.reporting.my-reports-page', [
            'views'        => $views,
            'selectedView' => $selectedView,
            'rows'         => $rows,
            'columns'      => $columns,
            'totals'       => $totals,
        ]);
    }
}
