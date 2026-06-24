<?php

namespace App\Http\Controllers\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Http\Controllers\Controller;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Native HTML form endpoints for the OpenFlow import page.
 *
 * Same rationale as {@see GoogleSheetsImportController} and
 * {@see MetaLeadsImportController}: the Livewire `wire:click` "Fetch" and
 * delete buttons silently drop clicks in production (the CLAUDE.md morph-drop
 * gotcha), so the load-bearing actions are plain `<form method="POST">` posts.
 */
class OpenflowImportController extends Controller
{
    /** Trigger an immediate pull for a single OpenFlow source. */
    public function fetch(Request $request, OpenflowSource $source, ImportRunner $runner, OpenflowLeadSource $leadSource): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);
        abort_unless($source->tenant_id === Tenant::DEFAULT_ID, 404);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => $request->user()->id,
            'source'    => $leadSource->key(),
            'label'     => $source->label.' · '.now()->format('Y-m-d H:i'),
            'meta'      => ['openflow_source_id' => $source->id],
        ]);

        try {
            $result = $runner->run($import, $leadSource);
            $source->update(['last_fetched_at' => now()]);

            return redirect()
                ->route('imports.openflow')
                ->with('status', __('Fetched: :imported imported, :skipped skipped, :dup duplicates, :inv invalid.', [
                    'imported' => $result->rows_imported,
                    'skipped'  => $result->rows_skipped,
                    'dup'      => $result->rows_duplicate,
                    'inv'      => $result->rows_invalid,
                ]));
        } catch (Throwable $e) {
            return redirect()
                ->route('imports.openflow')
                ->with('status', __('Fetch failed: :error', ['error' => $e->getMessage()]));
        }
    }

    /** Delete a single OpenFlow import and the leads it created. */
    public function destroy(Request $request, Import $import): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);
        abort_unless($import->source === 'openflow', 404);

        // Force-delete (not soft-delete) so re-fetching the same form creates
        // genuinely fresh leads without ghost duplicate matches.
        $deleted = $import->leads()->forceDelete();
        $import->delete();

        return redirect()
            ->route('imports.openflow')
            ->with('status', __('Import deleted — :count leads removed.', ['count' => $deleted]));
    }

    /**
     * Wipe the whole OpenFlow backlog: force-delete every openflow lead
     * (including copies whose import_id was nulled) and every import. The next
     * idempotent scheduled fetch rebuilds one clean copy per submission.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $importIds = Import::where('source', 'openflow')->pluck('id');

        $deletedLeads = Lead::withTrashed()
            ->where(function ($q) use ($importIds) {
                $q->whereIn('import_id', $importIds)->orWhere('source', 'openflow');
            })
            ->forceDelete();

        $deletedImports = Import::where('source', 'openflow')->delete();

        return redirect()
            ->route('imports.openflow')
            ->with('status', __(':imports import(s) and :leads lead(s) removed.', [
                'imports' => $deletedImports,
                'leads'   => $deletedLeads,
            ]));
    }
}
