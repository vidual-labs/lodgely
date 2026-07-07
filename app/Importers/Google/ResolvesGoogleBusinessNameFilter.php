<?php

namespace App\Importers\Google;

use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scopes a connector to one brand's campaigns within a Google Ads account
 * that actually serves several businesses, via the Business Name asset —
 * matched by the asset's permanent numeric ID, never its text (which
 * whoever manages the account could edit later).
 *
 * Business Name is an asset-group-level asset (Performance Max / Demand
 * Gen), so campaigns using it are resolved via asset_group_asset rather
 * than campaign_asset.
 */
trait ResolvesGoogleBusinessNameFilter
{
    /**
     * Campaign ids using this connector's business-name-asset filter, or
     * null when no filter is configured (meaning: don't restrict at all).
     * An empty (non-null) array means the filter is set but the asset isn't
     * linked to any campaign yet — the caller should then fetch nothing
     * rather than falling back to the whole account.
     *
     * @return list<string>|null
     */
    protected function matchingCampaignIds(
        AdPlatformSetting $settings,
        string $customerId,
        string $accessToken,
        array $headers,
        string $apiVersion,
        int $timeout,
    ): ?array {
        if (! $settings->hasGoogleBusinessNameFilter()) {
            return null;
        }

        $assetId = preg_replace('/\D/', '', (string) $settings->google_business_name_asset_id);
        $assetResourceName = "customers/{$customerId}/assets/{$assetId}";

        $query = 'SELECT asset_group.campaign FROM asset_group_asset '
            ."WHERE asset_group_asset.asset = '{$assetResourceName}' "
            ."AND asset_group_asset.field_type = 'BUSINESS_NAME'";

        $rows = $this->searchGaql($query, $customerId, $accessToken, $headers, $apiVersion, $timeout);

        $campaignIds = [];
        foreach ($rows as $row) {
            $campaignResource = (string) ($row['assetGroup']['campaign'] ?? '');
            if (preg_match('#/campaigns/(\d+)$#', $campaignResource, $m)) {
                $campaignIds[] = $m[1];
            }
        }

        return array_values(array_unique($campaignIds));
    }

    /**
     * Resolve a business-name asset id to its display text, for the
     * settings-page "Resolve" confirmation button — lets an operator verify
     * they typed the right id before saving, without ever matching on the
     * text itself.
     */
    public function resolveBusinessNameAssetName(
        AdPlatformSetting $settings,
        string $assetId,
        int $timeout,
    ): ?string {
        $customerId = (string) preg_replace('/\D/', '', $settings->effectiveGoogleCustomerId());
        $loginCustomerId = (string) preg_replace('/\D/', '', $settings->effectiveGoogleLoginCustomerId());
        $developerToken = trim($settings->effectiveGoogleDeveloperToken());
        $apiVersion = trim($settings->effectiveGoogleApiVersion());

        $accessToken = $this->accessToken($settings, $timeout);
        $headers = ['developer-token' => $developerToken];
        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        $bareId = preg_replace('/\D/', '', $assetId);
        $query = 'SELECT asset.business_name_asset.business_name FROM asset '
            ."WHERE asset.id = {$bareId} AND asset.type = 'BUSINESS_NAME'";

        $rows = $this->searchGaql($query, $customerId, $accessToken, $headers, $apiVersion, $timeout);

        return $rows[0]['asset']['businessNameAsset']['businessName'] ?? null;
    }

    /** @return list<array> */
    private function searchGaql(
        string $query,
        string $customerId,
        string $accessToken,
        array $headers,
        string $apiVersion,
        int $timeout,
    ): array {
        $url = sprintf(
            'https://googleads.googleapis.com/%s/customers/%s/googleAds:search',
            $apiVersion,
            $customerId,
        );

        $results = [];
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
                if (is_array($row)) {
                    $results[] = $row;
                }
            }

            $pageToken = $json['nextPageToken'] ?? null;
        } while (! empty($pageToken));

        return $results;
    }
}
