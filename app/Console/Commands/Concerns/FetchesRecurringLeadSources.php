<?php

namespace App\Console\Commands\Concerns;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The shared body of the recurring-source fetch commands
 * (`lodgely:google-sheets:fetch`, `lodgely:meta-leads:fetch`,
 * `lodgely:openflow:fetch`).
 *
 * All three do exactly the same thing — walk the tenant's active source rows,
 * skip the ones that aren't due, open an Import, hand it to the runner, and
 * advance the source's scheduling clock — and differ only in which model they
 * read, which key the Import carries, and the noun in their output. Keeping
 * three copies meant a fix to the fetch/error/clock semantics had to be made
 * three times, and in practice wasn't: the clock handling drifted apart
 * between them.
 *
 * A consuming command supplies {@see sourcesQuery()}, {@see importMetaKey()}
 * and {@see sourceNoun()}, and calls {@see fetchDueSources()} from its own
 * typed handle().
 */
trait FetchesRecurringLeadSources
{
    /**
     * Active source rows for the tenant, before the per-row due check.
     *
     * @return Builder<covariant Model>
     */
    abstract protected function sourcesQuery(): Builder;

    /** The Import `meta` key the adapter reads its source id back out of. */
    abstract protected function importMetaKey(): string;

    /** Singular human noun for output lines, e.g. "Google Sheet source". */
    abstract protected function sourceNoun(): string;

    /**
     * Record that a pull actually completed. Only OpenFlow needs this — its
     * adapter walks submissions newer than a high-water mark, which must not
     * advance on a failed attempt (see FetchOpenflow). The others re-read
     * their whole window every time and dedupe on ingest, so a failed attempt
     * costs them nothing.
     */
    protected function markFetchSucceeded(Model $source): void {}

    protected function fetchDueSources(ImportRunner $runner, LeadSource $adapter): int
    {
        $query = $this->sourcesQuery();

        if ($id = $this->option('source')) {
            $query->where('id', (int) $id);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info(sprintf('No active %ss configured.', $this->sourceNoun()));

            return self::SUCCESS;
        }

        $ran = 0;

        foreach ($sources as $source) {
            if (! $this->option('force') && ! $source->isDue()) {
                $this->line("  Skipping [{$source->label}] — not yet due.");

                continue;
            }

            $this->line("  Fetching [{$source->label}]…");

            $import = Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => $adapter->key(),
                'label'     => $source->label.' · '.now()->format('Y-m-d H:i'),
                'meta'      => [$this->importMetaKey() => $source->id],
            ]);

            try {
                $result = $runner->run($import, $adapter);

                $this->markFetchSucceeded($source);

                $this->info("  Done — {$result->rows_imported} imported, {$result->rows_skipped} skipped, {$result->rows_duplicate} duplicates, {$result->rows_invalid} invalid.");
                $ran++;
            } catch (Throwable $e) {
                // The import row already carries the error (see ImportRunner).
                $this->error("  Failed: {$e->getMessage()}");
            } finally {
                // Advance the scheduling clock on every attempt — success or
                // failure — so a persistently broken source respects its
                // refresh interval instead of being re-fetched on every hourly
                // scheduler tick. The recorded error stays visible; the
                // operator can hit "Fetch" to retry once they've fixed it.
                $source->last_fetched_at = now();
                $source->save();
            }
        }

        $this->info(sprintf('Fetched %d %s(s).', $ran, $this->sourceNoun()));

        return self::SUCCESS;
    }
}
