<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One day of aggregate performance for a single ad, keyword or audience
 * segment — the fine-grained companion to {@see AdSpendReport}, which stays
 * campaign-level. Aggregate metrics only, no PII.
 */
class AdCreativeReport extends Model
{
    public const DIMENSION_AD = 'ad';

    public const DIMENSION_KEYWORD = 'keyword';

    public const DIMENSION_SEGMENT = 'segment';

    protected $fillable = [
        'tenant_id', 'client_name', 'platform', 'date', 'dimension', 'external_id', 'label',
        'campaign_id', 'campaign_name', 'impressions', 'clicks', 'spend_cents',
        'currency', 'platform_leads', 'raw_payload',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend_cents' => 'integer',
        'platform_leads' => 'integer',
        'raw_payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to a client's creative rows — see
     * {@see AdSpendReport::scopeForClients()} for the matching convention.
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
