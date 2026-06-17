<?php

namespace App\Http\Controllers;

use App\Models\AdSpendReport;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Operator action to wipe ad-metrics data (the rows in ad_spend_reports).
 *
 * Why a controller and not a Livewire action: ad-spend rows carry no
 * per-import tag, so the demo/mock spend the importer generates can't be
 * cleaned up the way demo leads can (those hang off a tracking Import row).
 * This is the explicit "clear it all" escape hatch operators were missing.
 * Native POST → redirect also sidesteps the documented Livewire morph-drop
 * problems for clickable actions.
 */
class ReportingDataController extends Controller
{
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
