<?php

namespace App\Importers\Meta;

use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;
use App\Models\AdCreativeReport;
use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Creative-level Meta Marketing API adapter: two extra insights calls per day
 * on top of {@see MetaAdsSource}'s campaign pull —
 *
 *  - level=ad          → per-ad performance ("top ads"), and
 *  - breakdowns=age,gender at account level → audience segments.
 *
 * Aggregate metrics only, no PII: age bracket + gender is the coarsest
 * demographic breakdown Meta offers and carries no individual-level data.
 */
class MetaCreativeSource implements CreativeMetricsSource
{
    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta Ads creatives';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
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

        $base = [
            'time_range' => json_encode(['since' => $dateStr, 'until' => $dateStr]),
            'time_increment' => 1,
            'access_token' => $token,
            'limit' => 500,
        ];

        // Pass 1: per-ad performance.
        foreach ($this->rows($url, $base + [
            'level' => 'ad',
            'fields' => 'ad_id,ad_name,campaign_id,campaign_name,impressions,clicks,spend,actions',
        ], $timeout) as $row) {
            $adId = (string) ($row['ad_id'] ?? '');
            if ($adId === '') {
                continue;
            }

            yield $this->toSnapshot(
                $row,
                $dateStr,
                $currency,
                dimension: AdCreativeReport::DIMENSION_AD,
                externalId: $adId,
                label: (string) (($row['ad_name'] ?? '') !== '' ? $row['ad_name'] : 'Ad #'.$adId),
            );
        }

        // Pass 2: account-level age × gender segments.
        foreach ($this->rows($url, $base + [
            'level' => 'account',
            'fields' => 'impressions,clicks,spend,actions',
            'breakdowns' => 'age,gender',
        ], $timeout) as $row) {
            $age = (string) ($row['age'] ?? 'unknown');
            $gender = (string) ($row['gender'] ?? 'unknown');

            yield $this->toSnapshot(
                $row,
                $dateStr,
                $currency,
                dimension: AdCreativeReport::DIMENSION_SEGMENT,
                externalId: $age.'|'.$gender,
                label: $age.' · '.$gender,
            );
        }
    }

    /** @return \Generator<array> */
    private function rows(string $url, array $params, int $timeout): \Generator
    {
        do {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Meta Ads creative insights call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['data'] ?? []) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }

            $url = $json['paging']['next'] ?? null;
            $params = [];
        } while ($url);
    }

    private function toSnapshot(
        array $row,
        string $dateStr,
        string $currency,
        string $dimension,
        string $externalId,
        string $label,
    ): CreativeMetricsSnapshot {
        $leads = 0;
        foreach (($row['actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }
            if (in_array($action['action_type'] ?? null, MetaAdsSource::LEAD_ACTION_TYPES, true)) {
                $leads += (int) ($action['value'] ?? 0);
            }
        }

        return new CreativeMetricsSnapshot(
            platform: 'meta',
            date: $dateStr,
            dimension: $dimension,
            externalId: $externalId,
            label: $label,
            campaignId: isset($row['campaign_id']) ? (string) $row['campaign_id'] : null,
            campaignName: isset($row['campaign_name']) ? (string) $row['campaign_name'] : null,
            impressions: (int) ($row['impressions'] ?? 0),
            clicks: (int) ($row['clicks'] ?? 0),
            spendCents: (int) round((float) ($row['spend'] ?? 0) * 100),
            currency: $currency,
            platformLeads: $leads,
            rawPayload: $row,
        );
    }
}
