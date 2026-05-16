<?php

namespace App\Livewire\Inbox\Concerns;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\Audit\AuditLogger;

/**
 * Bulk selection and bulk status/priority application for the inbox table.
 *
 * Relies on {@see WithLeadFilters::applyFilters()} / {@see WithLeadFilters::sortBy()}
 * for the "select all on page" query.
 */
trait WithBulkLeadActions
{
    public array $bulkSelected = [];

    public string $bulkStatusValue = '';

    public string $bulkPriorityValue = '';

    public function bulkToggleAll(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $base = Lead::query()->visibleTo(auth()->user());
        $pageIds = $this->applyFilters($base)
            ->orderBy(...$this->sortBy())
            ->paginate(config('lodgely.pagination.per_page'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->bulkSelected = (count($this->bulkSelected) === count($pageIds) && count($pageIds) > 0)
            ? []
            : $pageIds;
    }

    public function clearBulkSelection(): void
    {
        $this->bulkSelected = [];
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
}
