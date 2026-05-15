<?php

namespace App\Importers\GoogleMock;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\DTOs\AdMetricsSnapshot;

class GoogleMockAdMetricsSource implements AdMetricsSource
{
    private const CAMPAIGNS = [
        ['id' => 'GOOG_C_001', 'name' => 'Search – Branded Keywords'],
        ['id' => 'GOOG_C_002', 'name' => 'Search – Competitor Keywords'],
    ];

    public function platform(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Ads (mock)';
    }

    public function fetch(int $tenantId, \DateTimeInterface $date): iterable
    {
        $dateStr = $date->format('Y-m-d');
        $seed    = crc32($dateStr . $tenantId . 'google');

        foreach (self::CAMPAIGNS as $i => $campaign) {
            $rng = $seed + $i * 53;

            $impressions = 1000 + abs($rng % 5000);
            $clicks      = (int) ($impressions * (0.04 + (abs($rng >> 4) % 40) / 1000));
            $spendCents  = (int) ($clicks * (80 + abs($rng >> 8) % 200));
            $leads       = (int) ($clicks * (0.02 + (abs($rng >> 12) % 6) / 100));

            yield new AdMetricsSnapshot(
                platform:      'google',
                date:          $dateStr,
                campaignId:    $campaign['id'],
                campaignName:  $campaign['name'],
                impressions:   $impressions,
                clicks:        $clicks,
                spendCents:    $spendCents,
                currency:      'USD',
                platformLeads: $leads,
                reach:         null,
                rawPayload:    ['mock' => true, 'seed' => $rng],
            );
        }
    }
}
