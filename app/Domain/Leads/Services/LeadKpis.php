<?php

namespace App\Domain\Leads\Services;

use App\Domain\Leads\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate counts for the inbox KPI strip.
 *
 * Takes a pre-scoped {@see Lead} query (typically the result of
 * `Lead::query()->visibleTo($user)` plus any filters) and returns the
 * totals + per-source breakdown used by the inbox header.
 */
class LeadKpis
{
    /**
     * @return array{new:int, duplicates:int, incomplete:int, offer_sent:int, total:int, by_source: Collection<int, object>}
     */
    public function compute(Builder $base): array
    {
        $counts = (clone $base)
            ->selectRaw('
                COUNT(*) FILTER (WHERE status = ?) AS new_count,
                COUNT(*) FILTER (WHERE duplicate_flag = true) AS duplicate_count,
                COUNT(*) FILTER (WHERE status = ?) AS incomplete_count,
                COUNT(*) FILTER (WHERE status = ?) AS offer_sent_count,
                COUNT(*) AS total_count
            ', [LeadStatus::New->value, LeadStatus::Incomplete->value, LeadStatus::OfferSent->value])
            ->first();

        $bySource = (clone $base)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        return [
            'new' => (int) ($counts->new_count ?? 0),
            'duplicates' => (int) ($counts->duplicate_count ?? 0),
            'incomplete' => (int) ($counts->incomplete_count ?? 0),
            'offer_sent' => (int) ($counts->offer_sent_count ?? 0),
            'total' => (int) ($counts->total_count ?? 0),
            'by_source' => $bySource,
        ];
    }
}
