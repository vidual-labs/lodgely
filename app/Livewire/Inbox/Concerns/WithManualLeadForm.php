<?php

namespace App\Livewire\Inbox\Concerns;

use App\Domain\Leads\Services\LeadIngestor;
use App\Models\Tenant;

/**
 * "New lead" modal state and submission for operators.
 *
 * Pushes the payload through {@see LeadIngestor} so the central
 * dedup / retention / audit pipeline still runs.
 */
trait WithManualLeadForm
{
    public bool $showManualForm = false;

    public array $manual = [
        'client_name' => '',
        'campaign_name' => '',
        'full_name' => '',
        'email' => '',
        'phone' => '',
        'message' => '',
        'priority' => 'medium',
    ];

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

    public function saveManual(LeadIngestor $ingestor): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $data = $this->validate([
            'manual.client_name' => ['nullable', 'string', 'max:120'],
            'manual.campaign_name' => ['nullable', 'string', 'max:120'],
            'manual.full_name' => ['nullable', 'string', 'max:120'],
            'manual.email' => ['nullable', 'email', 'max:160'],
            'manual.phone' => ['nullable', 'string', 'max:60'],
            'manual.message' => ['nullable', 'string', 'max:5000'],
            'manual.priority' => ['required', 'in:low,medium,high'],
        ])['manual'];

        if (! $data['email'] && ! $data['phone'] && ! $data['full_name']) {
            $this->addError('manual.full_name', __('Provide at least a name, email, or phone.'));

            return;
        }

        $ingestor->ingest([
            'source' => 'manual',
            'client_name' => $data['client_name'] ?: null,
            'campaign_name' => $data['campaign_name'] ?: null,
            'full_name' => $data['full_name'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'message' => $data['message'] ?: null,
            'priority' => $data['priority'],
        ], null, Tenant::DEFAULT_ID, auth()->id());

        $this->closeManualForm();
        $this->resetPage();
        $this->dispatch('toast', message: __('Lead added.'));
    }
}
