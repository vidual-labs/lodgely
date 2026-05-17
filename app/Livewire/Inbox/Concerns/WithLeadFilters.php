<?php

namespace App\Livewire\Inbox\Concerns;

use App\Domain\Leads\Services\LeadFilter;
use Livewire\Attributes\Url;

/**
 * URL-bound filter state for the inbox table.
 *
 * Emits an `inbox-filters-cleared` self-dispatched event from
 * {@see clearFilters()} so other traits (e.g. {@see WithBulkLeadActions})
 * can react without this trait knowing about them.
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
        $this->resetPage();
        $this->dispatch('inbox-filters-cleared')->self();
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
        return app(LeadFilter::class)->apply($base, $this->currentFilterState());
    }

    /** @return array{0:string, 1:string} */
    protected function sortBy(): array
    {
        return app(LeadFilter::class)->sortBy($this->sort);
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
