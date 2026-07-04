<?php

namespace App\Domain\Reporting\Services;

use App\Models\AdCreativeReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreativeRollup
{
    public function __construct(private readonly CampaignRollup $campaigns) {}

    /**
     * Top performers for one platform + dimension over a date range, ranked by
     * spend. When $client is given, rows are scoped to the campaigns that the
     * client's leads carry — same convention as {@see CampaignRollup}.
     *
     * @return Collection<int, object{platform: string, dimension: string, external_id: string, label: string, campaign_name: ?string, impressions: int, clicks: int, spend_cents: int, currency: string, platform_leads: int}>
     */
    public function top(
        int $tenantId,
        string $from,
        string $to,
        string $platform,
        string $dimension,
        ?string $client = null,
        int $limit = 5,
    ): Collection {
        $campaignIds = $this->campaigns->campaignIdsForClient($tenantId, $client);

        return AdCreativeReport::query()
            ->select([
                'platform',
                'dimension',
                'external_id',
                DB::raw('MAX(label) as label'),
                DB::raw('MAX(campaign_name) as campaign_name'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(spend_cents) as spend_cents'),
                DB::raw('MAX(currency) as currency'),
                DB::raw('SUM(platform_leads) as platform_leads'),
            ])
            ->where('tenant_id', $tenantId)
            ->where('platform', $platform)
            ->where('dimension', $dimension)
            ->whereBetween('date', [$from, $to])
            ->when($campaignIds !== null, fn ($q) => $q->whereIn('campaign_id', $campaignIds))
            ->groupBy(['platform', 'dimension', 'external_id'])
            ->orderByDesc(DB::raw('SUM(spend_cents)'))
            ->limit($limit)
            ->get();
    }
}
