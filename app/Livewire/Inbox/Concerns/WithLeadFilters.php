<?php

namespace App\Livewire\Inbox\Concerns;

use Livewire\Attributes\Url;

/**
 * URL-bound filter state for the inbox table.
 *
 * Designed to be composed with {@see WithBulkLeadActions} — {@see clearFilters()}
 * also resets `$bulkSelected` defined on that trait so the table never shows
 * stale selections after the filter set changes.
 */
trait WithLeadFilters
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: 'created_desc')]
    public string $sort = 'created_desc';

    /** @var list<string> Livewire property names that hold filter state. */
    protected const FILTER_PROPERTIES = ['search', 'status', 'priority', 'source', 'client', 'sort'];

    /** @var list<string> Query-string keys that map to those filter properties. */
    protected const FILTER_URL_KEYS = ['q', 'status', 'priority', 'source', 'client', 'sort'];

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->source = '';
        $this->client = '';
        $this->bulkSelected = [];
        $this->resetPage();
    }

    protected function applyFilterState(array $filters): void
    {
        $this->search = $filters['search'] ?? '';
        $this->status = $filters['status'] ?? '';
        $this->priority = $filters['priority'] ?? '';
        $this->source = $filters['source'] ?? '';
        $this->client = $filters['client'] ?? '';
        $this->sort = $filters['sort'] ?? 'created_desc';
    }

    protected function applyFilters($base): mixed
    {
        return (clone $base)
            ->search($this->search)
            ->when($this->status, fn ($q, $v) => $q->where('status', $v))
            ->when($this->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($this->source, fn ($q, $v) => $q->where('source', $v))
            ->when($this->client, fn ($q, $v) => $q->whereRaw('LOWER(client_name) = ?', [mb_strtolower($v)]));
    }

    /** @return array{0:string, 1:string} */
    protected function sortBy(): array
    {
        return match ($this->sort) {
            'created_asc' => ['created_at', 'asc'],
            'priority_desc' => ['priority', 'desc'],
            default => ['created_at', 'desc'],
        };
    }

    protected function currentFilterState(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'client' => $this->client,
            'sort' => $this->sort,
        ];
    }
}
