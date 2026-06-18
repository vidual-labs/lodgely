<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Models\AdPlatformSetting;
use App\Models\AdSpendReport;
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

        // Before importing live data, drop any leftover demo mock rows for the
        // platforms that are now connected for real. Without this, the
        // deterministic demo campaigns (META_C_*/GOOG_C_*) keep showing up next
        // to the operator's real campaigns with fabricated numbers — they were
        // only suppressed from *future* imports, never deleted.
        $this->purgeStaleMockRows($tenantId, AdPlatformSetting::activeSourceKeys($tenantId));

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
     * platforms — but with the demo mocks dropped once a real platform is
     * connected, so live reporting never mixes in fabricated demo campaigns.
     * See {@see AdPlatformSetting::activeSourceKeys()}.
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

    /**
     * Delete demo mock ad-spend rows for any platform that now has a live
     * adapter active (i.e. its `_mock` key was dropped from the active set).
     * Mock rows are tagged with `raw_payload.mock = true` by the mock adapters,
     * which is the canonical marker we match on.
     *
     * @param  string[]  $enabledKeys
     */
    private function purgeStaleMockRows(int $tenantId, array $enabledKeys): void
    {
        $livePlatforms = [];

        if (in_array('meta', $enabledKeys, true) && ! in_array('meta_mock', $enabledKeys, true)) {
            $livePlatforms[] = 'meta';
        }
        if (in_array('google', $enabledKeys, true) && ! in_array('google_mock', $enabledKeys, true)) {
            $livePlatforms[] = 'google';
        }

        if ($livePlatforms === []) {
            return;
        }

        AdSpendReport::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('platform', $livePlatforms)
            ->where('raw_payload->mock', true)
            ->delete();
    }
}
