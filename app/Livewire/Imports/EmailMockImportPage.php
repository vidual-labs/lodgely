<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\EmailMock\EmailMockLeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class EmailMockImportPage extends Component
{
    public int $count = 5;
    public ?string $defaultClient = null;
    public ?string $defaultCampaign = 'Website contact form';
    public ?Import $lastImport = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    protected function rules(): array
    {
        return [
            'count'           => ['required', 'integer', 'min:1', 'max:50'],
            'defaultClient'   => ['nullable', 'string', 'max:120'],
            'defaultCampaign' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function runImport(ImportRunner $runner, EmailMockLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
        $this->validate();

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => 'Mock email pull · '.now()->format('Y-m-d H:i'),
            'meta'      => [
                'count'                 => $this->count,
                'default_client_name'   => $this->defaultClient,
                'default_campaign_name' => $this->defaultCampaign,
            ],
        ]);

        $this->lastImport = $runner->run($import, $source, auth()->id());

        $this->dispatch('toast', message: "Pulled {$this->lastImport->rows_imported} mock email lead(s).");
    }

    public function render(): View
    {
        return view('livewire.imports.email-mock-import-page', [
            'recentImports' => Import::where('source', 'email_mock')->latest()->limit(10)->get(),
        ]);
    }
}
