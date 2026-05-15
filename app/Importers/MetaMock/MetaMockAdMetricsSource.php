<?php

namespace App\Importers\MetaMock;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\DTOs\AdMetricsSnapshot;

class MetaMockAdMetricsSource implements AdMetricsSource
{
    private const CAMPAIGNS = [
        ['id' => 'META_C_001', 'name' => 'Lodgely – Brand Awareness Q2'],
        ['id' => 'META_C_002', 'name' => 'Lodgely – Lead Gen Form – Properties'],
        ['id' => 'META_C_003', 'name' => 'Lodgely – Retargeting – Website Visitors'],
    ];

    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta Ads (mock)';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        $dateStr = $date->format('Y-m-d');
        $seed    = crc32($dateStr . $tenantId);

        foreach (self::CAMPAIGNS as $i => $campaign) {
            $rng = $seed + $i * 37;

            $impressions = 2000 + abs($rng % 8000);
            $clicks      = (int) ($impressions * (0.02 + (abs($rng >> 4) % 30) / 1000));
            $spendCents  = (int) ($clicks * (45 + abs($rng >> 8) % 120));
            $leads       = (int) ($clicks * (0.03 + (abs($rng >> 12) % 8) / 100));

            yield new AdMetricsSnapshot(
                platform:      'meta',
                date:          $dateStr,
                campaignId:    $campaign['id'],
                campaignName:  $campaign['name'],
                impressions:   $impressions,
                clicks:        $clicks,
                spendCents:    $spendCents,
                currency:      'USD',
                platformLeads: $leads,
                reach:         (int) ($impressions * 0.7),
                rawPayload:    ['mock' => true, 'seed' => $rng],
            );
        }
    }
}
