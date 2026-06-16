<?php

namespace App\Console\Commands;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Console\Command;

class FetchGoogleSheets extends Command
{
    protected $signature = 'lodgely:google-sheets:fetch
        {--source= : Only fetch a specific sheet source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active Google Sheet sources that are due for a refresh.';

    public function handle(ImportRunner $runner, GoogleSheetsLeadSource $source): int
    {
        $query = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);

        if ($id = $this->option('source')) {
            $query->where('id', (int) $id);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('No active Google Sheet sources configured.');
            return self::SUCCESS;
        }

        $ran = 0;

        foreach ($sources as $sheetSource) {
            if (! $this->option('force') && ! $sheetSource->isDue()) {
                $this->line("  Skipping [{$sheetSource->label}] — not yet due.");
                continue;
            }

            $this->line("  Fetching [{$sheetSource->label}]…");

            $import = Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => $source->key(),
                'label'     => $sheetSource->label.' · '.now()->format('Y-m-d H:i'),
                'meta'      => ['sheet_source_id' => $sheetSource->id],
            ]);

            try {
                $result = $runner->run($import, $source);
                $sheetSource->update(['last_fetched_at' => now()]);

                $this->info("  Done — {$result->rows_imported} imported, {$result->rows_skipped} skipped, {$result->rows_duplicate} duplicates, {$result->rows_invalid} invalid.");
                $ran++;
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        $this->info("Fetched {$ran} sheet source(s).");

        return self::SUCCESS;
    }
}
