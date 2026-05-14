<?php

namespace App\Livewire\Inbox;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\DuplicateDetector;
use App\Models\Lead;
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

    public ?string $newNoteBody = null;

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

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'priority', 'source', 'client', 'sort'], true)) {
            $this->resetPage();
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
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->source = '';
        $this->client = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();

        $base = Lead::query()->visibleTo($user);

        $leads = (clone $base)
            ->search($this->search)
            ->when($this->status,   fn ($q, $v) => $q->where('status', $v))
            ->when($this->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($this->source,   fn ($q, $v) => $q->where('source', $v))
            ->when($this->client,   fn ($q, $v) => $q->whereRaw('LOWER(client_name) = ?', [mb_strtolower($v)]))
            ->orderBy(... $this->sortBy())
            ->paginate(config('lodgely.pagination.per_page'));

        $kpis = $this->kpis($base);

        $clientOptions = (clone $base)
            ->whereNotNull('client_name')
            ->distinct()
            ->orderBy('client_name')
            ->pluck('client_name')
            ->all();

        $selected = $this->selectedLeadId
            ? (clone $base)->with(['notes.user', 'events.user', 'duplicateOf', 'import'])->find($this->selectedLeadId)
            : null;

        return view('livewire.inbox.inbox-page', [
            'leads'         => $leads,
            'kpis'          => $kpis,
            'clientOptions' => $clientOptions,
            'selected'      => $selected,
            'statusOptions'   => LeadStatus::options(),
            'priorityOptions' => LeadPriority::options(),
        ]);
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
