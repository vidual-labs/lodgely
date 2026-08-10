<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Native HTML form endpoints for saved-view actions.
 *
 * Mirrors the rationale in {@see InboxColumnPickerController}: the
 * Livewire-driven `wire:click` chip actions (load / toggle default /
 * delete) had the same class of "click silently dropped" failure on
 * the inbox filter-card subtree as the column picker did. Switching to
 * plain `<form method="POST">` submissions makes them indestructible —
 * the browser submits, Laravel routes, this controller mutates the row,
 * and `/inbox` reloads with the new state.
 *
 * Two entry points:
 *  - {@see store()}        POST /inbox/saved-filters
 *  - {@see action()}       POST /inbox/saved-filters/{filter}
 *      A single `action=load|default|delete` switch keeps the chip
 *      markup to one `<form>` per row.
 *
 * `store()` and the `default`/`delete` branches of `action()` reopen the
 * "Saved views" panel via a one-shot `inbox.open-panel` session flash, not a
 * `?saved-views=1` query param — a query param would stick in the address
 * bar forever (nothing ever clears it), reopening the panel on every
 * subsequent visit to that URL. `load` doesn't reopen the panel at all —
 * it's navigating away to view the loaded filter results, not managing views.
 */
class InboxSavedFilterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'is_default'  => ['nullable', 'boolean'],
            'search'      => ['nullable', 'string', 'max:255'],
            'status'      => ['nullable', 'string', 'max:32'],
            'priority'    => ['nullable', 'string', 'max:32'],
            'source'      => ['nullable', 'string', 'max:64'],
            'client'      => ['nullable', 'string', 'max:255'],
            'outreach'    => ['nullable', 'string', 'max:32'],
            'sort'        => ['nullable', Rule::in(['created_desc', 'created_asc', 'priority_desc'])],
        ]);

        $isDefault = (bool) ($validated['is_default'] ?? false);

        DB::transaction(function () use ($user, $validated, $isDefault) {
            if ($isDefault) {
                SavedFilter::where('user_id', $user->id)->update(['is_default' => false]);
            }

            SavedFilter::create([
                'user_id'    => $user->id,
                'tenant_id'  => Tenant::DEFAULT_ID,
                'name'       => trim($validated['name']),
                'filters'    => [
                    'search'   => $validated['search']   ?? '',
                    'status'   => $validated['status']   ?? '',
                    'priority' => $validated['priority'] ?? '',
                    'source'   => $validated['source']   ?? '',
                    'client'   => $validated['client']   ?? '',
                    'outreach' => $validated['outreach'] ?? '',
                    'sort'     => $validated['sort']     ?? 'created_desc',
                ],
                'is_default' => $isDefault,
            ]);
        });

        return $this->redirectToInbox($request)
            ->with('inbox.open-panel', 'saved-views')
            ->with('inbox.saved-filter.stored', __('View saved.'));
    }

    /**
     * Single endpoint for per-chip actions on an existing saved view.
     * Dispatches on `action` so the chip markup is one tiny form per row.
     */
    public function action(Request $request, SavedFilter $filter): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($filter->user_id === $user->id, 404);

        $action = $request->input('action');

        if ($action === 'load') {
            $query = [];
            $filters = $filter->filters ?? [];
            if (! empty($filters['search']))   { $query['q'] = $filters['search']; }
            if (! empty($filters['status']))   { $query['status'] = $filters['status']; }
            if (! empty($filters['priority'])) { $query['priority'] = $filters['priority']; }
            if (! empty($filters['source']))   { $query['source'] = $filters['source']; }
            if (! empty($filters['client']))   { $query['client'] = $filters['client']; }
            if (! empty($filters['outreach'])) { $query['outreach'] = $filters['outreach']; }
            if (! empty($filters['sort']) && $filters['sort'] !== 'created_desc') {
                $query['sort'] = $filters['sort'];
            }

            return redirect()->to(route('inbox', $query));
        }

        if ($action === 'default') {
            DB::transaction(function () use ($user, $filter) {
                if ($filter->is_default) {
                    $filter->update(['is_default' => false]);
                } else {
                    SavedFilter::where('user_id', $user->id)->update(['is_default' => false]);
                    $filter->update(['is_default' => true]);
                }
            });

            return $this->redirectToInbox($request)
                ->with('inbox.open-panel', 'saved-views')
                ->with('inbox.saved-filter.stored', $filter->is_default
                    ? __('Default view updated.')
                    : __('Default view cleared.'));
        }

        if ($action === 'delete') {
            $filter->delete();

            return $this->redirectToInbox($request)
                ->with('inbox.open-panel', 'saved-views')
                ->with('inbox.saved-filter.stored', __('View deleted.'));
        }

        abort(422);
    }

    /**
     * Return the user to /inbox preserving any current filter URL params.
     * Callers that need the "saved views" panel to reopen add the
     * `inbox.open-panel` flash themselves — see the class docblock for why
     * that isn't a query param.
     */
    private function redirectToInbox(Request $request): RedirectResponse
    {
        $query = $request->only(['q', 'status', 'priority', 'source', 'client', 'outreach', 'sort']);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        return redirect()->to(route('inbox', $query));
    }
}
