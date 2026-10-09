<?php

namespace App\Console\Commands;

use App\Domain\Ai\Services\LeadRanker;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Hourly sweep behind automatic lead ranking: queues the oldest unranked
 * leads (bounded by the batch size on /settings/ai and what is left of the
 * daily AI call cap). A quiet no-op whenever AI, the ranking task or the
 * data-sharing consent is off — installs without AI must not see it.
 */
class RankLeadsWithAi extends Command
{
    protected $signature = 'lodgely:ai:rank-leads
        {--limit=   : Override the batch size from AI settings}
        {--dry-run  : List the leads that would be ranked without calling the model}';

    protected $description = 'Queue AI rankings (priority, reason, tags) for unranked leads.';

    public function handle(LeadRanker $ranker): int
    {
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? max(1, (int) $this->option('limit'))
            : null;

        if ($this->option('dry-run')) {
            $ids = Lead::query()
                ->rankingCandidates(Tenant::DEFAULT_ID)
                ->limit($limit ?? 25)
                ->pluck('id');

            $this->info($ids->isEmpty()
                ? 'No unranked leads.'
                : 'Would rank lead(s): '.$ids->implode(', '));

            return self::SUCCESS;
        }

        $result = $ranker->dispatchBatch(Tenant::DEFAULT_ID, $limit);

        if ($result['reason'] === 'cap') {
            $this->info('Daily AI call cap reached — nothing queued.');
        } elseif ($result['reason'] !== null) {
            $this->info('Skipped: '.$result['reason']);
        } else {
            $this->info("Queued {$result['dispatched']} lead(s) for AI ranking.");
        }

        return self::SUCCESS;
    }
}
