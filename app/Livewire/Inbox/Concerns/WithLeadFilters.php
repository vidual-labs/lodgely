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

    #[Url(except: '')]
    public string $outreach = '';

    #[Url(except: 'created_desc')]
    public string $sort = 'created_desc';

    /** @var list<string> Livewire property names that hold filter state. */
    protected const FILTER_PROPERTIES = ['search', 'status', 'priority', 'source', 'client', 'outreach', 'sort'];

    /** @var list<string> Query-string keys that map to those filter properties. */
    protected const FILTER_URL_KEYS = ['q', 'status', 'priority', 'source', 'client', 'outreach', 'sort'];

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->source = '';
        $this->client = '';
        $this->outreach = '';
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
        $this->outreach = $filters['outreach'] ?? '';
        $this->sort = $filters['sort'] ?? 'created_desc';
    }

    protected function applyFilters($base): mixed
    {
        return app(LeadFilter::class)->apply($base, $this->currentFilterState());
    }

    /** Apply the current sort — primary key plus tiebreakers — to a lead query. */
    protected function applySort($query)
    {
        return app(LeadFilter::class)->applySort($query, $this->sort);
    }

    /** @return array{0:string|\Illuminate\Contracts\Database\Query\Expression, 1:string} */
    protected function sortBy(): array
    {
        return app(LeadFilter::class)->sortBy($this->sort);
    }

    /**
     * The current filter state as inbox URL query params (empty values and
     * the default sort left out), with $overrides applied on top — what the
     * Export CSV / JSON links and the Sources chips link to.
     *
     * Built from the component's own properties, never request()->query():
     * that is the page URL only on the first render — every filter change
     * re-renders through Livewire's update request, whose query string is
     * empty, so links built from it silently dropped every active filter.
     *
     * @param  array<string, string|null>  $overrides  URL key => value ('' or null removes it)
     * @return array<string, string>
     */
    public function filterQuery(array $overrides = []): array
    {
        $query = array_combine(self::FILTER_URL_KEYS, array_map(
            fn (string $property) => (string) $this->{$property},
            self::FILTER_PROPERTIES,
        ));

        if ($query['sort'] === 'created_desc') {
            $query['sort'] = '';
        }

        return array_filter(
            array_merge($query, $overrides),
            static fn ($value) => $value !== null && $value !== '',
        );
    }

    /** @return array<string, string> */
    public function exportQuery(string $format): array
    {
        return $this->filterQuery() + ['format' => $format];
    }

    protected function currentFilterState(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'client' => $this->client,
            'outreach' => $this->outreach,
            'sort' => $this->sort,
        ];
    }
}
