<?php

namespace App\Livewire\Webhooks;

use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WebhooksPage extends Component
{
    public bool $showForm = false;
    public ?int $revealedId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'label'                => '',
        'default_client_name'  => '',
        'default_campaign_name' => '',
    ];

    public function mount(): void
    {
        $this->guardOperator();
    }

    // ------------------------------------------------------------------ form

    public function openCreate(): void
    {
        $this->guardOperator();
        $this->form = ['label' => '', 'default_client_name' => '', 'default_campaign_name' => ''];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function close(): void
    {
        $this->showForm = false;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->guardOperator();

        $data = $this->validate([
            'form.label'                => ['required', 'string', 'max:120'],
            'form.default_client_name'  => ['nullable', 'string', 'max:255'],
            'form.default_campaign_name' => ['nullable', 'string', 'max:255'],
        ])['form'];

        WebhookEndpoint::create([
            'tenant_id'             => Tenant::DEFAULT_ID,
            'user_id'               => auth()->id(),
            'token'                 => Str::random(48),
            'label'                 => trim($data['label']),
            'default_client_name'   => filled($data['default_client_name']) ? trim($data['default_client_name']) : null,
            'default_campaign_name' => filled($data['default_campaign_name']) ? trim($data['default_campaign_name']) : null,
            'is_active'             => true,
        ]);

        $this->close();
        $this->dispatch('toast', message: 'Webhook endpoint created.');
    }

    public function toggleActive(int $id): void
    {
        $this->guardOperator();
        $endpoint = WebhookEndpoint::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id);
        $endpoint->update(['is_active' => ! $endpoint->is_active]);
    }

    public function delete(int $id): void
    {
        $this->guardOperator();
        WebhookEndpoint::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id)->delete();
        if ($this->revealedId === $id) {
            $this->revealedId = null;
        }
        $this->dispatch('toast', message: 'Webhook endpoint deleted.');
    }

    public function revealToken(int $id): void
    {
        $this->guardOperator();
        // Only toggle reveal for endpoints this tenant owns
        WebhookEndpoint::where('tenant_id', Tenant::DEFAULT_ID)->findOrFail($id);
        $this->revealedId = ($this->revealedId === $id) ? null : $id;
    }

    // ---------------------------------------------------------------- render

    public function render(): View
    {
        $endpoints = WebhookEndpoint::where('tenant_id', Tenant::DEFAULT_ID)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.webhooks.webhooks-page', [
            'endpoints' => $endpoints,
        ]);
    }

    // -------------------------------------------------------------- helpers

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
