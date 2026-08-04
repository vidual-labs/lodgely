<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FetchesRecurringLeadSources;
use App\Domain\Leads\Services\ImportRunner;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FetchGoogleSheets extends Command
{
    use FetchesRecurringLeadSources;

    protected $signature = 'lodgely:google-sheets:fetch
        {--source= : Only fetch a specific sheet source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active Google Sheet sources that are due for a refresh.';

    public function handle(ImportRunner $runner, GoogleSheetsLeadSource $source): int
    {
        return $this->fetchDueSources($runner, $source);
    }

    protected function sourcesQuery(): Builder
    {
        return GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);
    }

    protected function importMetaKey(): string
    {
        return 'sheet_source_id';
    }

    protected function sourceNoun(): string
    {
        return 'Google Sheet source';
    }
}
