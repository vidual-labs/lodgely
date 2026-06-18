<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\Services\AdMetricsImporter;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Operator actions for the ad-metrics dataset behind the reporting page.
 *
 * Why controllers and not Livewire actions: ad-spend rows carry no per-import
 * tag, and the fetch is a multi-second outbound API call — both are a poor fit
 * for Livewire. Native POST → redirect also sidesteps the documented
 * Livewire morph-drop problems for clickable actions (see CLAUDE.md).
 */
class ReportingDataController extends Controller
{
    /**
     * Pull the most recent ad metrics on demand, instead of waiting for the
     * daily scheduled run (which only fetches yesterday at 05:00). Backfills
     * `lodgely.reporting.backfill_days` (default 30) through today so the
     * reporting page's 30-day view fills immediately after an operator connects
     * a platform — fetching only a week left the 30-/90-day ranges near-empty.
     */
    public function fetch(Request $request, AdMetricsImporter $importer): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        // Outbound API calls can take a few seconds per source/day; give the
        // synchronous request a little more headroom than the PHP default.
        @set_time_limit(120);

        $days = max(1, (int) config('lodgely.reporting.backfill_days', 30));

        $result = $importer->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), days: $days);

        Log::info('lodgely.reporting.ad_metrics_fetched', [
            'user_id' => $request->user()->id,
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'errors' => count($result['errors']),
        ]);

        if ($result['sources'] === 0) {
            return redirect()->route('reporting')->with('status', __(
                'No ad platforms are connected yet — connect Meta or Google under Settings → Ad platforms first.'
            ));
        }

        if ($result['errors'] !== []) {
            return redirect()->route('reporting')->with('status', __(
                'Fetched with errors — :ins new, :upd updated. First error: :err',
                ['ins' => $result['inserted'], 'upd' => $result['updated'], 'err' => $result['errors'][0]],
            ));
        }

        return redirect()->route('reporting')->with('status', __(
            'Fetched the latest ad metrics — :ins new, :upd updated row(s).',
            ['ins' => $result['inserted'], 'upd' => $result['updated']],
        ));
    }

    public function purge(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $deleted = AdSpendReport::where('tenant_id', Tenant::DEFAULT_ID)->delete();

        Log::info('lodgely.reporting.ad_metrics_purged', [
            'user_id' => $request->user()->id,
            'deleted' => $deleted,
        ]);

        return redirect()
            ->route('reporting')
            ->with('status', __(':count ad-metrics row(s) removed.', ['count' => $deleted]));
    }
}
