<?php

namespace App\Livewire\Reporting;

use App\Domain\Reporting\Services\CampaignRollup;
use App\Domain\Reporting\Services\CreativeRollup;
use App\Models\AdCreativeReport;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public function render(CampaignRollup $rollup, CreativeRollup $creativeRollup): View
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
        $creativeSections = $this->creativeSections($creativeRollup, $tenantId, $from, $to, $platform, $client);

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
            'creativeSections' => $creativeSections,
            'clientOptions' => $clientOptions,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * The lean creative performance overview: top ads and audience segments
     * from Meta, top keywords and ads from Google Ads. Sections without data
     * are dropped so the cards only appear once a platform delivers
     * creative-level rows.
     *
     * @return list<array{title: string, heading: string, rows: Collection}>
     */
    private function creativeSections(
        CreativeRollup $rollup,
        int $tenantId,
        string $from,
        string $to,
        ?string $platform,
        ?string $client,
    ): array {
        $definitions = [
            ['meta', AdCreativeReport::DIMENSION_AD, __('Top Meta ads'), __('Ad')],
            ['meta', AdCreativeReport::DIMENSION_SEGMENT, __('Top Meta segments'), __('Segment')],
            ['google', AdCreativeReport::DIMENSION_KEYWORD, __('Top Google keywords'), __('Keyword')],
            ['google', AdCreativeReport::DIMENSION_AD, __('Top Google ads'), __('Ad')],
        ];

        $sections = [];

        foreach ($definitions as [$sectionPlatform, $dimension, $title, $heading]) {
            if ($platform && $platform !== $sectionPlatform) {
                continue;
            }

            $rows = $rollup->top($tenantId, $from, $to, $sectionPlatform, $dimension, $client);
            if ($rows->isEmpty()) {
                continue;
            }

            $sections[] = ['title' => $title, 'heading' => $heading, 'rows' => $rows];
        }

        return $sections;
    }
}
