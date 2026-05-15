<?php

namespace App\Domain\Reporting\Contracts;

use App\Domain\Reporting\DTOs\AdMetricsSnapshot;

interface AdMetricsSource
{
    public function platform(): string;

    public function label(): string;

    /** @return iterable<AdMetricsSnapshot> */
    public function fetch(int $tenantId, \DateTimeInterface $date): iterable;
}
