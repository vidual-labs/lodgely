<?php

namespace App\Livewire\Inbox;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Services\AiSummarizer;
use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\DuplicateDetector;
use App\Domain\Leads\Services\LeadKpis;
use App\Livewire\Inbox\Concerns\WithBulkLeadActions;
use App\Livewire\Inbox\Concerns\WithLeadFilters;
use App\Livewire\Inbox\Concerns\WithManualLeadForm;
use App\Livewire\Inbox\Concerns\WithSavedFilters;
use App\Models\AiSummary;
use App\Models\Lead;
use App\Models\Tenant;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class InboxPage extends Component
{
    use WithBulkLeadActions;
    use WithLeadFilters;
    use WithManualLeadForm;
    use WithPagination;
    use WithSavedFilters;

    private const SOURCE_LABELS = [
        'csv' => 'CSV',
        'email_mock' => 'Email (mock)',
        'email_imap' => 'Email (IMAP)',
        'manual' => 'Manual',
        'webhook' => 'Webhook',
    ];

    public ?int $selectedLeadId = null;

    public ?string $newNoteBody = null;

    protected function rules(): array
    {
        return [
            'newNoteBody' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function mount(): void
    {
        if (! request()->hasAny(self::FILTER_URL_KEYS)) {
            $this->loadDefaultSavedFilter();
        }
    }

    public function updating($name): void
    {
        if (in_array($name, self::FILTER_PROPERTIES, true)) {
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
            'to' => $status->value,
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
            'to' => $priority->value,
        ]);
    }

    public function reconcileDuplicate(int $leadId, DuplicateDetector $detector, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $lead = $this->guardedLead($leadId);
        if ($detector->reconcile($lead)) {
            $audit->record($lead, 'lead.duplicate_reconciled', [
                'duplicate_flag' => $lead->duplicate_flag,
                'duplicate_of' => $lead->duplicate_of_id,
            ]);
        }
    }

    public function evaluateLeadWithAi(int $leadId, AiSummarizer $summarizer): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $lead = $this->guardedLead($leadId);

        try {
            $summarizer->requestLeadQualification($lead, auth()->user());
            $this->dispatch('toast', message: __('AI evaluation queued. Review it in AI drafts.'), type: 'success');
        } catch (AiDisabledException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
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
            'body' => $body,
        ]);

        $audit->record($lead, 'lead.note_added', ['note_id' => $note->id]);
        $this->newNoteBody = null;
    }

    public function render(LeadKpis $kpis): View
    {
        $user = auth()->user();

        $base = Lead::query()->visibleTo($user);

        $leads = $this->applyFilters($base)
            ->orderBy(...$this->sortBy())
            ->paginate(config('lodgely.pagination.per_page'));

        $clientOptions = (clone $base)
            ->whereNotNull('client_name')
            ->distinct()
            ->orderBy('client_name')
            ->pluck('client_name')
            ->all();

        $sourceOptions = (clone $base)
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->map(fn ($s) => [
                'value' => $s,
                'label' => self::SOURCE_LABELS[$s] ?? ucwords(str_replace('_', ' ', $s)),
            ])
            ->all();

        $selected = $this->selectedLeadId
            ? (clone $base)->with(['notes.user', 'events.user', 'duplicateOf', 'import'])->find($this->selectedLeadId)
            : null;

        $leadAiSummary = null;
        if ($selected && $user?->isOperator() && config('lodgely.ai.enabled')) {
            $leadAiSummary = AiSummary::query()
                ->where('tenant_id', Tenant::DEFAULT_ID)
                ->where('kind', AiSummaryKind::LeadQualification->value)
                ->where('subject_type', Lead::class)
                ->where('subject_id', $selected->id)
                ->whereIn('status', [AiSummaryStatus::Approved->value, AiSummaryStatus::Shared->value])
                ->with('operator')
                ->latest('approved_at')
                ->first();
        }

        return view('livewire.inbox.inbox-page', [
            'leads' => $leads,
            'kpis' => $kpis->compute($base),
            'clientOptions' => $clientOptions,
            'sourceOptions' => $sourceOptions,
            'selected' => $selected,
            'statusOptions' => LeadStatus::options(),
            'priorityOptions' => LeadPriority::options(),
            'savedFilters' => $this->userSavedFilters(),
            'leadAiSummary' => $leadAiSummary,
        ]);
    }

    private function guardedLead(int $id): Lead
    {
        /** @var Lead $lead */
        $lead = Lead::query()->visibleTo(auth()->user())->findOrFail($id);

        return $lead;
    }
}
