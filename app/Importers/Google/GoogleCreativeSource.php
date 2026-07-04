<?php

namespace App\Importers\Google;

use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;
use App\Models\AdCreativeReport;
use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Creative-level Google Ads REST API adapter: two extra GAQL queries per day
 * on top of {@see GoogleAdsSource}'s campaign pull —
 *
 *  - keyword_view → per-keyword performance ("top keywords"), and
 *  - ad_group_ad  → per-ad performance ("top ads").
 *
 * Aggregate metrics only, no PII.
 */
class GoogleCreativeSource implements CreativeMetricsSource
{
    use RefreshesGoogleAccessToken;

    public function platform(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Ads creatives';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        $settings = AdPlatformSetting::resolveSafe($tenantId);

        $customerId = (string) preg_replace('/\D/', '', $settings->effectiveGoogleCustomerId());
        $loginCustomerId = (string) preg_replace('/\D/', '', $settings->effectiveGoogleLoginCustomerId());
        $developerToken = trim($settings->effectiveGoogleDeveloperToken());
        $apiVersion = trim($settings->effectiveGoogleApiVersion());
        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        if ($customerId === '' || $developerToken === '') {
            throw new RuntimeException(
                'Google Ads source: customer id and developer token must both be set (configure them in Settings → Ad platforms).'
            );
        }

        $accessToken = $this->accessToken($settings, $timeout);
        $dateStr = $date->format('Y-m-d');

        $url = sprintf(
            'https://googleads.googleapis.com/%s/customers/%s/googleAds:search',
            $apiVersion,
            $customerId,
        );

        $headers = ['developer-token' => $developerToken];
        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        // Pass 1: per-keyword performance.
        $keywordQuery = 'SELECT ad_group.id, ad_group_criterion.criterion_id, '
            .'ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, '
            .'campaign.id, campaign.name, customer.currency_code, '
            .'metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions '
            ."FROM keyword_view WHERE segments.date = '".$dateStr."'";

        foreach ($this->rows($url, $headers, $accessToken, $keywordQuery, $timeout) as $row) {
            $criterion = is_array($row['adGroupCriterion'] ?? null) ? $row['adGroupCriterion'] : [];
            $adGroup = is_array($row['adGroup'] ?? null) ? $row['adGroup'] : [];
            $keyword = is_array($criterion['keyword'] ?? null) ? $criterion['keyword'] : [];

            $criterionId = (string) ($criterion['criterionId'] ?? '');
            if ($criterionId === '') {
                continue;
            }

            $text = (string) ($keyword['text'] ?? '');
            $matchType = strtolower((string) ($keyword['matchType'] ?? ''));

            yield $this->toSnapshot(
                $row,
                $dateStr,
                dimension: AdCreativeReport::DIMENSION_KEYWORD,
                // Keyword criterion ids are only unique within an ad group.
                externalId: ($adGroup['id'] ?? '0').'~'.$criterionId,
                label: $text !== ''
                    ? $text.($matchType !== '' && $matchType !== 'unspecified' ? ' ('.$matchType.')' : '')
                    : 'Keyword #'.$criterionId,
            );
        }

        // Pass 2: per-ad performance.
        $adQuery = 'SELECT ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group.name, '
            .'campaign.id, campaign.name, customer.currency_code, '
            .'metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions '
            ."FROM ad_group_ad WHERE segments.date = '".$dateStr."'";

        foreach ($this->rows($url, $headers, $accessToken, $adQuery, $timeout) as $row) {
            $ad = is_array($row['adGroupAd']['ad'] ?? null) ? $row['adGroupAd']['ad'] : [];
            $adGroup = is_array($row['adGroup'] ?? null) ? $row['adGroup'] : [];

            $adId = (string) ($ad['id'] ?? '');
            if ($adId === '') {
                continue;
            }

            // Responsive search ads usually carry no ad name — fall back to
            // the ad group so the row still reads meaningfully.
            $name = (string) ($ad['name'] ?? '');
            if ($name === '') {
                $groupName = (string) ($adGroup['name'] ?? '');
                $name = ($groupName !== '' ? $groupName.' · ' : '').'Ad #'.$adId;
            }

            yield $this->toSnapshot(
                $row,
                $dateStr,
                dimension: AdCreativeReport::DIMENSION_AD,
                externalId: $adId,
                label: $name,
            );
        }
    }

    /** @return \Generator<array> */
    private function rows(string $url, array $headers, string $accessToken, string $query, int $timeout): \Generator
    {
        $pageToken = null;
        do {
            $body = ['query' => $query, 'pageSize' => 1000];
            if ($pageToken !== null && $pageToken !== '') {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->withToken($accessToken)
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->post($url, $body);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Google Ads creative search call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['results'] ?? []) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }

            $pageToken = $json['nextPageToken'] ?? null;
        } while (! empty($pageToken));
    }

    private function toSnapshot(
        array $row,
        string $dateStr,
        string $dimension,
        string $externalId,
        string $label,
    ): CreativeMetricsSnapshot {
        $campaign = is_array($row['campaign'] ?? null) ? $row['campaign'] : [];
        $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : [];
        $customer = is_array($row['customer'] ?? null) ? $row['customer'] : [];

        // 1 currency unit = 1,000,000 micros = 100 cents → micros / 10,000 = cents.
        $spendCents = (int) round(((int) ($metrics['costMicros'] ?? 0)) / 10000);

        return new CreativeMetricsSnapshot(
            platform: 'google',
            date: $dateStr,
            dimension: $dimension,
            externalId: $externalId,
            label: $label,
            campaignId: isset($campaign['id']) ? (string) $campaign['id'] : null,
            campaignName: isset($campaign['name']) ? (string) $campaign['name'] : null,
            impressions: (int) ($metrics['impressions'] ?? 0),
            clicks: (int) ($metrics['clicks'] ?? 0),
            spendCents: $spendCents,
            currency: (string) ($customer['currencyCode'] ?? 'USD'),
            platformLeads: (int) round((float) ($metrics['conversions'] ?? 0)),
            rawPayload: $row,
        );
    }
}
