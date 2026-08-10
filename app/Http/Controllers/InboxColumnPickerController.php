<?php

namespace App\Http\Controllers;

use App\Livewire\Inbox\InboxPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Native HTML form submission for the inbox column picker.
 *
 * Why this exists in addition to the Livewire trait:
 *
 * The Livewire-driven picker (chip clicks, wire:model.live checkboxes,
 * lifecycle hooks) never produced a visible table update for the user in
 * production — across four rebuild rounds. Every Livewire-side approach
 * looks correct on paper, but something in the bundle / browser combo
 * silently drops the click. A bare <form method="POST"> with checkboxes
 * cannot fail the same way: the browser submits, Laravel routes, this
 * controller writes the JSONB column, /inbox reloads with the picker
 * re-opened, and InboxPage::mount() picks up the new picks via
 * WithColumnPicker::loadColumnPicker().
 *
 * The form carries the current search/status/priority/source/client/
 * outreach/sort state as hidden inputs so applying a column pick doesn't
 * silently drop whatever the user was already looking at. The picker
 * re-opening on reload is a one-shot `inbox.open-panel` session flash, not
 * a `?columns=1` query param — a query param would stick in the address
 * bar forever (nothing ever clears it, and it isn't one of InboxPage's
 * Livewire `#[Url]`-bound properties), reopening the panel on every
 * subsequent visit to that URL. Both bugs — filters dropped on apply, and
 * the panel stuck permanently open — hit the same class of controllers;
 * see the identical fix in InboxFilterPickerController /
 * InboxSavedFilterController.
 *
 * Trade-off: one full page reload per Apply. Acceptable — the picker is
 * a low-frequency action, and the rest of the inbox (filters, sort,
 * pagination, lead selection) is still fully Livewire-reactive.
 *
 * The form has two submit buttons: `action=apply` saves the checkbox
 * state; `action=reset` clears `users.inbox_columns` so the role-aware
 * defaults take over again.
 */
class InboxColumnPickerController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $action = $request->input('action');

        if ($action === 'reset') {
            $user->forceFill(['inbox_columns' => null])->save();

            return $this->redirectBackToInbox($request, __('Columns reset to default.'));
        }

        $validated = $request->validate([
            'columns'     => ['nullable', 'array'],
            'columns.*'   => ['string', Rule::in(InboxPage::AVAILABLE_COLUMNS)],
            'questions'   => ['nullable', 'array'],
            'questions.*' => ['string', 'max:255'],
        ]);

        $columns = array_values(array_unique($validated['columns'] ?? []));
        $questions = array_values(array_unique(array_filter(
            array_map(fn ($q) => trim((string) $q), $validated['questions'] ?? []),
            fn ($q) => $q !== '',
        )));

        // Enforce the same caps the Livewire trait would, in case a stale
        // form bypasses the (now redundant) frontend cap check.
        if (count($questions) > InboxPage::MAX_QUESTION_COLUMNS) {
            $questions = array_slice($questions, 0, InboxPage::MAX_QUESTION_COLUMNS);
        }
        $room = max(0, InboxPage::MAX_TOTAL_COLUMNS - count($questions));
        if (count($columns) > $room) {
            $columns = array_slice($columns, 0, $room);
        }

        $user->forceFill([
            'inbox_columns' => [
                'columns'   => $columns,
                'questions' => $questions,
            ],
        ])->save();

        return $this->redirectBackToInbox($request, __('Columns updated.'));
    }

    /**
     * Redirect to /inbox preserving the current filter state, with a
     * one-shot flash that reopens the columns picker and shows a "Saved."
     * confirmation on the very next request only.
     */
    private function redirectBackToInbox(Request $request, string $message): RedirectResponse
    {
        $query = array_filter([
            'q' => (string) $request->input('search', ''),
            'status' => (string) $request->input('status', ''),
            'priority' => (string) $request->input('priority', ''),
            'source' => (string) $request->input('source', ''),
            'client' => (string) $request->input('client', ''),
            'outreach' => (string) $request->input('outreach', ''),
            'sort' => (string) $request->input('sort', ''),
        ], fn ($v) => $v !== '');

        return redirect()
            ->to(route('inbox', $query))
            ->with('inbox.open-panel', 'columns')
            ->with('inbox.columns.saved', $message);
    }
}
