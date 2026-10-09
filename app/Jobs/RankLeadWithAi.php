<?php

namespace App\Jobs;

use App\Domain\Ai\Services\LeadRanker;
use App\Models\AiSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one automatic lead ranking (a pending `lead_ranking` AiSummary):
 * calls the model, parses the JSON answer and writes priority + reason +
 * tags onto the lead. All of that lives in LeadRanker::execute(); this
 * class only exists so the hourly command returns instantly and the queue
 * worker absorbs provider latency one lead at a time.
 */
class RankLeadWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $aiSummaryId) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(LeadRanker $ranker): void
    {
        $summary = AiSummary::find($this->aiSummaryId);
        if (! $summary) {
            return;
        }

        $ranker->execute($summary);
    }
}
