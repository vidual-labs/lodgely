<?php

namespace App\Console\Commands;

use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Lead;
use App\Models\OpenflowSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for leads imported before OpenFlow external_ids were scoped
 * to their source (install + form). Without this, a client running two
 * OpenFlow sources would have the *next* fetch after upgrading re-create the
 * most recently pulled submissions as duplicates, because the freshly-scoped
 * external_id no longer matches the unscoped one already stored on the lead.
 *
 * Safe to run repeatedly: it recomputes from each lead's stored raw_payload
 * (the original submission, id included) using the same formula the importer
 * now uses going forward, so an already-rescoped lead is simply written back
 * to the same value.
 */
class RescopeOpenflowExternalIds extends Command
{
    protected $signature = 'lodgely:openflow:rescope-ids
        {--dry-run : Show what would be updated without writing}';

    protected $description = 'Backfill source-scoped external_id on OpenFlow leads imported before multi-source dedup scoping existed.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $sourcesById = OpenflowSource::query()->get()->keyBy('id');

        $updates = [];
        $skippedNoSource = 0;

        Lead::query()
            ->where('source', 'openflow')
            ->whereNotNull('import_id')
            ->with('import:id,meta')
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$updates, &$skippedNoSource, $sourcesById) {
                foreach ($leads as $lead) {
                    $submissionId = $lead->raw_payload['id'] ?? null;
                    $openflowSourceId = $lead->import?->meta['openflow_source_id'] ?? null;

                    if ($submissionId === null || $openflowSourceId === null) {
                        continue;
                    }

                    $source = $sourcesById->get((int) $openflowSourceId);
                    if ($source === null) {
                        $skippedNoSource++;

                        continue;
                    }

                    $scoped = OpenflowLeadSource::scopedExternalId($source, (string) $submissionId);
                    if ($lead->external_id !== $scoped) {
                        $updates[$lead->id] = $scoped;
                    }
                }
            });

        $updateCount = count($updates);

        if ($dryRun) {
            $this->info("Would update external_id on {$updateCount} lead(s); {$skippedNoSource} skipped (source deleted).");

            return self::SUCCESS;
        }

        foreach ($updates as $id => $externalId) {
            // Query-builder update so updated_at is not bumped by a backfill.
            DB::table('leads')->where('id', $id)->update(['external_id' => $externalId]);
        }

        $this->info("Updated external_id on {$updateCount} lead(s); {$skippedNoSource} skipped (source deleted).");

        return self::SUCCESS;
    }
}
