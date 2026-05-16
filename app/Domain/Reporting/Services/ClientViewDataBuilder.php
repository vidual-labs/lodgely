<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Reporting\Enums\ReportColumn;
use App\Models\AdSpendReport;
use App\Models\ClientReportingView;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientViewDataBuilder
{
    /**
     * Build monthly rows for a single view.
     *
     * Returns a Collection of stdObjects with a "month" key ("YYYY-MM") and
     * one key per selected column. Values are raw (numeric) — use
     * ReportColumn::format() in the view layer for display.
     *
     * @param  string  $from  Y-m-d
     * @param  string  $to    Y-m-d
     */
    public function build(
        ClientReportingView $view,
        User $user,
        int $tenantId,
        string $from,
        string $to,
    ): Collection {
        $columns = $view->columnEnums();

        $needsAd   = collect($columns)->some(fn (ReportColumn $c) => $c->isAdMetric());
        $needsLead = collect($columns)->some(fn (ReportColumn $c) => $c->isLeadMetric());

        $adRows   = $needsAd   ? $this->adMonthlyRows($tenantId, $from, $to)   : collect();
        $leadRows = $needsLead ? $this->leadMonthlyRows($user, $tenantId, $from, $to) : collect();

        return $this->mergeByMonth($this->monthRange($from, $to), $adRows, $leadRows, $columns);
    }

    /**
     * Compute totals across all monthly rows for the KPI summary strip.
     *
     * @param  Collection<int, object>  $rows
     * @param  ReportColumn[]  $columns
     * @return array<string, mixed>
     */
    public function totals(Collection $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $col) {
            $key = $col->value;

            if (in_array($col, [ReportColumn::Ctr, ReportColumn::Cpl], true)) {
                // Re-derive from raw sums rather than averaging per-month values
                $clicks      = $rows->sum('clicks');
                $impressions = $rows->sum('impressions');
                $spend       = $rows->sum('spend_cents');
                $pLeads      = $rows->sum('platform_leads');

                $totals[$key] = match ($col) {
                    ReportColumn::Ctr => $impressions > 0 ? $clicks / $impressions * 100 : null,
                    ReportColumn::Cpl => $pLeads > 0 ? $spend / $pLeads / 100 : null,
                    default           => null,
                };
            } else {
                $totals[$key] = $rows->sum($key);
            }
        }

        return $totals;
    }

    private function adMonthlyRows(int $tenantId, string $from, string $to): Collection
    {
        return AdSpendReport::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("
                TO_CHAR(DATE_TRUNC('month', date), 'YYYY-MM') AS month,
                SUM(impressions)    AS impressions,
                SUM(clicks)         AS clicks,
                SUM(spend_cents)    AS spend_cents,
                SUM(reach)          AS reach,
                SUM(platform_leads) AS platform_leads
            ")
            ->groupByRaw("DATE_TRUNC('month', date)")
            ->orderByRaw("DATE_TRUNC('month', date)")
            ->get()
            ->keyBy('month');
    }

    private function leadMonthlyRows(User $user, int $tenantId, string $from, string $to): Collection
    {
        $newVal      = LeadStatus::New->value;
        $reviewedVal = LeadStatus::Reviewed->value;

        return Lead::query()
            ->visibleTo($user)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw("
                TO_CHAR(DATE_TRUNC('month', created_at), 'YYYY-MM') AS month,
                COUNT(*)                                              AS lead_count,
                COUNT(*) FILTER (WHERE status = ?)                   AS new_leads,
                COUNT(*) FILTER (WHERE status = ?)                   AS reviewed_leads
            ", [$newVal, $reviewedVal])
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy('month');
    }

    /** @param  ReportColumn[]  $columns */
    private function mergeByMonth(
        array $months,
        Collection $adRows,
        Collection $leadRows,
        array $columns,
    ): Collection {
        return collect($months)->map(function (string $month) use ($adRows, $leadRows, $columns) {
            $ad   = $adRows->get($month);
            $lead = $leadRows->get($month);

            $row = ['month' => $month];

            foreach ($columns as $col) {
                $row[$col->value] = match ($col) {
                    ReportColumn::Impressions   => $ad ? (int) $ad->impressions : 0,
                    ReportColumn::Clicks        => $ad ? (int) $ad->clicks : 0,
                    ReportColumn::Spend         => $ad ? (int) $ad->spend_cents : 0,
                    ReportColumn::Reach         => $ad ? (int) $ad->reach : 0,
                    ReportColumn::PlatformLeads => $ad ? (int) $ad->platform_leads : 0,
                    ReportColumn::Ctr           => ($ad && (int) $ad->impressions > 0)
                        ? (int) $ad->clicks / (int) $ad->impressions * 100
                        : null,
                    ReportColumn::Cpl           => ($ad && (int) $ad->platform_leads > 0)
                        ? (int) $ad->spend_cents / (int) $ad->platform_leads / 100
                        : null,
                    ReportColumn::LeadCount     => $lead ? (int) $lead->lead_count : 0,
                    ReportColumn::NewLeads      => $lead ? (int) $lead->new_leads : 0,
                    ReportColumn::ReviewedLeads => $lead ? (int) $lead->reviewed_leads : 0,
                };

                // Also expose raw values needed by totals() for CTR/CPL re-derivation
                if ($col === ReportColumn::Ctr || $col === ReportColumn::Cpl) {
                    $row['clicks']         = $ad ? (int) $ad->clicks : 0;
                    $row['impressions']    = $ad ? (int) $ad->impressions : 0;
                    $row['spend_cents']    = $ad ? (int) $ad->spend_cents : 0;
                    $row['platform_leads'] = $ad ? (int) $ad->platform_leads : 0;
                }
            }

            return (object) $row;
        });
    }

    /** @return string[]  e.g. ["2026-01", "2026-02", ...] */
    private function monthRange(string $from, string $to): array
    {
        $months  = [];
        $current = \Carbon\Carbon::parse($from)->startOfMonth();
        $end     = \Carbon\Carbon::parse($to)->startOfMonth();

        while ($current->lte($end)) {
            $months[] = $current->format('Y-m');
            $current->addMonth();
        }

        return $months;
    }
}
