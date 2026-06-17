<?php

namespace App\Domain\Reporting\Services;

use App\Models\AdSpendReport;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignRollup
{
    /**
     * Aggregate ad metrics + lodgely lead counts, grouped by campaign.
     *
     * @return Collection<int, object{platform: string, campaign_id: string, campaign_name: ?string, impressions: int, clicks: int, spend_cents: int, currency: string, platform_leads: int, lodgely_leads: int}>
     */
    public function forTenant(int $tenantId, string $from, string $to, ?string $platform = null): Collection
    {
        $query = AdSpendReport::query()
            ->select([
                'platform',
                'campaign_id',
                DB::raw('MAX(campaign_name) as campaign_name'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(spend_cents) as spend_cents'),
                DB::raw('MAX(currency) as currency'),
                DB::raw('SUM(platform_leads) as platform_leads'),
            ])
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->groupBy(['platform', 'campaign_id'])
            ->orderByDesc(DB::raw('SUM(spend_cents)'));

        if ($platform && $platform !== 'all') {
            $query->where('platform', $platform);
        }

        $adRows = $query->get();

        // Fetch lead counts from lodgely's own data, grouped by campaign_id
        $leadCounts = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('campaign_id')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('campaign_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('campaign_id')
            ->pluck('cnt', 'campaign_id');

        return $adRows->map(function ($row) use ($leadCounts) {
            $row->lodgely_leads = (int) ($leadCounts[$row->campaign_id] ?? 0);

            return $row;
        });
    }

    /**
     * KPI totals for the given period.
     *
     * @return array{total_spend_cents: int, total_clicks: int, total_impressions: int, total_platform_leads: int, total_lodgely_leads: int, has_data: bool}
     */
    public function kpis(int $tenantId, string $from, string $to, ?string $platform = null): array
    {
        $query = AdSpendReport::where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to]);

        if ($platform && $platform !== 'all') {
            $query->where('platform', $platform);
        }

        $agg = $query->selectRaw('
            COALESCE(SUM(spend_cents), 0)    as total_spend_cents,
            COALESCE(SUM(clicks), 0)         as total_clicks,
            COALESCE(SUM(impressions), 0)    as total_impressions,
            COALESCE(SUM(platform_leads), 0) as total_platform_leads,
            COUNT(*) as row_count
        ')->first();

        $lodgelyLeads = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->count();

        return [
            'total_spend_cents' => (int) ($agg->total_spend_cents ?? 0),
            'total_clicks' => (int) ($agg->total_clicks ?? 0),
            'total_impressions' => (int) ($agg->total_impressions ?? 0),
            'total_platform_leads' => (int) ($agg->total_platform_leads ?? 0),
            'total_lodgely_leads' => $lodgelyLeads,
            'has_data' => ((int) ($agg->row_count ?? 0)) > 0,
        ];
    }

    /**
     * Daily time series for the trend charts. One row per calendar day in the
     * range (gaps filled with zeros so the line stays continuous), carrying ad
     * metrics plus lodgely's own lead count for that day.
     *
     * @return Collection<int, object{date: string, spend_cents: int, clicks: int, impressions: int, platform_leads: int, lodgely_leads: int}>
     */
    public function dailySeries(int $tenantId, string $from, string $to, ?string $platform = null): Collection
    {
        $query = AdSpendReport::query()
            ->select([
                'date',
                DB::raw('SUM(spend_cents) as spend_cents'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(platform_leads) as platform_leads'),
            ])
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->groupBy('date');

        if ($platform && $platform !== 'all') {
            $query->where('platform', $platform);
        }

        $adByDay = $query->get()->keyBy(
            fn ($r) => Carbon::parse($r->date)->toDateString()
        );

        $leadsByDay = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $series = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $ad = $adByDay->get($key);

            $series->push((object) [
                'date' => $key,
                'spend_cents' => (int) ($ad->spend_cents ?? 0),
                'clicks' => (int) ($ad->clicks ?? 0),
                'impressions' => (int) ($ad->impressions ?? 0),
                'platform_leads' => (int) ($ad->platform_leads ?? 0),
                'lodgely_leads' => (int) ($leadsByDay[$key] ?? 0),
            ]);

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Leads by source from lodgely's own data (not ad platform data).
     */
    public function bySource(int $tenantId, string $from, string $to): Collection
    {
        return Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('source', DB::raw('COUNT(*) as lead_count'))
            ->groupBy('source')
            ->orderByDesc('lead_count')
            ->get();
    }
}
