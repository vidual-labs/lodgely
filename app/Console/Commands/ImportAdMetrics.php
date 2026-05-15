<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\Services\MetricsIngestor;
use App\Models\Tenant;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;

class ImportAdMetrics extends Command
{
    protected $signature = 'lodgely:import:ad-metrics
                            {--platform= : Only run sources for this platform (meta|google)}
                            {--date= : Specific date to import (Y-m-d). Defaults to yesterday.}
                            {--days=1 : Number of days to import, counting back from --date.}';

    protected $description = 'Fetch ad spend metrics from configured ad platform sources.';

    public function handle(MetricsIngestor $ingestor): int
    {
        $tenantId = Tenant::DEFAULT_ID;
        $platform = $this->option('platform');
        $days     = max(1, (int) $this->option('days'));

        $anchorDate = $this->option('date')
            ? new \DateTimeImmutable($this->option('date'))
            : new \DateTimeImmutable('yesterday');

        $sources = $this->resolveSources($platform);

        if (empty($sources)) {
            $this->warn('No ad metrics sources registered or matched the platform filter.');
            return self::SUCCESS;
        }

        $totalInserted = 0;
        $totalUpdated  = 0;

        for ($d = $days - 1; $d >= 0; $d--) {
            $date = $anchorDate->modify("-{$d} days");

            foreach ($sources as $source) {
                $this->line("→ [{$source->label()}] {$date->format('Y-m-d')}…");
                $snapshots = $source->fetch($tenantId, $date);
                $result    = $ingestor->ingest($snapshots, $tenantId);
                $this->info("  inserted={$result['inserted']} updated={$result['updated']}");
                $totalInserted += $result['inserted'];
                $totalUpdated  += $result['updated'];
            }
        }

        $this->info("Done. Total inserted={$totalInserted} updated={$totalUpdated}");

        return self::SUCCESS;
    }

    /** @return AdMetricsSource[] */
    private function resolveSources(?string $platform): array
    {
        $sources = [];

        foreach (AppServiceProvider::AD_METRICS_SOURCES as $class) {
            $source = app($class);
            if (!$platform || $source->platform() === $platform) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
