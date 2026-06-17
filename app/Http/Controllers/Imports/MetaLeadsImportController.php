<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Native HTML form endpoints for deleting Meta Lead Ads imports.
 *
 * Same rationale as {@see GoogleSheetsImportController}: the Livewire
 * `wire:click` delete button on the "Recent imports" table silently drops
 * clicks in production (the CLAUDE.md morph-drop gotcha). Plain
 * `<form method="POST">` submissions are indestructible.
 */
class MetaLeadsImportController extends Controller
{
    /** Delete a single Meta Lead Ads import and the leads it created. */
    public function destroy(Request $request, Import $import): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);
        abort_unless($import->source === 'meta_leads', 404);

        // Force-delete (not soft-delete) so re-fetching the same forms creates
        // genuinely fresh leads without ghost duplicate matches.
        $deleted = $import->leads()->forceDelete();
        $import->delete();

        return redirect()
            ->route('imports.meta-leads')
            ->with('status', __('Import deleted — :count leads removed.', ['count' => $deleted]));
    }

    /**
     * Wipe the whole Meta Lead Ads backlog: force-delete every meta_leads lead
     * (including copies whose import_id was nulled) and every import. The next
     * idempotent scheduled fetch rebuilds one clean copy per Meta lead.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $importIds = Import::where('source', 'meta_leads')->pluck('id');

        $deletedLeads = Lead::withTrashed()
            ->where(function ($q) use ($importIds) {
                $q->whereIn('import_id', $importIds)->orWhere('source', 'meta_leads');
            })
            ->forceDelete();

        $deletedImports = Import::where('source', 'meta_leads')->delete();

        return redirect()
            ->route('imports.meta-leads')
            ->with('status', __(':imports import(s) and :leads lead(s) removed.', [
                'imports' => $deletedImports,
                'leads'   => $deletedLeads,
            ]));
    }
}
