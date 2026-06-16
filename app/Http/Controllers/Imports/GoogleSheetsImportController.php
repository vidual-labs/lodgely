<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Native HTML form endpoints for deleting Google Sheets imports.
 *
 * Mirrors the rationale in {@see \App\Http\Controllers\InboxSavedFilterController}:
 * the Livewire `wire:click="deleteImport(...)"` button on the "Recent imports"
 * table silently dropped clicks in production (the CLAUDE.md morph-drop gotcha) —
 * the confirm dialog showed but the server action never ran. Plain
 * `<form method="POST">` submissions are indestructible: the browser submits,
 * Laravel routes here, this controller deletes, and `/imports/google-sheets`
 * reloads with the row gone.
 */
class GoogleSheetsImportController extends Controller
{
    /** Delete a single Google Sheets import and the leads it created. */
    public function destroy(Request $request, Import $import): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);
        abort_unless($import->source === 'google_sheets', 404);

        // Force-delete (not soft-delete) so reimporting the same sheet creates
        // genuinely fresh leads without ghost duplicate matches.
        $deleted = $import->leads()->forceDelete();
        $import->delete();

        return redirect()
            ->route('imports.google-sheets')
            ->with('status', __('Import deleted — :count leads removed.', ['count' => $deleted]));
    }

    /**
     * Wipe the whole Google Sheets backlog: force-delete every google_sheets
     * lead (including copies whose import_id was nulled) and every import. The
     * next idempotent scheduled fetch rebuilds one clean copy per sheet row.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        // A lead's own `source` column may hold a value mapped from a sheet
        // column (e.g. "facebook"), so the reliable link to "came from Google
        // Sheets" is its import_id. Match on import provenance OR the
        // google_sheets source (covers copies whose import_id was nulled).
        $importIds = Import::where('source', 'google_sheets')->pluck('id');

        $deletedLeads = Lead::withTrashed()
            ->where(function ($q) use ($importIds) {
                $q->whereIn('import_id', $importIds)->orWhere('source', 'google_sheets');
            })
            ->forceDelete();

        $deletedImports = Import::where('source', 'google_sheets')->delete();

        return redirect()
            ->route('imports.google-sheets')
            ->with('status', __(':imports import(s) and :leads lead(s) removed.', [
                'imports' => $deletedImports,
                'leads'   => $deletedLeads,
            ]));
    }
}
