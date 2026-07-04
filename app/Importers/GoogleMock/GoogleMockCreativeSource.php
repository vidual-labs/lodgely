<?php

namespace App\Importers\GoogleMock;

use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;
use App\Models\AdCreativeReport;

/**
 * Deterministic demo data for the creative performance overview: a handful of
 * search keywords and ads across the mock campaigns, seeded the same way as
 * {@see GoogleMockAdMetricsSource} so numbers are stable per day/tenant.
 */
class GoogleMockCreativeSource implements CreativeMetricsSource
{
    private const KEYWORDS = [
        ['id' => '100~9001', 'text' => 'lodgely (exact)', 'campaign_id' => 'GOOG_C_001', 'campaign_name' => 'Search – Branded Keywords'],
        ['id' => '100~9002', 'text' => 'lodgely reviews (phrase)', 'campaign_id' => 'GOOG_C_001', 'campaign_name' => 'Search – Branded Keywords'],
        ['id' => '200~9003', 'text' => 'lead intake software (phrase)', 'campaign_id' => 'GOOG_C_002', 'campaign_name' => 'Search – Competitor Keywords'],
        ['id' => '200~9004', 'text' => 'lead management for agencies (broad)', 'campaign_id' => 'GOOG_C_002', 'campaign_name' => 'Search – Competitor Keywords'],
        ['id' => '200~9005', 'text' => 'crm alternative small business (broad)', 'campaign_id' => 'GOOG_C_002', 'campaign_name' => 'Search – Competitor Keywords'],
    ];

    private const ADS = [
        ['id' => 'GOOG_AD_001', 'name' => 'Branded RSA – "All your leads in one inbox"', 'campaign_id' => 'GOOG_C_001', 'campaign_name' => 'Search – Branded Keywords'],
        ['id' => 'GOOG_AD_002', 'name' => 'Branded RSA – "Sort leads, keep clients happy"', 'campaign_id' => 'GOOG_C_001', 'campaign_name' => 'Search – Branded Keywords'],
        ['id' => 'GOOG_AD_003', 'name' => 'Competitor RSA – "The lean CRM alternative"', 'campaign_id' => 'GOOG_C_002', 'campaign_name' => 'Search – Competitor Keywords'],
    ];

    public function platform(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Ads creatives (mock)';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        $dateStr = $date->format('Y-m-d');
        $seed = crc32($dateStr.$tenantId.'google-creative');

        foreach (self::KEYWORDS as $i => $keyword) {
            $rng = $seed + $i * 43;

            $impressions = 300 + abs($rng % 1800);
            $clicks = (int) ($impressions * (0.04 + (abs($rng >> 4) % 40) / 1000));
            $spendCents = (int) ($clicks * (80 + abs($rng >> 8) % 200));
            $leads = (int) ($clicks * (0.02 + (abs($rng >> 12) % 6) / 100));

            yield new CreativeMetricsSnapshot(
                platform: 'google',
                date: $dateStr,
                dimension: AdCreativeReport::DIMENSION_KEYWORD,
                externalId: $keyword['id'],
                label: $keyword['text'],
                campaignId: $keyword['campaign_id'],
                campaignName: $keyword['campaign_name'],
                impressions: $impressions,
                clicks: $clicks,
                spendCents: $spendCents,
                currency: 'USD',
                platformLeads: $leads,
                rawPayload: ['mock' => true, 'seed' => $rng],
            );
        }

        foreach (self::ADS as $i => $ad) {
            $rng = $seed + 2000 + $i * 59;

            $impressions = 500 + abs($rng % 3000);
            $clicks = (int) ($impressions * (0.035 + (abs($rng >> 4) % 35) / 1000));
            $spendCents = (int) ($clicks * (75 + abs($rng >> 8) % 190));
            $leads = (int) ($clicks * (0.02 + (abs($rng >> 12) % 6) / 100));

            yield new CreativeMetricsSnapshot(
                platform: 'google',
                date: $dateStr,
                dimension: AdCreativeReport::DIMENSION_AD,
                externalId: $ad['id'],
                label: $ad['name'],
                campaignId: $ad['campaign_id'],
                campaignName: $ad['campaign_name'],
                impressions: $impressions,
                clicks: $clicks,
                spendCents: $spendCents,
                currency: 'USD',
                platformLeads: $leads,
                rawPayload: ['mock' => true, 'seed' => $rng],
            );
        }
    }
}
