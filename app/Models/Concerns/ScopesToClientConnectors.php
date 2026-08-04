<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared "which ad rows belong to this client" scope for the two reporting
 * tables that carry a nullable `client_name` — {@see \App\Models\AdSpendReport}
 * (campaign level) and {@see \App\Models\AdCreativeReport} (ad / keyword /
 * segment level).
 *
 * Both tables answer the question the same way, and must keep answering it the
 * same way: a client's rollup and their creative breakdown disagreeing about
 * which rows are theirs is worse than either being wrong on its own.
 */
trait ScopesToClientConnectors
{
    /**
     * Scope to a client's ad rows: rows tagged with a matching client_name
     * (fetched via a connector assigned directly to them), OR untagged rows
     * (the shared/default connector) whose campaign_id appears among that
     * client's leads — the same campaign-attribution heuristic
     * {@see \App\Domain\Reporting\Services\CampaignRollup} has always used.
     *
     * With neither a name nor a campaign id to match on, the scope matches
     * nothing rather than everything: an unresolvable client must never fall
     * through to the whole tenant's spend.
     *
     * @param  string[]  $clientNames
     * @param  list<string>  $campaignIds
     */
    public function scopeForClients(Builder $query, array $clientNames, array $campaignIds = []): Builder
    {
        $lowerNames = array_map(static fn ($n) => mb_strtolower((string) $n), $clientNames);

        return $query->where(function (Builder $q) use ($lowerNames, $campaignIds) {
            if ($lowerNames !== []) {
                $q->whereIn(DB::raw('LOWER(client_name)'), $lowerNames);
            }

            if ($campaignIds !== []) {
                $q->orWhere(function (Builder $q2) use ($campaignIds) {
                    $q2->whereNull('client_name')->whereIn('campaign_id', $campaignIds);
                });
            }

            if ($lowerNames === [] && $campaignIds === []) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
