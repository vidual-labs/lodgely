<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Csv\CsvLeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class CsvImportPage extends Component
{
    use WithFileUploads;

    public $file;
    public ?string $defaultClient = null;
    public ?string $defaultCampaign = null;

    public ?Import $lastImport = null;

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'defaultClient'   => ['nullable', 'string', 'max:120'],
            'defaultCampaign' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    public function submit(ImportRunner $runner, CsvLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
        $this->validate();

        $storedPath = $this->file->store('imports', 'local');
        $absolute   = storage_path('app/private/'.$storedPath);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => $this->file->getClientOriginalName(),
            'reference' => $storedPath,
            'meta'      => [
                'path' => $absolute,
                'default_client_name'   => $this->defaultClient,
                'default_campaign_name' => $this->defaultCampaign,
            ],
        ]);

        $this->lastImport = $runner->run($import, $source, auth()->id());

        $this->reset(['file', 'defaultClient', 'defaultCampaign']);
        $this->dispatch('toast', message: "Imported {$this->lastImport->rows_imported} of {$this->lastImport->rows_total} rows.");
    }

    public function render(): View
    {
        return view('livewire.imports.csv-import-page', [
            'recentImports' => Import::where('source', 'csv')->latest()->limit(10)->get(),
        ]);
    }
}
