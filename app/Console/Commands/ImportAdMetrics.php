<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\AdMetricsImporter;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ImportAdMetrics extends Command
{
    protected $signature = 'lodgely:import:ad-metrics
                            {--platform= : Only run sources for this platform (meta|google)}
                            {--date= : Specific date to import (Y-m-d). Defaults to yesterday.}
                            {--days=1 : Number of days to import, counting back from --date.}';

    protected $description = 'Fetch ad spend metrics from configured ad platform sources.';

    public function handle(AdMetricsImporter $importer): int
    {
        $tenantId = Tenant::DEFAULT_ID;
        $platform = $this->option('platform');
        $days = max(1, (int) $this->option('days'));

        $anchorDate = $this->option('date')
            ? new \DateTimeImmutable($this->option('date'))
            : new \DateTimeImmutable('yesterday');

        $result = $importer->run($tenantId, $anchorDate, $days, $platform);

        if ($result['sources'] === 0) {
            $this->warn('No ad metrics sources registered or matched the platform filter.');

            return self::SUCCESS;
        }

        foreach ($result['errors'] as $error) {
            $this->warn('  '.$error);
        }

        $this->info("Done. Total inserted={$result['inserted']} updated={$result['updated']}".
            ($result['errors'] ? ' errors='.count($result['errors']) : ''));

        // Fail only when nothing imported and every source errored, so a single
        // flaky platform doesn't mark an otherwise-successful scheduled run red.
        if ($result['errors'] && $result['inserted'] === 0 && $result['updated'] === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
