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
 * became idempotent. Collapses each group of identical rows to its earliest copy
 * (soft-deleting the rest) and backfills the per-row content fingerprint
 * (external_id) on the survivors so the next scheduled fetch recognizes them.
 *
 * Grouping never depends on resolving the spreadsheet: it keys on the lead's
 * stored row content, so a deleted/recreated GoogleSheetSource no longer causes
 * leads to be silently skipped. The importer-compatible external_id is only
 * backfilled where the spreadsheet still resolves (a dead source has no future
 * fetch to re-match anyway).
 */
class DedupeGoogleSheets extends Command
{
    protected $signature = 'lodgely:google-sheets:dedupe
        {--dry-run : Show what would be backfilled / soft-deleted without writing}';

    protected $description = 'Backfill row fingerprints and soft-delete duplicate Google Sheets leads from past full re-fetches.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // import_id => spreadsheet_id, so survivors can get an importer-compatible
        // external_id and identical rows from the same sheet group together.
        $spreadsheetBySource = GoogleSheetSource::query()->pluck('spreadsheet_id', 'id');
        $spreadsheetByImport = [];
        $gsImportIds = [];
        Import::query()
            ->where('source', 'google_sheets')
            ->get(['id', 'meta'])
            ->each(function (Import $import) use (&$spreadsheetByImport, &$gsImportIds, $spreadsheetBySource) {
                $gsImportIds[] = $import->id;
                $sourceId = (int) ($import->meta['sheet_source_id'] ?? 0);
                if ($sourceId > 0 && isset($spreadsheetBySource[$sourceId])) {
                    $spreadsheetByImport[$import->id] = $spreadsheetBySource[$sourceId];
                }
            });

        // Bucket every live Google-Sheets-originated lead by its row content
        // (never skipped). A lead's own `source` may be a value mapped from a
        // sheet column, so identify provenance by import_id rather than source.
        $buckets = [];               // group key => [lead id, ...]
        $backfillCandidates = [];    // lead id => importer fingerprint (external_id was null)

        Lead::query()
            ->where(function ($q) use ($gsImportIds) {
                $q->whereIn('import_id', $gsImportIds)->orWhere('source', 'google_sheets');
            })
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$buckets, &$backfillCandidates, $spreadsheetByImport) {
                foreach ($leads as $lead) {
                    $raw = $lead->raw_payload;
                    $spreadsheetId = $spreadsheetByImport[$lead->import_id] ?? null;

                    if (is_array($raw)) {
                        $content = sha1(json_encode(array_values($raw), JSON_UNESCAPED_UNICODE));
                        $groupKey = $lead->tenant_id."\0".($spreadsheetId ?? '')."\0".$content;

                        // Future idempotency: only backfillable where the sheet
                        // still resolves and the lead has no fingerprint yet.
                        if ($spreadsheetId !== null && ($lead->external_id === null || $lead->external_id === '')) {
                            $backfillCandidates[$lead->id] = GoogleSheetsLeadSource::fingerprint($spreadsheetId, $raw);
                        }
                    } elseif ($lead->email_normalized !== null || $lead->phone_normalized !== null) {
                        // No stored row (very old lead): fall back to contact identity.
                        $groupKey = $lead->tenant_id."\0ep\0".($lead->email_normalized ?? '')."\0".($lead->phone_normalized ?? '');
                    } else {
                        continue; // nothing to group on
                    }

                    $buckets[$groupKey][] = $lead->id;
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
            $backfillCandidates,
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
