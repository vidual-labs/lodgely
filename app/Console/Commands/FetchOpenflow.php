<?php

namespace App\Console\Commands;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Import;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Console\Command;

class FetchOpenflow extends Command
{
    protected $signature = 'lodgely:openflow:fetch
        {--source= : Only fetch a specific OpenFlow source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active OpenFlow sources that are due for a refresh.';

    public function handle(ImportRunner $runner, OpenflowLeadSource $source): int
    {
        $query = OpenflowSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);

        if ($id = $this->option('source')) {
            $query->where('id', (int) $id);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('No active OpenFlow sources configured.');

            return self::SUCCESS;
        }

        $ran = 0;

        foreach ($sources as $openflowSource) {
            if (! $this->option('force') && ! $openflowSource->isDue()) {
                $this->line("  Skipping [{$openflowSource->label}] — not yet due.");

                continue;
            }

            $this->line("  Fetching [{$openflowSource->label}]…");

            $import = Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => $source->key(),
                'label'     => $openflowSource->label.' · '.now()->format('Y-m-d H:i'),
                'meta'      => ['openflow_source_id' => $openflowSource->id],
            ]);

            try {
                $result = $runner->run($import, $source);

                $this->info("  Done — {$result->rows_imported} imported, {$result->rows_skipped} skipped, {$result->rows_duplicate} duplicates, {$result->rows_invalid} invalid.");
                $ran++;
            } catch (\Throwable $e) {
                // The import row already carries the error (see ImportRunner).
                $this->error("  Failed: {$e->getMessage()}");
            } finally {
                // Advance the clock on every attempt — success or failure — so a
                // persistently broken source respects its refresh interval
                // instead of being re-fetched on every hourly scheduler tick.
                $openflowSource->update(['last_fetched_at' => now()]);
            }
        }

        $this->info("Fetched {$ran} OpenFlow source(s).");

        return self::SUCCESS;
    }
}
