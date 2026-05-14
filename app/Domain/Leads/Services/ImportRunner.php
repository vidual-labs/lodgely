<?php

namespace App\Domain\Leads\Services;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;

/**
 * Glues a LeadSource adapter to the ingestor and updates the Import row's
 * counters. Stays intentionally dumb so importers stay testable in isolation.
 */
class ImportRunner
{
    public function __construct(private readonly LeadIngestor $ingestor) {}

    public function run(Import $import, LeadSource $source, ?int $actorId = null): Import
    {
        $import->update(['started_at' => now(), 'source' => $source->key()]);

        $total = 0;
        $imported = 0;
        $duplicate = 0;
        $invalid = 0;

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
            $imported++;
            if ($lead->duplicate_flag) {
                $duplicate++;
            }
        }

        $import->update([
            'rows_total'     => $total,
            'rows_imported'  => $imported,
            'rows_duplicate' => $duplicate,
            'rows_invalid'   => $invalid,
            'finished_at'    => now(),
        ]);

        return $import->fresh();
    }
}
