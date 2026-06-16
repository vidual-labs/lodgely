<?php

namespace App\Console\Commands;

use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for the duplicate backlog created before Google Sheets imports
 * became idempotent. Backfills the per-row content fingerprint (external_id) on
 * existing google_sheets leads, then collapses each group of identical rows to
 * its earliest copy, soft-deleting the rest.
 *
 * Backfilling the survivors matters: it lets the next scheduled fetch recognize
 * them (via external_id) and skip instead of re-creating + re-flagging them.
 */
class DedupeGoogleSheets extends Command
{
    protected $signature = 'lodgely:google-sheets:dedupe
        {--dry-run : Show what would be backfilled / soft-deleted without writing}';

    protected $description = 'Backfill row fingerprints and soft-delete duplicate Google Sheets leads from past full re-fetches.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // import_id => spreadsheet_id, so a lead's stored raw_payload can be
        // fingerprinted with the same formula the importer uses.
        $spreadsheetBySource = GoogleSheetSource::query()->pluck('spreadsheet_id', 'id');
        $spreadsheetByImport = [];
        Import::query()
            ->where('source', 'google_sheets')
            ->get(['id', 'meta'])
            ->each(function (Import $import) use (&$spreadsheetByImport, $spreadsheetBySource) {
                $sourceId = (int) ($import->meta['sheet_source_id'] ?? 0);
                if ($sourceId > 0 && isset($spreadsheetBySource[$sourceId])) {
                    $spreadsheetByImport[$import->id] = $spreadsheetBySource[$sourceId];
                }
            });

        // Bucket every live google_sheets lead by (tenant, fingerprint).
        $buckets = [];            // "tenant\0fingerprint" => [lead id, ...]
        $backfillNeeded = [];     // lead id => fingerprint (external_id was null)

        Lead::query()
            ->where('source', 'google_sheets')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$buckets, &$backfillNeeded, $spreadsheetByImport) {
                foreach ($leads as $lead) {
                    $fingerprint = $lead->external_id;

                    if ($fingerprint === null || $fingerprint === '') {
                        $spreadsheetId = $spreadsheetByImport[$lead->import_id] ?? null;
                        if ($spreadsheetId === null || $lead->raw_payload === null) {
                            continue; // can't fingerprint — leave it untouched
                        }
                        $fingerprint = GoogleSheetsLeadSource::fingerprint($spreadsheetId, (array) $lead->raw_payload);
                        $backfillNeeded[$lead->id] = $fingerprint;
                    }

                    $buckets[$lead->tenant_id."\0".$fingerprint][] = $lead->id;
                }
            });

        // Keep the lowest id per bucket, mark the rest for soft-delete.
        $deleteIds = [];
        foreach ($buckets as $ids) {
            if (count($ids) <= 1) {
                continue;
            }
            sort($ids);
            array_shift($ids); // keep the earliest copy
            array_push($deleteIds, ...$ids);
        }

        // Only the survivors need an external_id going forward.
        $deleteSet = array_flip($deleteIds);
        $survivorBackfill = array_filter(
            $backfillNeeded,
            fn ($fingerprint, $id) => ! isset($deleteSet[$id]),
            ARRAY_FILTER_USE_BOTH,
        );

        $backfillCount = count($survivorBackfill);
        $deleteCount = count($deleteIds);

        if ($dryRun) {
            $this->info("Would backfill external_id on {$backfillCount} lead(s) and soft-delete {$deleteCount} duplicate(s).");
            return self::SUCCESS;
        }

        foreach ($survivorBackfill as $id => $fingerprint) {
            // Query-builder update so updated_at is not bumped by a backfill.
            DB::table('leads')->where('id', $id)->update(['external_id' => $fingerprint]);
        }

        foreach (array_chunk($deleteIds, 1000) as $chunk) {
            Lead::query()->whereIn('id', $chunk)->delete(); // soft delete
        }

        $this->info("Backfilled external_id on {$backfillCount} lead(s); soft-deleted {$deleteCount} duplicate(s).");

        return self::SUCCESS;
    }
}
