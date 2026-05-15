<?php

namespace App\Domain\Reporting\DTOs;

readonly class AdMetricsSnapshot
{
    public function __construct(
        public string  $platform,
        public string  $date,
        public string  $campaignId,
        public ?string $campaignName,
        public int     $impressions,
        public int     $clicks,
        public int     $spendCents,
        public string  $currency,
        public int     $platformLeads,
        public ?int    $reach = null,
        public ?array  $rawPayload = null,
    ) {}
}
