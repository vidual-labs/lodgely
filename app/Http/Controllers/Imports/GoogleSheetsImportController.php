<?php

namespace App\Http\Controllers\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Http\Controllers\Controller;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Native HTML form endpoints for the Google Sheets import page.
 *
 * Mirrors the rationale in {@see \App\Http\Controllers\InboxSavedFilterController}:
 * Livewire `wire:click` buttons on this page silently dropped clicks in
 * production (the CLAUDE.md morph-drop gotcha) — the confirm dialog showed but
 * the server action never ran. Plain `<form method="POST">` submissions are
 * indestructible: the browser submits, Laravel routes here, this controller
 * acts, and `/imports/google-sheets` reloads with the result flashed.
 *
 * This covers both deleting imports AND the "Fetch now" trigger — the latter is
 * the action that actually pulls leads, so a dropped click there looks exactly
 * like "the Google Sheets import is broken, nothing happens".
 */
class GoogleSheetsImportController extends Controller
{
    /**
     * Run an immediate fetch for one sheet source. Always runs (no `isDue`
     * gate) so the operator can pull on demand — and, crucially, recover a
     * source the hourly scheduler is holding off after an earlier failure.
     */
    public function fetch(
        Request $request,
        int $source,
        ImportRunner $runner,
        GoogleSheetsLeadSource $adapter,
    ): RedirectResponse {
        abort_unless($request->user()?->isOperator(), 403);

        $sheetSource = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($source);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => $request->user()?->id,
            'source'    => $adapter->key(),
            'label'     => $sheetSource->label.' · '.now()->format('Y-m-d H:i'),
            'meta'      => ['sheet_source_id' => $sheetSource->id],
        ]);

        try {
            $result = $runner->run($import, $adapter);
            $sheetSource->update(['last_fetched_at' => now()]);

            return redirect()
                ->route('imports.google-sheets')
                ->with('status', $this->summarize($result));
        } catch (Throwable $e) {
            // ImportRunner already recorded the reason on $import and marked it
            // failed; surface it inline too so the operator sees it immediately.
            return redirect()
                ->route('imports.google-sheets')
                ->with('status', __('Fetch failed: :reason', ['reason' => $e->getMessage()]));
        }
    }

    /**
     * Turn an import's counters into an operator-friendly sentence — including
     * the common "ran fine but found nothing" cases, which otherwise read as a
     * useless "0 imported" and leave the operator wondering what went wrong.
     */
    private function summarize(Import $result): string
    {
        if ($result->rows_total === 0) {
            return __('Fetch ran but the sheet returned no rows. Check the sheet range (e.g. "Sheet1" must match the tab name) and that the connected Google account can read this spreadsheet.');
        }

        if ($result->rows_imported === 0 && $result->rows_invalid === $result->rows_total) {
            return __('Fetch read :total row(s) but none had a name, email or phone — check the column mapping.', ['total' => $result->rows_total]);
        }

        if ($result->rows_imported === 0 && $result->rows_skipped > 0) {
            return __('Fetch read :total row(s); all :skipped were already imported (skipped).', [
                'total'   => $result->rows_total,
                'skipped' => $result->rows_skipped,
            ]);
        }

        return __(':imported imported, :skipped skipped, :dup duplicates, :inv invalid.', [
            'imported' => $result->rows_imported,
            'skipped'  => $result->rows_skipped,
            'dup'      => $result->rows_duplicate,
            'inv'      => $result->rows_invalid,
        ]);
    }

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
