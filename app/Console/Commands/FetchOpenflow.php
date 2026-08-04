<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FetchesRecurringLeadSources;
use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FetchOpenflow extends Command
{
    use FetchesRecurringLeadSources;

    protected $signature = 'lodgely:openflow:fetch
        {--source= : Only fetch a specific OpenFlow source ID}
        {--force  : Fetch even if not yet due}';

    protected $description = 'Pull leads from all active OpenFlow sources that are due for a refresh.';

    public function handle(ImportRunner $runner, OpenflowLeadSource $source): int
    {
        return $this->fetchDueSources($runner, $source);
    }

    protected function sourcesQuery(): Builder
    {
        return OpenflowSource::forTenant(Tenant::DEFAULT_ID)->where('is_active', true);
    }

    protected function importMetaKey(): string
    {
        return 'openflow_source_id';
    }

    protected function sourceNoun(): string
    {
        return 'OpenFlow source';
    }

    /**
     * OpenFlow is the one source whose adapter walks *forward* from a
     * high-water mark rather than re-reading a fixed window, so the mark may
     * only move when a pull actually completed — otherwise a single failed run
     * steps the cutoff past submissions nothing ingested, and the next run
     * skips them for good. The scheduling clock still advances either way.
     */
    protected function markFetchSucceeded(Model $source): void
    {
        $source->last_successful_fetch_at = now();
    }
}
