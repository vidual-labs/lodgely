<?php

namespace App\Livewire\Inbox\Concerns;

use App\Models\SavedFilter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-user "saved view" CRUD for the inbox filter set.
 *
 * Relies on {@see WithLeadFilters::currentFilterState()} /
 * {@see WithLeadFilters::applyFilterState()} for the actual filter payload.
 */
trait WithSavedFilters
{
    public bool $showSaveDialog = false;

    public string $newFilterName = '';

    public bool $newFilterIsDefault = false;

    public function openSaveDialog(): void
    {
        $this->showSaveDialog = true;
        $this->newFilterName = '';
        $this->newFilterIsDefault = false;
    }

    public function closeSaveDialog(): void
    {
        $this->showSaveDialog = false;
        $this->newFilterName = '';
        $this->newFilterIsDefault = false;
    }

    public function saveFilter(): void
    {
        $this->validate(['newFilterName' => ['required', 'string', 'max:100']]);

        if ($this->newFilterIsDefault) {
            SavedFilter::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        SavedFilter::create([
            'user_id' => auth()->id(),
            'tenant_id' => Tenant::DEFAULT_ID,
            'name' => trim($this->newFilterName),
            'filters' => $this->currentFilterState(),
            'is_default' => $this->newFilterIsDefault,
        ]);

        $this->closeSaveDialog();
        $this->dispatch('toast', message: __('Filter saved.'));
    }

    public function loadFilter(int $id): void
    {
        $filter = SavedFilter::where('user_id', auth()->id())->findOrFail($id);
        $this->applyFilterState($filter->filters);
        $this->bulkSelected = [];
        $this->resetPage();
    }

    public function deleteFilter(int $id): void
    {
        SavedFilter::where('user_id', auth()->id())->findOrFail($id)->delete();
        $this->dispatch('toast', message: __('Filter deleted.'));
    }

    public function toggleDefaultFilter(int $id): void
    {
        $filter = SavedFilter::where('user_id', auth()->id())->findOrFail($id);

        if ($filter->is_default) {
            DB::transaction(function () use ($filter) {
                $filter->update(['is_default' => false]);
            });
            $this->dispatch('toast', message: __('Default view cleared.'));
        } else {
            DB::transaction(function () use ($filter) {
                SavedFilter::where('user_id', auth()->id())->update(['is_default' => false]);
                $filter->update(['is_default' => true]);
            });
            $this->dispatch('toast', message: __('Default view updated.'));
        }
    }

    protected function loadDefaultSavedFilter(): void
    {
        $default = SavedFilter::where('user_id', auth()->id())
            ->where('is_default', true)
            ->first();

        if ($default) {
            $this->applyFilterState($default->filters);
        }
    }

    /** @return Collection<int, SavedFilter> */
    protected function userSavedFilters(): Collection
    {
        return SavedFilter::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
