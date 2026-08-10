<?php

namespace App\Http\Controllers;

use App\Livewire\Inbox\InboxPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Native HTML form submission for the inbox filter-dropdown picker — which
 * of Status / Priority / Source / Outreach show up in the toolbar. Same
 * "plain form, not Livewire" rationale as {@see InboxColumnPickerController}
 * — see CLAUDE.md.
 *
 * The form carries the current search/status/priority/source/client/outreach/
 * sort state as hidden inputs (mirroring the "Save current view" panel) so
 * applying a filter-picker change doesn't silently drop whatever the user was
 * already looking at. A filter dropped from the picked set has its value
 * dropped from the redirect too, even if its hidden input still carried one
 * — otherwise a lead list could stay invisibly filtered by a dropdown the
 * user just removed and has no way left to clear.
 *
 * Two submit buttons: `action=apply` saves the picked set; `action=reset`
 * clears `users.inbox_filters` so the default set (status/priority/source)
 * takes over again — see {@see \App\Livewire\Inbox\Concerns\WithFilterPicker::defaultPickedFilters()}.
 */
class InboxFilterPickerController extends Controller
{
    /** Must match WithFilterPicker::defaultPickedFilters(). */
    private const DEFAULT_FILTERS = ['status', 'priority', 'source'];

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($request->input('action') === 'reset') {
            $user->forceFill(['inbox_filters' => null])->save();

            return $this->redirectBackToInbox($request, __('Filters reset to default.'), self::DEFAULT_FILTERS);
        }

        $validated = $request->validate([
            'filters'   => ['nullable', 'array'],
            'filters.*' => ['string', Rule::in(InboxPage::AVAILABLE_FILTERS)],
        ]);

        $picked = array_values(array_unique($validated['filters'] ?? []));

        $user->forceFill(['inbox_filters' => $picked])->save();

        return $this->redirectBackToInbox($request, __('Filters updated.'), $picked);
    }

    /**
     * Redirect to /inbox carrying forward the hidden-input filter state —
     * except the four picker-owned filters (status/priority/source/outreach),
     * which only survive if still in $picked. The picker re-opening on
     * reload is a one-shot `inbox.open-panel` session flash, not a
     * `?filters=1` query param: a query param would stick in the address bar
     * forever (nothing ever clears it, and it isn't one of InboxPage's
     * Livewire `#[Url]`-bound properties), reopening the panel on every
     * subsequent visit to that URL — not just the one right after Apply.
     *
     * @param  list<string>  $picked
     */
    private function redirectBackToInbox(Request $request, string $message, array $picked): RedirectResponse
    {
        $query = array_filter([
            'q' => (string) $request->input('search', ''),
            'client' => (string) $request->input('client', ''),
            'sort' => (string) $request->input('sort', ''),
        ], fn ($v) => $v !== '');

        $paramForFilter = ['status' => 'status', 'priority' => 'priority', 'source' => 'source', 'outreach' => 'outreach'];
        foreach ($paramForFilter as $filterKey => $queryParam) {
            $value = (string) $request->input($filterKey, '');
            if ($value !== '' && in_array($filterKey, $picked, true)) {
                $query[$queryParam] = $value;
            }
        }

        return redirect()->to(route('inbox', $query))
            ->with('inbox.open-panel', 'filters')
            ->with('inbox.filters.saved', $message);
    }
}
