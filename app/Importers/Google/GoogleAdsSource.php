<?php

namespace App\Importers\Google;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\DTOs\AdMetricsSnapshot;
use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Cache;
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
        // Credentials come from the per-tenant settings row (configured in
        // /settings/ad-platforms), falling back to env config when unset.
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
                yield $this->toSnapshot($row, $dateStr);
            }

            $pageToken = $json['nextPageToken'] ?? null;
        } while (! empty($pageToken));
    }

    /**
     * Exchange the refresh token for a fresh access token. Cached for just
     * under the 1 h Google validity window so each scheduled run reuses the
     * same token instead of refreshing on every campaign page.
     */
    private function accessToken(AdPlatformSetting $settings, int $timeout): string
    {
        $clientId = trim($settings->effectiveGoogleClientId());
        $clientSecret = trim($settings->effectiveGoogleClientSecret());
        $refreshToken = trim($settings->effectiveGoogleRefreshToken());

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException(
                'Google Ads source: OAuth client id, client secret and refresh token must all be set. Connect Google Ads in Settings → Ad platforms.'
            );
        }

        $cacheKey = 'lodgely.google_ads.access_token.'.sha1($clientId.'|'.$refreshToken);

        return Cache::remember($cacheKey, 3300, function () use ($clientId, $clientSecret, $refreshToken, $timeout) {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->asForm()
                ->acceptJson()
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Google OAuth token refresh failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 300),
                ));
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google OAuth token refresh returned an unexpected payload shape.');
            }

            return $token;
        });
    }

    private function toSnapshot(array $row, string $dateStr): AdMetricsSnapshot
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
        );
    }
}
