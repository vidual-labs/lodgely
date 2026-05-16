<?php

namespace App\Livewire\Inbox;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\DuplicateDetector;
use App\Models\Lead;
use App\Models\SavedFilter;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class InboxPage extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')] public string $search = '';
    #[Url(except: '')] public string $status   = '';
    #[Url(except: '')] public string $priority = '';
    #[Url(except: '')] public string $source   = '';
    #[Url(except: '')] public string $client   = '';
    #[Url(except: 'created_desc')] public string $sort = 'created_desc';

    public ?int $selectedLeadId = null;

    public array $bulkSelected = [];
    public string $bulkStatusValue   = '';
    public string $bulkPriorityValue = '';

    public ?string $newNoteBody = null;

    public bool $showSaveDialog     = false;
    public string $newFilterName     = '';
    public bool $newFilterIsDefault  = false;

    public bool $showManualForm = false;
    public array $manual = [
        'client_name'   => '',
        'campaign_name' => '',
        'full_name'     => '',
        'email'         => '',
        'phone'         => '',
        'message'       => '',
        'priority'      => 'medium',
    ];

    protected function rules(): array
    {
        return [
            'newNoteBody' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function mount(): void
    {
        if (! request()->hasAny(['q', 'status', 'priority', 'source', 'client', 'sort'])) {
            $default = SavedFilter::where('user_id', auth()->id())
                ->where('is_default', true)
                ->first();

            if ($default) {
                $this->applyFilterState($default->filters);
            }
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'priority', 'source', 'client', 'sort'], true)) {
            $this->resetPage();
            $this->bulkSelected = [];
        }
    }

    public function selectLead(int $id): void
    {
        $this->selectedLeadId = $id;
        $this->newNoteBody = null;
    }

    public function closePanel(): void
    {
        $this->selectedLeadId = null;
        $this->newNoteBody = null;
    }

    public function setStatus(int $leadId, string $status, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $status = LeadStatus::from($status);
        $lead = $this->guardedLead($leadId);

        if ($lead->status === $status) {
            return;
        }

        $previous = $lead->status?->value;
        $lead->status = $status;
        $lead->save();

        $audit->record($lead, 'lead.status_changed', [
            'from' => $previous,
            'to'   => $status->value,
        ]);
    }

    public function setPriority(int $leadId, string $priority, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $priority = LeadPriority::from($priority);
        $lead = $this->guardedLead($leadId);

        if ($lead->priority === $priority) {
            return;
        }

        $previous = $lead->priority?->value;
        $lead->priority = $priority;
        $lead->save();

        $audit->record($lead, 'lead.priority_changed', [
            'from' => $previous,
            'to'   => $priority->value,
        ]);
    }

    public function reconcileDuplicate(int $leadId, DuplicateDetector $detector, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $lead = $this->guardedLead($leadId);
        if ($detector->reconcile($lead)) {
            $audit->record($lead, 'lead.duplicate_reconciled', [
                'duplicate_flag' => $lead->duplicate_flag,
                'duplicate_of'   => $lead->duplicate_of_id,
            ]);
        }
    }

    public function addNote(AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->validate();
        $body = trim((string) $this->newNoteBody);
        if ($body === '' || $this->selectedLeadId === null) {
            return;
        }

        $lead = $this->guardedLead($this->selectedLeadId);
        $note = $lead->notes()->create([
            'user_id' => auth()->id(),
            'body'    => $body,
        ]);

        $audit->record($lead, 'lead.note_added', ['note_id' => $note->id]);
        $this->newNoteBody = null;
    }

    public function openManualForm(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
        $this->showManualForm = true;
    }

    public function closeManualForm(): void
    {
        $this->showManualForm = false;
        $this->reset('manual');
        $this->manual['priority'] = 'medium';
    }

    public function saveManual(\App\Domain\Leads\Services\LeadIngestor $ingestor): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $data = $this->validate([
            'manual.client_name'   => ['nullable', 'string', 'max:120'],
            'manual.campaign_name' => ['nullable', 'string', 'max:120'],
            'manual.full_name'     => ['nullable', 'string', 'max:120'],
            'manual.email'         => ['nullable', 'email', 'max:160'],
            'manual.phone'         => ['nullable', 'string', 'max:60'],
            'manual.message'       => ['nullable', 'string', 'max:5000'],
            'manual.priority'      => ['required', 'in:low,medium,high'],
        ])['manual'];

        if (! $data['email'] && ! $data['phone'] && ! $data['full_name']) {
            $this->addError('manual.full_name', __('Provide at least a name, email, or phone.'));
            return;
        }

        $ingestor->ingest([
            'source'        => 'manual',
            'client_name'   => $data['client_name'] ?: null,
            'campaign_name' => $data['campaign_name'] ?: null,
            'full_name'     => $data['full_name'] ?: null,
            'email'         => $data['email'] ?: null,
            'phone'         => $data['phone'] ?: null,
            'message'       => $data['message'] ?: null,
            'priority'      => $data['priority'],
        ], null, \App\Models\Tenant::DEFAULT_ID, auth()->id());

        $this->closeManualForm();
        $this->resetPage();
        $this->dispatch('toast', message: __('Lead added.'));
    }

    public function clearFilters(): void
    {
        $this->search   = '';
        $this->status   = '';
        $this->priority = '';
        $this->source   = '';
        $this->client   = '';
        $this->bulkSelected = [];
        $this->resetPage();
    }

    public function openSaveDialog(): void
    {
        $this->showSaveDialog   = true;
        $this->newFilterName    = '';
        $this->newFilterIsDefault = false;
    }

    public function closeSaveDialog(): void
    {
        $this->showSaveDialog   = false;
        $this->newFilterName    = '';
        $this->newFilterIsDefault = false;
    }

    public function saveFilter(): void
    {
        $this->validate(['newFilterName' => ['required', 'string', 'max:100']]);

        if ($this->newFilterIsDefault) {
            SavedFilter::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        SavedFilter::create([
            'user_id'    => auth()->id(),
            'tenant_id'  => \App\Models\Tenant::DEFAULT_ID,
            'name'       => trim($this->newFilterName),
            'filters'    => [
                'search'   => $this->search,
                'status'   => $this->status,
                'priority' => $this->priority,
                'source'   => $this->source,
                'client'   => $this->client,
                'sort'     => $this->sort,
            ],
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
            $filter->update(['is_default' => false]);
            $this->dispatch('toast', message: __('Default view cleared.'));
        } else {
            SavedFilter::where('user_id', auth()->id())->update(['is_default' => false]);
            $filter->update(['is_default' => true]);
            $this->dispatch('toast', message: __('Default view updated.'));
        }
    }

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
        $ids        = array_map('intval', $this->bulkSelected);
        $leads      = Lead::query()->visibleTo(auth()->user())->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            if ($lead->status === $statusEnum) {
                continue;
            }
            $previous     = $lead->status?->value;
            $lead->status = $statusEnum;
            $lead->save();
            $audit->record($lead, 'lead.status_changed', ['from' => $previous, 'to' => $statusEnum->value]);
        }

        $count                 = $leads->count();
        $this->bulkSelected    = [];
        $this->bulkStatusValue = '';
        $this->dispatch('toast', message: $count . ' ' . ($count === 1 ? 'lead' : 'leads') . ' updated.');
    }

    public function bulkSetPriority(AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        if ($this->bulkPriorityValue === '' || empty($this->bulkSelected)) {
            return;
        }

        $priorityEnum = LeadPriority::from($this->bulkPriorityValue);
        $ids          = array_map('intval', $this->bulkSelected);
        $leads        = Lead::query()->visibleTo(auth()->user())->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            if ($lead->priority === $priorityEnum) {
                continue;
            }
            $previous       = $lead->priority?->value;
            $lead->priority = $priorityEnum;
            $lead->save();
            $audit->record($lead, 'lead.priority_changed', ['from' => $previous, 'to' => $priorityEnum->value]);
        }

        $count                   = $leads->count();
        $this->bulkSelected      = [];
        $this->bulkPriorityValue = '';
        $this->dispatch('toast', message: $count . ' ' . ($count === 1 ? 'lead' : 'leads') . ' updated.');
    }

    public function render(): View
    {
        $user = auth()->user();

        $base = Lead::query()->visibleTo($user);

        $leads = $this->applyFilters($base)
            ->orderBy(...$this->sortBy())
            ->paginate(config('lodgely.pagination.per_page'));

        $kpis = $this->kpis($base);

        $clientOptions = (clone $base)
            ->whereNotNull('client_name')
            ->distinct()
            ->orderBy('client_name')
            ->pluck('client_name')
            ->all();

        $sourceLabels = [
            'csv'        => 'CSV',
            'email_mock' => 'Email (mock)',
            'email_imap' => 'Email (IMAP)',
            'manual'     => 'Manual',
            'webhook'    => 'Webhook',
        ];

        $sourceOptions = (clone $base)
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->map(fn ($s) => [
                'value' => $s,
                'label' => $sourceLabels[$s] ?? ucwords(str_replace('_', ' ', $s)),
            ])
            ->all();

        $selected = $this->selectedLeadId
            ? (clone $base)->with(['notes.user', 'events.user', 'duplicateOf', 'import'])->find($this->selectedLeadId)
            : null;

        $savedFilters = SavedFilter::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.inbox.inbox-page', [
            'leads'           => $leads,
            'kpis'            => $kpis,
            'clientOptions'   => $clientOptions,
            'sourceOptions'   => $sourceOptions,
            'selected'        => $selected,
            'statusOptions'   => LeadStatus::options(),
            'priorityOptions' => LeadPriority::options(),
            'savedFilters'    => $savedFilters,
        ]);
    }

    private function applyFilterState(array $filters): void
    {
        $this->search   = $filters['search']   ?? '';
        $this->status   = $filters['status']   ?? '';
        $this->priority = $filters['priority'] ?? '';
        $this->source   = $filters['source']   ?? '';
        $this->client   = $filters['client']   ?? '';
        $this->sort     = $filters['sort']     ?? 'created_desc';
    }

    private function applyFilters($base): mixed
    {
        return (clone $base)
            ->search($this->search)
            ->when($this->status,   fn ($q, $v) => $q->where('status', $v))
            ->when($this->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($this->source,   fn ($q, $v) => $q->where('source', $v))
            ->when($this->client,   fn ($q, $v) => $q->whereRaw('LOWER(client_name) = ?', [mb_strtolower($v)]));
    }

    /** @return array{0:string, 1:string} */
    private function sortBy(): array
    {
        return match ($this->sort) {
            'created_asc'   => ['created_at', 'asc'],
            'priority_desc' => ['priority', 'desc'],
            default         => ['created_at', 'desc'],
        };
    }

    private function guardedLead(int $id): Lead
    {
        /** @var Lead $lead */
        $lead = Lead::query()->visibleTo(auth()->user())->findOrFail($id);

        return $lead;
    }

    private function kpis($base): array
    {
        $counts = (clone $base)
            ->selectRaw('
                COUNT(*) FILTER (WHERE status = ?) AS new_count,
                COUNT(*) FILTER (WHERE duplicate_flag = true) AS duplicate_count,
                COUNT(*) FILTER (WHERE status = ?) AS incomplete_count,
                COUNT(*) AS total_count
            ', [LeadStatus::New->value, LeadStatus::Incomplete->value])
            ->first();

        $bySource = (clone $base)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        return [
            'new'        => (int) ($counts->new_count ?? 0),
            'duplicates' => (int) ($counts->duplicate_count ?? 0),
            'incomplete' => (int) ($counts->incomplete_count ?? 0),
            'total'      => (int) ($counts->total_count ?? 0),
            'by_source'  => $bySource,
        ];
    }
}
