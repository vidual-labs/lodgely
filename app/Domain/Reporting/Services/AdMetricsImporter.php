<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Domain\Reporting\Contracts\CreativeMetricsSource;
use App\Models\AdCreativeReport;
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
    public function __construct(
        private readonly MetricsIngestor $ingestor,
        private readonly CreativeMetricsIngestor $creativeIngestor,
    ) {}

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
        // Resolved once and threaded through: activeSourceKeys() is two DB
        // round-trips, and this method used to ask for it three separate times
        // per run (purge + campaign sources + creative sources).
        $enabledKeys = AdPlatformSetting::activeSourceKeys($tenantId);

        $this->purgeStaleMockRows($tenantId, $enabledKeys);

        $sources = $this->sourcesFrom(AppServiceProvider::AD_METRICS_SOURCES, $enabledKeys, $platform);
        $creativeSources = $this->sourcesFrom(AppServiceProvider::CREATIVE_METRICS_SOURCES, $enabledKeys, $platform);

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

            foreach ($creativeSources as $source) {
                try {
                    $result = $this->creativeIngestor->ingest($source->fetch($tenantId, $date), $tenantId);
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
        return $this->sourcesFrom(
            AppServiceProvider::AD_METRICS_SOURCES,
            AdPlatformSetting::activeSourceKeys($tenantId),
            $platform,
        );
    }

    /**
     * Same resolution rules as resolveSources(), for the creative-level
     * adapters — the source keys are shared, so a platform toggled on in
     * Settings → Ad platforms pulls both campaign and creative metrics.
     *
     * @return CreativeMetricsSource[]
     */
    public function resolveCreativeSources(int $tenantId, ?string $platform = null): array
    {
        return $this->sourcesFrom(
            AppServiceProvider::CREATIVE_METRICS_SOURCES,
            AdPlatformSetting::activeSourceKeys($tenantId),
            $platform,
        );
    }

    /**
     * Instantiate the adapters from one registry whose source key is enabled
     * for this tenant, optionally narrowed to a single platform. The campaign
     * and creative registries are keyed identically on purpose, so they share
     * this one resolution rule rather than drifting apart.
     *
     * An empty $enabledKeys means "no opinion" and lets the whole registry run
     * — that is the pre-multi-connector behaviour env-only installs rely on.
     *
     * @param  array<string, class-string>  $registry
     * @param  string[]  $enabledKeys
     * @return list<AdMetricsSource|CreativeMetricsSource>
     */
    private function sourcesFrom(array $registry, array $enabledKeys, ?string $platform): array
    {
        $sources = [];

        foreach ($registry as $key => $class) {
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

        AdCreativeReport::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('platform', $livePlatforms)
            ->where('raw_payload->mock', true)
            ->delete();
    }
}
