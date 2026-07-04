<?php

namespace App\Domain\Reporting\Contracts;

use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;

/**
 * Creative-level companion to {@see AdMetricsSource}: fetches per-ad /
 * per-keyword / per-segment aggregates for a single day. Adapters are tiny
 * fetch-and-map classes — persistence happens in CreativeMetricsIngestor.
 */
interface CreativeMetricsSource
{
    public function platform(): string;

    public function label(): string;

    /** @return iterable<CreativeMetricsSnapshot> */
    public function fetch(int $tenantId, \DateTimeInterface $date): iterable;
}
