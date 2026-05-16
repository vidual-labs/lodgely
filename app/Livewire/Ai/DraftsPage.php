<?php

namespace App\Livewire\Ai;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Services\AiSummarizer;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\Tenant;
use App\Support\Audit\AiAuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DraftsPage extends Component
{
    #[Url(except: 'pending')]
    public string $filter = 'pending';            // pending | approved | shared | rejected | failed | all

    public ?int $selectedId = null;

    public ?string $rejectReason = '';

    public function mount(): void
    {
        $this->guardOperator();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->rejectReason = '';
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->rejectReason = '';
    }

    public function approve(int $id, AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $row = $this->guardedRow($id);
        if (! $row->response) {
            return;
        }

        $row->forceFill([
            'status'      => AiSummaryStatus::Approved->value,
            'operator_id' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $audit->record($row, 'ai.summary.approved');

        $this->dispatch('toast', message: __('Summary approved.'), type: 'success');
    }

    public function reject(int $id, AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $row = $this->guardedRow($id);

        $row->forceFill([
            'status'      => AiSummaryStatus::Rejected->value,
            'operator_id' => auth()->id(),
        ])->save();

        $audit->record($row, 'ai.summary.rejected', [
            'reason' => mb_substr(trim((string) $this->rejectReason), 0, 400) ?: null,
        ]);

        $this->rejectReason = '';
        $this->dispatch('toast', message: __('Summary rejected.'), type: 'success');
    }

    public function share(int $id, AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $row = $this->guardedRow($id);

        if ($row->status !== AiSummaryStatus::Approved) {
            $this->dispatch('toast', message: __('Approve a summary before sharing it.'), type: 'error');
            return;
        }

        $row->forceFill([
            'status'    => AiSummaryStatus::Shared->value,
            'shared_at' => now(),
        ])->save();

        $audit->record($row, 'ai.summary.shared');

        $this->dispatch('toast', message: __('Shared with client.'), type: 'success');
    }

    public function regenerate(int $id, AiSummarizer $summarizer, AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $row = $this->guardedRow($id);

        // Reset the response and re-dispatch the job using the existing prompt.
        $row->forceFill([
            'status'      => AiSummaryStatus::Pending->value,
            'response'    => null,
            'error'       => null,
            'token_usage' => null,
        ])->save();

        $audit->record($row, 'ai.summary.requested', [
            'regenerated_from' => $row->id,
        ]);

        GenerateAiSummary::dispatch($row->id);

        $this->dispatch('toast', message: __('Regeneration queued.'), type: 'success');
    }

    public function render(): View
    {
        $query = AiSummary::query()
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->with(['requestedBy', 'operator'])
            ->latest();

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $rows = $query->paginate(20);

        $selected = $this->selectedId ? AiSummary::with(['requestedBy', 'operator'])->find($this->selectedId) : null;
        if ($selected && (int) $selected->tenant_id !== Tenant::DEFAULT_ID) {
            $selected = null;
        }

        return view('livewire.ai.drafts-page', [
            'rows'      => $rows,
            'selected'  => $selected,
            'kinds'     => AiSummaryKind::cases(),
            'statuses'  => AiSummaryStatus::cases(),
        ]);
    }

    private function guardedRow(int $id): AiSummary
    {
        /** @var AiSummary $row */
        $row = AiSummary::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id);

        return $row;
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
