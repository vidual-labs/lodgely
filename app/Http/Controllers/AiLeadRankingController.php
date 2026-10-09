<?php

namespace App\Http\Controllers;

use App\Domain\Ai\Services\LeadRanker;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Rank unranked leads now" on /settings/ai — the same sweep the hourly
 * scheduler runs, on demand. Native POST → redirect rather than a Livewire
 * action, for the reasons documented in CLAUDE.md (morph-dropped clicks).
 */
class AiLeadRankingController extends Controller
{
    public function rankNow(Request $request, LeadRanker $ranker): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $result = $ranker->dispatchBatch(Tenant::DEFAULT_ID, requester: $request->user());

        Log::info('lodgely.ai.rank_now', [
            'user_id'    => $request->user()->id,
            'dispatched' => $result['dispatched'],
            'reason'     => $result['reason'],
        ]);

        if ($result['reason'] === 'cap') {
            return redirect()->route('settings.ai')->with('status', __(
                'Daily AI call cap reached — try again tomorrow or raise LODGELY_AI_MAX_CALLS_PER_DAY.'
            ));
        }

        if ($result['reason'] !== null) {
            return redirect()->route('settings.ai')->with('status', __(
                'Automatic lead ranking is turned off — enable AI, the ranking task and the data-sharing consent first.'
            ));
        }

        if ($result['dispatched'] === 0) {
            return redirect()->route('settings.ai')->with('status', __('No unranked leads to rank.'));
        }

        return redirect()->route('settings.ai')->with('status', __(
            'Queued :count lead(s) for AI ranking.',
            ['count' => $result['dispatched']],
        ));
    }
}
