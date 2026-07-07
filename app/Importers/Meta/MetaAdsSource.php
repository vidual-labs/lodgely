<?php

namespace App\Importers\Meta;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\DTOs\AdMetricsSnapshot;
use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live Meta (Facebook/Instagram) Marketing API adapter.
 *
 * Pulls campaign-level insights for a single day from
 * https://graph.facebook.com/{version}/act_{account_id}/insights, in line with
 * the reporting compliance intent: aggregate metrics only, no PII, no
 * audience-level data.
 *
 * Requires `LODGELY_META_ADS_ACCESS_TOKEN` and `LODGELY_META_ADS_ACCOUNT_ID`
 * to be set, and the source key `meta` to be listed in
 * `LODGELY_AD_METRICS_SOURCES`.
 */
class MetaAdsSource implements AdMetricsSource
{
    use ResolvesMetaPageFilter;

    /**
     * Meta exposes many "lead-ish" action types. We sum across the ones that
     * are commonly used for lead generation and pixel/CAPI lead events, so
     * `platform_leads` lines up with what an operator would call a lead in
     * Ads Manager. Public because {@see MetaCreativeSource} counts leads the
     * same way at ad/segment level.
     */
    public const LEAD_ACTION_TYPES = [
        'lead',
        'leadgen.other',
        'onsite_conversion.lead_grouped',
        'offsite_conversion.fb_pixel_lead',
    ];

    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta Ads';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        // A tenant can have several Meta connectors (the shared default plus
        // one per client with its own ad account/token) — pull each in turn.
        foreach (AdPlatformSetting::activeConnectorsForPlatform($tenantId, 'meta') as $settings) {
            yield from $this->fetchOne($settings, $date);
        }
    }

    /**
     * Fetch a single connector's campaigns for a day, tagging each snapshot
     * with that connector's client_name. Public so the settings-page "Test
     * connection" button and the connector-management controller can probe
     * one connector directly, regardless of its enabled toggle.
     *
     * @return iterable<AdMetricsSnapshot>
     */
    public function fetchOne(AdPlatformSetting $settings, \DateTimeInterface $date): iterable
    {
        $accountId = trim($settings->effectiveMetaAccountId());
        $token = trim($settings->effectiveMetaAccessToken());
        $apiVer = trim($settings->effectiveMetaApiVersion());
        $currency = $settings->effectiveMetaCurrency();
        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        if ($accountId === '' || $token === '') {
            throw new RuntimeException(
                'Meta Ads source: LODGELY_META_ADS_ACCESS_TOKEN and LODGELY_META_ADS_ACCOUNT_ID must both be set.'
            );
        }

        $accountPath = str_starts_with($accountId, 'act_') ? $accountId : 'act_'.$accountId;
        $dateStr = $date->format('Y-m-d');

        // Meta only exposes the publishing Page per *ad* (via its creative),
        // not per campaign, so a page filter means fetching at ad level and
        // aggregating back up to campaign_id ourselves — the campaign-level
        // insights endpoint has no way to restrict by Page directly.
        $adIds = $this->matchingAdIds($settings, $accountPath, $token, $apiVer, $timeout);
        if ($adIds !== null && $adIds === []) {
            return; // Filter set, but no ad in this account publishes as that Page.
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/insights', $apiVer, $accountPath);

        $params = [
            'level' => $adIds !== null ? 'ad' : 'campaign',
            'fields' => $adIds !== null
                ? 'ad_id,campaign_id,campaign_name,impressions,clicks,spend,reach,actions'
                : 'campaign_id,campaign_name,impressions,clicks,spend,reach,actions',
            'time_range' => json_encode(['since' => $dateStr, 'until' => $dateStr]),
            'time_increment' => 1,
            'access_token' => $token,
            'limit' => 500,
        ];

        $matchingAdIds = $adIds !== null ? array_flip($adIds) : null;
        /** @var array<string, array{campaignId: string, campaignName: ?string, impressions: int, clicks: int, spendCents: int, reach: ?int, leads: int}> $aggregated */
        $aggregated = [];

        do {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Meta Ads insights call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['data'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if ($matchingAdIds !== null) {
                    if (! isset($matchingAdIds[(string) ($row['ad_id'] ?? '')])) {
                        continue;
                    }
                    $this->accumulate($aggregated, $row);

                    continue;
                }

                yield $this->toSnapshot($row, $dateStr, $currency, $settings->client_name);
            }

            $url = $json['paging']['next'] ?? null;
            $params = [];
        } while ($url);

        if ($matchingAdIds !== null) {
            foreach ($aggregated as $row) {
                yield $this->toSnapshotFromAggregate($row, $dateStr, $currency, $settings->client_name);
            }
        }
    }

    /**
     * Roll an ad-level insights row into its campaign's running totals, so a
     * page-filtered fetch still yields one campaign-level snapshot per
     * campaign — matching the shape AdSpendReport expects.
     *
     * @param  array<string, array{campaignId: string, campaignName: ?string, impressions: int, clicks: int, spendCents: int, reach: ?int, leads: int}>  &$aggregated
     */
    private function accumulate(array &$aggregated, array $row): void
    {
        $campaignId = (string) ($row['campaign_id'] ?? '');
        $leads = 0;
        foreach (($row['actions'] ?? []) as $action) {
            if (is_array($action) && in_array($action['action_type'] ?? null, self::LEAD_ACTION_TYPES, true)) {
                $leads += (int) ($action['value'] ?? 0);
            }
        }

        $aggregated[$campaignId] ??= [
            'campaignId' => $campaignId,
            'campaignName' => isset($row['campaign_name']) ? (string) $row['campaign_name'] : null,
            'impressions' => 0,
            'clicks' => 0,
            'spendCents' => 0,
            'reach' => null,
            'leads' => 0,
        ];

        $aggregated[$campaignId]['impressions'] += (int) ($row['impressions'] ?? 0);
        $aggregated[$campaignId]['clicks'] += (int) ($row['clicks'] ?? 0);
        $aggregated[$campaignId]['spendCents'] += (int) round((float) ($row['spend'] ?? 0) * 100);
        $aggregated[$campaignId]['leads'] += $leads;
        // Reach isn't additive across ads (audience overlap) — Meta doesn't
        // return a de-duplicated reach at this granularity either way, so we
        // leave it null for page-filtered rows rather than publish a number
        // that overstates unique audience size.
    }

    /**
     * @param  array{campaignId: string, campaignName: ?string, impressions: int, clicks: int, spendCents: int, reach: ?int, leads: int}  $row
     */
    private function toSnapshotFromAggregate(array $row, string $dateStr, string $currency, ?string $clientName): AdMetricsSnapshot
    {
        return new AdMetricsSnapshot(
            platform: 'meta',
            date: $dateStr,
            campaignId: $row['campaignId'],
            campaignName: $row['campaignName'],
            impressions: $row['impressions'],
            clicks: $row['clicks'],
            spendCents: $row['spendCents'],
            currency: $currency,
            platformLeads: $row['leads'],
            reach: $row['reach'],
            rawPayload: $row,
            clientName: $clientName,
        );
    }

    private function toSnapshot(array $row, string $dateStr, string $currency, ?string $clientName): AdMetricsSnapshot
    {
        $impressions = (int) ($row['impressions'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);
        $reach = isset($row['reach']) ? (int) $row['reach'] : null;

        // Marketing API returns spend as a decimal string in account currency.
        $spendCents = (int) round((float) ($row['spend'] ?? 0) * 100);

        $leads = 0;
        foreach (($row['actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }
            if (in_array($action['action_type'] ?? null, self::LEAD_ACTION_TYPES, true)) {
                $leads += (int) ($action['value'] ?? 0);
            }
        }

        return new AdMetricsSnapshot(
            platform: 'meta',
            date: $dateStr,
            campaignId: (string) ($row['campaign_id'] ?? ''),
            campaignName: isset($row['campaign_name']) ? (string) $row['campaign_name'] : null,
            impressions: $impressions,
            clicks: $clicks,
            spendCents: $spendCents,
            currency: $currency,
            platformLeads: $leads,
            reach: $reach,
            rawPayload: $row,
            clientName: $clientName,
        );
    }
}
