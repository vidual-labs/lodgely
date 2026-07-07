<?php

namespace App\Importers\Meta;

use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scopes a connector to one brand's ads within a Meta ad account that
 * actually serves several businesses, via the Facebook Page each ad
 * publishes as — matched by the Page's permanent numeric id, never its
 * name (which whoever manages the Page could edit later).
 *
 * Unlike Google's Business Name asset, Meta only exposes the Page per *ad*
 * (via the ad's creative), not per campaign — so a page filter forces the
 * campaign-level adapters to fetch at ad level and aggregate back up to
 * campaign_id themselves.
 */
trait ResolvesMetaPageFilter
{
    /**
     * Every ad id in this account whose creative publishes as this
     * connector's filtered Page, or null when no filter is configured
     * (meaning: don't restrict at all).
     *
     * @return list<string>|null
     */
    protected function matchingAdIds(AdPlatformSetting $settings, string $accountPath, string $token, string $apiVer, int $timeout): ?array
    {
        if (! $settings->hasMetaPageFilter()) {
            return null;
        }

        $pageId = trim((string) $settings->meta_page_id);
        $matching = [];

        foreach ($this->accountAds($accountPath, $token, $apiVer, $timeout) as $ad) {
            $adId = (string) ($ad['id'] ?? '');
            $adPageId = (string) ($ad['creative']['object_story_spec']['page_id'] ?? '');

            if ($adId !== '' && $adPageId === $pageId) {
                $matching[] = $adId;
            }
        }

        return $matching;
    }

    /**
     * Resolve a Page id to its display name, for the settings-page "Resolve"
     * confirmation button — lets an operator verify they typed the right id
     * before saving, without ever matching on the name itself.
     */
    public function resolvePageName(string $pageId, string $token, string $apiVer, int $timeout): ?string
    {
        $response = Http::timeout($timeout)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->get(sprintf('https://graph.facebook.com/%s/%s', $apiVer, $pageId), [
                'fields' => 'name',
                'access_token' => $token,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('name');
    }

    /** @return list<array> */
    private function accountAds(string $accountPath, string $token, string $apiVer, int $timeout): array
    {
        $url = sprintf('https://graph.facebook.com/%s/%s/ads', $apiVer, $accountPath);
        $params = [
            'fields' => 'id,creative{object_story_spec}',
            'limit' => 500,
            'access_token' => $token,
        ];

        $ads = [];

        do {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Meta Ads list call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['data'] ?? []) as $ad) {
                if (is_array($ad)) {
                    $ads[] = $ad;
                }
            }

            $url = $json['paging']['next'] ?? null;
            $params = [];
        } while ($url);

        return $ads;
    }
}
