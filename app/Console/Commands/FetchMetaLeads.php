<?php

namespace App\Console\Commands;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Meta\MetaLeadsSource;
use App\Models\Import;
use App\Models\MetaLeadSource;
use App\Models\Tenant;
use Illuminate\Console\Command;

class FetchMetaLeads extends Command
{
    protected $signature = 'lodgely:meta-leads:fetch
        {--source= : Only fetch a specific Meta Lead Ads source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active Meta Lead Ads connections that are due for a refresh.';

    public function handle(ImportRunner $runner, MetaLeadsSource $source): int
    {
        $query = MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);

        if ($id = $this->option('source')) {
            $query->where('id', (int) $id);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('No active Meta Lead Ads connections configured.');

            return self::SUCCESS;
        }

        $ran = 0;

        foreach ($sources as $leadSource) {
            if (! $this->option('force') && ! $leadSource->isDue()) {
                $this->line("  Skipping [{$leadSource->label}] — not yet due.");

                continue;
            }

            $this->line("  Fetching [{$leadSource->label}]…");

            $import = Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => $source->key(),
                'label'     => $leadSource->label.' · '.now()->format('Y-m-d H:i'),
                'meta'      => ['meta_lead_source_id' => $leadSource->id],
            ]);

            try {
                $result = $runner->run($import, $source);
                $leadSource->update(['last_fetched_at' => now()]);

                $this->info("  Done — {$result->rows_imported} imported, {$result->rows_skipped} skipped, {$result->rows_duplicate} duplicates, {$result->rows_invalid} invalid.");
                $ran++;
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        $this->info("Fetched {$ran} Meta Lead Ads connection(s).");

        return self::SUCCESS;
    }
}
