<?php

namespace App\Domain\Leads\Services;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;
use Illuminate\Support\Str;
use Throwable;

/**
 * Glues a LeadSource adapter to the ingestor and updates the Import row's
 * counters. Stays intentionally dumb so importers stay testable in isolation.
 */
class ImportRunner
{
    public function __construct(private readonly LeadIngestor $ingestor) {}

    public function run(Import $import, LeadSource $source, ?int $actorId = null): Import
    {
        $import->update(['started_at' => now(), 'source' => $source->key(), 'error' => null]);

        $total = 0;
        $imported = 0;
        $duplicate = 0;
        $invalid = 0;
        $skipped = 0;

        try {
            foreach ($source->pull($import) as $incoming) {
                $total++;
                if (! $incoming instanceof IncomingLead) {
                    $invalid++;
                    continue;
                }

                // Minimum-viable sanity check: require at least one contact channel.
                if (! $incoming->email && ! $incoming->phone && ! $incoming->fullName) {
                    $invalid++;
                    continue;
                }

                $lead = $this->ingestor->ingest($incoming->toIngestPayload(), $import, $import->tenant_id, $actorId);

                // The ingestor returns an existing lead (not freshly created) when a
                // recurring source re-sends a row it has already seen (external_id
                // match). Count that as skipped, not imported.
                if (! $lead->wasRecentlyCreated) {
                    $skipped++;
                    continue;
                }

                $imported++;
                if ($lead->duplicate_flag) {
                    $duplicate++;
                }
            }
        } catch (Throwable $e) {
            // A fetch that throws (bad credentials, expired OAuth token, sheet
            // unreachable, …) must not vanish silently. Persist the partial
            // counters and the reason, mark the run finished, then re-throw so
            // the caller can still log / surface it. Without this the import row
            // stays a misleading 0/0/0/0 and the operator never learns why.
            $import->update([
                'rows_total'     => $total,
                'rows_imported'  => $imported,
                'rows_duplicate' => $duplicate,
                'rows_invalid'   => $invalid,
                'rows_skipped'   => $skipped,
                'error'          => Str::limit($e->getMessage(), 1000, ''),
                'finished_at'    => now(),
            ]);

            throw $e;
        }

        $import->update([
            'rows_total'     => $total,
            'rows_imported'  => $imported,
            'rows_duplicate' => $duplicate,
            'rows_invalid'   => $invalid,
            'rows_skipped'   => $skipped,
            'error'          => null,
            'finished_at'    => now(),
        ]);

        return $import->fresh();
    }
}
