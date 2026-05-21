<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Native HTML form submission for "Save current view".
 *
 * Mirrors the rationale in {@see InboxColumnPickerController}: the
 * Livewire-driven save-view modal had the same class of "click silently
 * dropped" failure on the inbox subtree. Switching to a plain
 * `<form method="POST">` makes the round-trip indestructible — the
 * browser submits, Laravel routes, this controller writes the row, the
 * user is redirected back to `/inbox` with their filter URL params
 * intact and a `saved` flash so the chip reappears in the picker.
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
                    'sort'     => $validated['sort']     ?? 'created_desc',
                ],
                'is_default' => $isDefault,
            ]);
        });

        // Preserve current filter URL params so the user lands back on the
        // exact view they just saved.
        $query = $request->only(['q', 'status', 'priority', 'source', 'client', 'sort']);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
        $query['saved-views'] = 1;

        return redirect()
            ->to(route('inbox', $query))
            ->with('inbox.saved-filter.stored', __('View saved.'));
    }
}
