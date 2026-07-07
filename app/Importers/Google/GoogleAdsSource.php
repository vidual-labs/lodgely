<?php

namespace App\Importers\Google;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\DTOs\AdMetricsSnapshot;
use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live Google Ads REST API adapter.
 *
 * Queries campaign-level metrics for a single day with GAQL via
 * https://googleads.googleapis.com/{version}/customers/{customer_id}/googleAds:search.
 *
 * Requires a developer token plus an OAuth installed-application setup
 * (client id, client secret, refresh token). Access tokens are refreshed on
 * demand and cached for 55 minutes. Aggregate metrics only — no PII.
 */
class GoogleAdsSource implements AdMetricsSource
{
    use RefreshesGoogleAccessToken;

    public function platform(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Ads';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        // A tenant can have several Google Ads connectors (the shared default
        // plus one per client with its own customer id/refresh token) — pull
        // each in turn.
        foreach (AdPlatformSetting::activeConnectorsForPlatform($tenantId, 'google') as $settings) {
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

        $query = 'SELECT campaign.id, campaign.name, customer.currency_code, '
            .'metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions '
            ."FROM campaign WHERE segments.date = '".$dateStr."'";

        $url = sprintf(
            'https://googleads.googleapis.com/%s/customers/%s/googleAds:search',
            $apiVersion,
            $customerId,
        );

        $headers = ['developer-token' => $developerToken];
        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

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
                    'Google Ads search call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['results'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                yield $this->toSnapshot($row, $dateStr, $settings->client_name);
            }

            $pageToken = $json['nextPageToken'] ?? null;
        } while (! empty($pageToken));
    }

    private function toSnapshot(array $row, string $dateStr, ?string $clientName): AdMetricsSnapshot
    {
        $campaign = is_array($row['campaign'] ?? null) ? $row['campaign'] : [];
        $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : [];
        $customer = is_array($row['customer'] ?? null) ? $row['customer'] : [];

        // 1 currency unit = 1,000,000 micros = 100 cents → micros / 10,000 = cents.
        $costMicros = (int) ($metrics['costMicros'] ?? 0);
        $spendCents = (int) round($costMicros / 10000);

        return new AdMetricsSnapshot(
            platform: 'google',
            date: $dateStr,
            campaignId: (string) ($campaign['id'] ?? ''),
            campaignName: isset($campaign['name']) ? (string) $campaign['name'] : null,
            impressions: (int) ($metrics['impressions'] ?? 0),
            clicks: (int) ($metrics['clicks'] ?? 0),
            spendCents: $spendCents,
            currency: (string) ($customer['currencyCode'] ?? 'USD'),
            platformLeads: (int) round((float) ($metrics['conversions'] ?? 0)),
            reach: null,
            rawPayload: $row,
            clientName: $clientName,
        );
    }
}
