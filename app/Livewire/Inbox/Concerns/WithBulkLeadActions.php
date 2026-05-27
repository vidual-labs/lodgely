<?php

namespace App\Livewire\Inbox\Concerns;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\Audit\AuditLogger;
use Livewire\Attributes\On;

/**
 * Bulk selection and bulk status/priority application for the inbox table.
 *
 * Relies on {@see WithLeadFilters::applyFilters()} / {@see WithLeadFilters::sortBy()}
 * for the "select all on page" query, and reacts to the
 * `inbox-filters-cleared` event so selections do not survive a filter reset.
 */
trait WithBulkLeadActions
{
    public array $bulkSelected = [];

    public string $bulkStatusValue = '';

    public string $bulkPriorityValue = '';

    #[On('inbox-filters-cleared')]
    public function clearBulkSelectionOnFiltersCleared(): void
    {
        $this->bulkSelected = [];
    }

    public function bulkToggleAll(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $base = Lead::query()->visibleTo(auth()->user());
        $pageIds = $this->applyFilters($base)
            ->orderBy(...$this->sortBy())
            ->paginate(config('lodgely.pagination.per_page'))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->bulkSelected = (count($this->bulkSelected) === count($pageIds) && count($pageIds) > 0)
            ? []
            : $pageIds;
    }

    public function clearBulkSelection(): void
    {
        $this->bulkSelected = [];
    }

    public function toggleBulkItem(string $id): void
    {
        if (in_array($id, $this->bulkSelected, true)) {
            $this->bulkSelected = array_values(array_diff($this->bulkSelected, [$id]));
        } else {
            $this->bulkSelected[] = $id;
        }
    }

    public function bulkSetStatus(AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        if ($this->bulkStatusValue === '' || empty($this->bulkSelected)) {
            return;
        }

        $statusEnum = LeadStatus::from($this->bulkStatusValue);
        $ids = array_map('intval', $this->bulkSelected);
        $leads = Lead::query()->visibleTo(auth()->user())->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            if ($lead->status === $statusEnum) {
                continue;
            }
            $previous = $lead->status?->value;
            $lead->status = $statusEnum;
            $lead->save();
            $audit->record($lead, 'lead.status_changed', ['from' => $previous, 'to' => $statusEnum->value]);
        }

        $count = $leads->count();
        $this->bulkSelected = [];
        $this->bulkStatusValue = '';
        $this->dispatch('toast', message: $count.' '.($count === 1 ? 'lead' : 'leads').' updated.');
    }

    public function bulkSetPriority(AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        if ($this->bulkPriorityValue === '' || empty($this->bulkSelected)) {
            return;
        }

        $priorityEnum = LeadPriority::from($this->bulkPriorityValue);
        $ids = array_map('intval', $this->bulkSelected);
        $leads = Lead::query()->visibleTo(auth()->user())->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            if ($lead->priority === $priorityEnum) {
                continue;
            }
            $previous = $lead->priority?->value;
            $lead->priority = $priorityEnum;
            $lead->save();
            $audit->record($lead, 'lead.priority_changed', ['from' => $previous, 'to' => $priorityEnum->value]);
        }

        $count = $leads->count();
        $this->bulkSelected = [];
        $this->bulkPriorityValue = '';
        $this->dispatch('toast', message: $count.' '.($count === 1 ? 'lead' : 'leads').' updated.');
    }

    public function bulkDelete(AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        if (empty($this->bulkSelected)) {
            return;
        }

        $ids = array_map('intval', $this->bulkSelected);
        $leads = Lead::query()->visibleTo(auth()->user())->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            $audit->record($lead, 'lead.deleted', ['source' => $lead->source]);
            $lead->delete();
        }

        $count = $leads->count();
        $this->bulkSelected = [];
        $this->bulkStatusValue = '';
        $this->bulkPriorityValue = '';
        $this->dispatch('toast', message: $count.' '.($count === 1 ? 'lead' : 'leads').' deleted.');
    }
}
