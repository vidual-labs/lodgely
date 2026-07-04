<?php

namespace App\Importers\MetaMock;

use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;
use App\Models\AdCreativeReport;

/**
 * Deterministic demo data for the creative performance overview: a handful of
 * ads across the mock campaigns plus age × gender segments, seeded the same
 * way as {@see MetaMockAdMetricsSource} so numbers are stable per day/tenant.
 */
class MetaMockCreativeSource implements CreativeMetricsSource
{
    private const ADS = [
        ['id' => 'META_AD_001', 'name' => 'Lakeside cabin – carousel', 'campaign_id' => 'META_C_002', 'campaign_name' => 'Lodgely – Lead Gen Form – Properties'],
        ['id' => 'META_AD_002', 'name' => 'Mountain lodge – video tour', 'campaign_id' => 'META_C_002', 'campaign_name' => 'Lodgely – Lead Gen Form – Properties'],
        ['id' => 'META_AD_003', 'name' => 'Spring offer – static image', 'campaign_id' => 'META_C_001', 'campaign_name' => 'Lodgely – Brand Awareness Q2'],
        ['id' => 'META_AD_004', 'name' => 'Come back & book – retargeting', 'campaign_id' => 'META_C_003', 'campaign_name' => 'Lodgely – Retargeting – Website Visitors'],
    ];

    private const SEGMENTS = [
        ['age' => '25-34', 'gender' => 'female'],
        ['age' => '25-34', 'gender' => 'male'],
        ['age' => '35-44', 'gender' => 'female'],
        ['age' => '35-44', 'gender' => 'male'],
        ['age' => '45-54', 'gender' => 'female'],
        ['age' => '45-54', 'gender' => 'male'],
    ];

    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta Ads creatives (mock)';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        $dateStr = $date->format('Y-m-d');
        $seed = crc32($dateStr.$tenantId.'meta-creative');

        foreach (self::ADS as $i => $ad) {
            $rng = $seed + $i * 41;

            $impressions = 800 + abs($rng % 3500);
            $clicks = (int) ($impressions * (0.02 + (abs($rng >> 4) % 30) / 1000));
            $spendCents = (int) ($clicks * (45 + abs($rng >> 8) % 120));
            $leads = (int) ($clicks * (0.03 + (abs($rng >> 12) % 8) / 100));

            yield new CreativeMetricsSnapshot(
                platform: 'meta',
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

        foreach (self::SEGMENTS as $i => $segment) {
            $rng = $seed + 1000 + $i * 61;

            $impressions = 500 + abs($rng % 2500);
            $clicks = (int) ($impressions * (0.015 + (abs($rng >> 4) % 25) / 1000));
            $spendCents = (int) ($clicks * (40 + abs($rng >> 8) % 110));
            $leads = (int) ($clicks * (0.02 + (abs($rng >> 12) % 7) / 100));

            yield new CreativeMetricsSnapshot(
                platform: 'meta',
                date: $dateStr,
                dimension: AdCreativeReport::DIMENSION_SEGMENT,
                externalId: $segment['age'].'|'.$segment['gender'],
                label: $segment['age'].' · '.$segment['gender'],
                campaignId: null,
                campaignName: null,
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
