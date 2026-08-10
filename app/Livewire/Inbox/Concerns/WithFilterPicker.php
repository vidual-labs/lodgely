<?php

namespace App\Livewire\Inbox\Concerns;

/**
 * Per-user picked *filter dropdown* set for the inbox toolbar — which of
 * Status / Priority / Source / Outreach show up, as opposed to
 * {@see WithColumnPicker}, which picks *table columns*. A client whose
 * workflow never touches priority but lives in the outreach toggles can drop
 * one and add the other; Search and Sort aren't part of this — they're not
 * optional dropdowns, and the operator-only Client dropdown is tied to role,
 * not personal preference, so it stays outside the picker entirely.
 *
 * Persisted to users.inbox_filters (JSONB array of picked keys). Null =
 * default set (see {@see defaultPickedFilters()}).
 *
 * UI flow mirrors WithColumnPicker exactly: a native HTML <form method="POST">
 * posting to {@see \App\Http\Controllers\InboxFilterPickerController} — see
 * CLAUDE.md for why the inbox filter card never drives writes through
 * wire:click/wire:model.live. This trait still exposes `pickedFilters` so the
 * Livewire view can decide which dropdowns to render, and keeps the
 * imperative `togglePickedFilter` / `saveFilterPicker` actions for tests and
 * any future programmatic caller.
 */
trait WithFilterPicker
{
    /** @var list<string> Picked filter-dropdown keys. */
    public array $pickedFilters = [];

    /**
     * Every filter dropdown the picker can turn on/off, in toolbar order.
     * Search (free text) and Sort aren't dropdowns in this sense; the
     * operator-only Client dropdown is a role affordance, not a preference.
     */
    public const AVAILABLE_FILTERS = ['status', 'priority', 'source', 'outreach'];

    /** The fixed set every install shipped with before this picker existed. */
    protected function defaultPickedFilters(): array
    {
        return ['status', 'priority', 'source'];
    }

    protected function loadFilterPicker(): void
    {
        $stored = auth()->user()?->inbox_filters;

        $this->pickedFilters = is_array($stored)
            ? array_values(array_intersect(self::AVAILABLE_FILTERS, array_map('strval', $stored)))
            : $this->defaultPickedFilters();
    }

    /**
     * Imperative entry point retained for tests and any future programmatic
     * caller. Mirrors a single checkbox-toggle from the form.
     */
    public function togglePickedFilter(string $key): void
    {
        if (! in_array($key, self::AVAILABLE_FILTERS, true)) {
            return;
        }

        $idx = array_search($key, $this->pickedFilters, true);
        if ($idx === false) {
            $this->pickedFilters[] = $key;
        } else {
            array_splice($this->pickedFilters, $idx, 1);
            $this->pickedFilters = array_values($this->pickedFilters);
        }

        $this->persistFilterPicker();
    }

    /**
     * Public Livewire action retained so callers (tests, ad-hoc
     * `->call('saveFilterPicker')`) still have an idempotent "force a write"
     * entry point.
     */
    public function saveFilterPicker(): void
    {
        $this->persistFilterPicker();
    }

    public function resetFilterPicker(): void
    {
        $this->pickedFilters = $this->defaultPickedFilters();

        $user = auth()->user();
        if ($user) {
            $user->forceFill(['inbox_filters' => null])->save();
        }

        $this->dispatch('toast', message: __('Filters reset to default.'));
    }

    private function persistFilterPicker(): void
    {
        $user = auth()->user();
        if ($user) {
            $user->forceFill(['inbox_filters' => $this->pickedFilters])->save();
        }
    }
}
