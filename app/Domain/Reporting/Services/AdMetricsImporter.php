<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Models\AdPlatformSetting;
use App\Providers\AppServiceProvider;

/**
 * Runs the configured ad-metrics source adapters for a window of days and
 * upserts the results via {@see MetricsIngestor}.
 *
 * This is the single place the daily scheduled command and the "Fetch data
 * now" button on the reporting page both go through, so source resolution and
 * the day-by-day fetch loop stay in lockstep. Per-source/day failures are
 * collected instead of aborting the whole run — one mis-configured platform
 * shouldn't stop the others from importing.
 */
class AdMetricsImporter
{
    public function __construct(private readonly MetricsIngestor $ingestor) {}

    /**
     * @return array{inserted:int, updated:int, errors:list<string>, sources:int, days:int}
     */
    public function run(int $tenantId, \DateTimeInterface $anchorDate, int $days = 1, ?string $platform = null): array
    {
        $days = max(1, $days);
        $sources = $this->resolveSources($tenantId, $platform);

        $anchor = \DateTimeImmutable::createFromInterface($anchorDate);

        $inserted = 0;
        $updated = 0;
        $errors = [];

        for ($d = $days - 1; $d >= 0; $d--) {
            $date = $anchor->modify("-{$d} days");

            foreach ($sources as $source) {
                try {
                    $result = $this->ingestor->ingest($source->fetch($tenantId, $date), $tenantId);
                    $inserted += $result['inserted'];
                    $updated += $result['updated'];
                } catch (\Throwable $e) {
                    $errors[] = sprintf('[%s %s] %s', $source->label(), $date->format('Y-m-d'), $e->getMessage());
                }
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'sources' => count($sources),
            'days' => $days,
        ];
    }

    /**
     * Which adapters actually run: the env list (mocks by default) plus any
     * live Meta/Google adapters the operator switched on in Settings → Ad
     * platforms. See {@see AdPlatformSetting::activeSourceKeys()}.
     *
     * @return AdMetricsSource[]
     */
    public function resolveSources(int $tenantId, ?string $platform = null): array
    {
        $enabledKeys = AdPlatformSetting::activeSourceKeys($tenantId);

        $sources = [];

        foreach (AppServiceProvider::AD_METRICS_SOURCES as $key => $class) {
            if ($enabledKeys && ! in_array($key, $enabledKeys, true)) {
                continue;
            }
            $source = app($class);
            if (! $platform || $source->platform() === $platform) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
