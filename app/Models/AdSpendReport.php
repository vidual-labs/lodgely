<?php

namespace App\Models;

use App\Models\Concerns\ScopesToClientConnectors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AdSpendReport extends Model
{
    use ScopesToClientConnectors;

    protected $fillable = [
        'tenant_id', 'client_name', 'platform', 'date', 'campaign_id', 'campaign_name',
        'impressions', 'clicks', 'spend_cents', 'currency', 'reach',
        'platform_leads', 'raw_payload',
    ];

    protected $casts = [
        'date'           => 'date',
        'impressions'    => 'integer',
        'clicks'         => 'integer',
        'spend_cents'    => 'integer',
        'reach'          => 'integer',
        'platform_leads' => 'integer',
        'raw_payload'    => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function spendFormatted(): string
    {
        return sprintf('%s %.2f', strtoupper($this->currency), $this->spend_cents / 100);
    }

    /**
     * The currency to display a tenant's reporting in. Ad accounts report spend
     * in their own currency, so we pick the currency carrying the most spend in
     * the window (handles a tenant running, say, Meta in EUR and Google in USD),
     * and fall back to the configured Meta currency when there's no data yet.
     *
     * @param  list<string>|null  $campaignIds  legacy campaign-attribution scoping (null = no scoping)
     * @param  string[]|null  $clientNames  direct client_name scoping, combined with $campaignIds via scopeForClients()
     */
    public static function dominantCurrency(
        int $tenantId,
        ?string $from = null,
        ?string $to = null,
        ?string $platform = null,
        ?array $campaignIds = null,
        ?array $clientNames = null,
    ): string {
        $query = static::query()->where('tenant_id', $tenantId);

        if ($from !== null && $to !== null) {
            $query->whereBetween('date', [$from, $to]);
        }

        if ($platform && $platform !== 'all') {
            $query->where('platform', $platform);
        }

        if ($clientNames !== null) {
            $query->forClients($clientNames, $campaignIds ?? []);
        } elseif ($campaignIds !== null) {
            $query->whereIn('campaign_id', $campaignIds);
        }

        $currency = $query
            ->select('currency', DB::raw('SUM(spend_cents) as spend_total'))
            ->groupBy('currency')
            ->orderByDesc('spend_total')
            ->value('currency');

        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        return strtoupper(AdPlatformSetting::resolveSafe($tenantId)->effectiveMetaCurrency() ?: 'USD');
    }
}
