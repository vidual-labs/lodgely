<?php

namespace App\Livewire\Reporting;

use App\Domain\Reporting\Services\CampaignRollup;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportingPage extends Component
{
    #[Url]
    public string $platform = 'all';

    #[Url]
    public string $range = '30';

    #[Url(except: '')]
    public string $client = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    private function dateRange(): array
    {
        $days = match ($this->range) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };

        return [
            now()->subDays($days - 1)->toDateString(),
            now()->toDateString(),
        ];
    }

    public function render(CampaignRollup $rollup): View
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        [$from, $to] = $this->dateRange();
        $tenantId = Tenant::DEFAULT_ID;
        $platform = $this->platform !== 'all' ? $this->platform : null;
        $client = $this->client !== '' ? $this->client : null;

        $kpis = $rollup->kpis($tenantId, $from, $to, $platform, $client);
        $campaigns = $rollup->forTenant($tenantId, $from, $to, $platform, $client);
        $bySource = $rollup->bySource($tenantId, $from, $to, $client);
        $series = $rollup->dailySeries($tenantId, $from, $to, $platform, $client);

        $clientOptions = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('client_name')
            ->distinct()
            ->orderBy('client_name')
            ->pluck('client_name')
            ->all();

        return view('livewire.reporting.reporting-page', [
            'kpis' => $kpis,
            'campaigns' => $campaigns,
            'bySource' => $bySource,
            'series' => $series,
            'clientOptions' => $clientOptions,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
