<?php

namespace App\Http\Controllers;

use App\Livewire\Inbox\Concerns\WithColumnPicker;
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
 * controller writes the JSONB column, /inbox reloads with ?columns=1
 * so the picker re-opens, and InboxPage::mount() picks up the new picks
 * via WithColumnPicker::loadColumnPicker().
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
            'columns.*'   => ['string', Rule::in(WithColumnPicker::AVAILABLE_COLUMNS)],
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
     * Redirect to /inbox with `?columns=1` so the picker re-opens, plus
     * a one-shot flash so the user sees a "Saved." confirmation.
     */
    private function redirectBackToInbox(Request $request, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('inbox', ['columns' => 1]))
            ->with('inbox.columns.saved', $message);
    }
}
