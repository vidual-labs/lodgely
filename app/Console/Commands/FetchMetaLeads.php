<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FetchesRecurringLeadSources;
use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Meta\MetaLeadsSource;
use App\Models\MetaLeadSource;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FetchMetaLeads extends Command
{
    use FetchesRecurringLeadSources;

    protected $signature = 'lodgely:meta-leads:fetch
        {--source= : Only fetch a specific Meta Lead Ads source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active Meta Lead Ads connections that are due for a refresh.';

    public function handle(ImportRunner $runner, MetaLeadsSource $source): int
    {
        return $this->fetchDueSources($runner, $source);
    }

    protected function sourcesQuery(): Builder
    {
        return MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);
    }

    protected function importMetaKey(): string
    {
        return 'meta_lead_source_id';
    }

    protected function sourceNoun(): string
    {
        return 'Meta Lead Ads connection';
    }
}
