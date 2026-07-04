<?php

namespace App\Domain\Reporting\DTOs;

/**
 * One day of aggregate metrics for a single ad, keyword or audience segment.
 * The creative-level sibling of {@see AdMetricsSnapshot}.
 */
readonly class CreativeMetricsSnapshot
{
    public function __construct(
        public string $platform,
        public string $date,
        public string $dimension,
        public string $externalId,
        public string $label,
        public ?string $campaignId,
        public ?string $campaignName,
        public int $impressions,
        public int $clicks,
        public int $spendCents,
        public string $currency,
        public int $platformLeads,
        public ?array $rawPayload = null,
    ) {}
}
