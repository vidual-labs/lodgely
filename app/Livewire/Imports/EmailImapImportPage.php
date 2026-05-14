<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Email\ImapLeadSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class EmailImapImportPage extends Component
{
    public ?string $defaultClient   = null;
    public ?string $defaultCampaign = null;
    public ?Import $lastImport      = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->defaultClient   = config('lodgely.importers.email.imap.default_client_name') ?: null;
        $this->defaultCampaign = config('lodgely.importers.email.imap.default_campaign_name') ?: null;
    }

    protected function rules(): array
    {
        return [
            'defaultClient'   => ['nullable', 'string', 'max:120'],
            'defaultCampaign' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function isConfigured(): bool
    {
        return ! empty(config('lodgely.importers.email.imap.host'));
    }

    public function runImport(ImportRunner $runner, ImapLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
        $this->validate();

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => 'IMAP pull · ' . now()->format('Y-m-d H:i'),
            'meta'      => array_filter([
                'default_client_name'   => $this->defaultClient,
                'default_campaign_name' => $this->defaultCampaign,
            ]),
        ]);

        $this->lastImport = $runner->run($import, $source, auth()->id());

        $this->dispatch('toast', message: "Pulled {$this->lastImport->rows_imported} email lead(s).");
    }

    public function render(): View
    {
        return view('livewire.imports.email-imap-import-page', [
            'recentImports' => Import::where('source', 'email_imap')->latest()->limit(10)->get(),
            'imapHost'      => config('lodgely.importers.email.imap.host'),
            'imapPort'      => config('lodgely.importers.email.imap.port'),
            'imapEncryption' => config('lodgely.importers.email.imap.encryption'),
            'imapMailbox'   => config('lodgely.importers.email.imap.mailbox'),
        ]);
    }
}
