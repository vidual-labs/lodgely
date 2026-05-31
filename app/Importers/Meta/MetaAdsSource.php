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
    /**
     * Meta exposes many "lead-ish" action types. We sum across the ones that
     * are commonly used for lead generation and pixel/CAPI lead events, so
     * `platform_leads` lines up with what an operator would call a lead in
     * Ads Manager.
     */
    private const LEAD_ACTION_TYPES = [
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
        // Credentials come from the per-tenant settings row (configured in
        // /settings/ad-platforms), falling back to env config when unset.
        $settings = AdPlatformSetting::resolveSafe($tenantId);

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

        $url = sprintf('https://graph.facebook.com/%s/%s/insights', $apiVer, $accountPath);

        $params = [
            'level' => 'campaign',
            'fields' => 'campaign_id,campaign_name,impressions,clicks,spend,reach,actions',
            'time_range' => json_encode(['since' => $dateStr, 'until' => $dateStr]),
            'time_increment' => 1,
            'access_token' => $token,
            'limit' => 500,
        ];

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
                yield $this->toSnapshot($row, $dateStr, $currency);
            }

            $url = $json['paging']['next'] ?? null;
            $params = [];
        } while ($url);
    }

    private function toSnapshot(array $row, string $dateStr, string $currency): AdMetricsSnapshot
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
        );
    }
}
