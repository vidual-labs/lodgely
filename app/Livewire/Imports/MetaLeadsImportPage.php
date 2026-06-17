<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Meta\MetaLeadsSource;
use App\Models\AdPlatformSetting;
use App\Models\Import;
use App\Models\MetaLeadSource;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Operator UI for retrieving Meta (Facebook/Instagram) Lead Ads leads directly
 * from the Graph API, instead of bouncing them through a Google Sheet. Reuses
 * the Meta credentials configured in Settings → Ad platforms.
 *
 * Mirrors the Google Sheets import page: a list of configured sources plus an
 * add/edit form. "Load forms" validates the token + page access and lists the
 * page's lead gen forms so the operator can optionally pin one.
 */
#[Layout('components.layouts.app')]
class MetaLeadsImportPage extends Component
{
    public string $mode = 'list'; // 'list' | 'form'

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'label'                 => '',
        'page_id'               => '',
        'form_id'               => '',
        'form_name'             => '',
        'default_client_name'   => '',
        'default_campaign_name' => '',
        'lookback_days'         => 30,
        'refresh_hours'         => 24,
        'is_active'             => true,
    ];

    /** @var array<int, array{id:string, name:?string, status:?string}> */
    public array $availableForms = [];

    public bool $formsLoaded = false;

    public ?string $loadError = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->reset(['editingId', 'availableForms', 'formsLoaded', 'loadError']);
        $this->form = [
            'label'                 => '',
            'page_id'               => '',
            'form_id'               => '',
            'form_name'             => '',
            'default_client_name'   => '',
            'default_campaign_name' => '',
            'lookback_days'         => 30,
            'refresh_hours'         => 24,
            'is_active'             => true,
        ];
        $this->mode = 'form';
    }

    public function editSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'label'                 => $source->label,
            'page_id'               => (string) ($source->page_id ?? ''),
            'form_id'               => (string) ($source->form_id ?? ''),
            'form_name'             => (string) ($source->form_name ?? ''),
            'default_client_name'   => (string) ($source->default_client_name ?? ''),
            'default_campaign_name' => (string) ($source->default_campaign_name ?? ''),
            'lookback_days'         => $source->lookback_days,
            'refresh_hours'         => $source->refresh_hours,
            'is_active'             => (bool) $source->is_active,
        ];
        $this->reset(['availableForms', 'formsLoaded', 'loadError']);
        $this->mode = 'form';
    }

    public function loadForms(MetaLeadsSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->loadError = null;
        $this->formsLoaded = false;
        $this->availableForms = [];

        try {
            $this->availableForms = $source->availableForms(
                Tenant::DEFAULT_ID,
                trim((string) ($this->form['page_id'] ?? '')),
            );
            $this->formsLoaded = true;
        } catch (Throwable $e) {
            $this->loadError = $e->getMessage();
        }
    }

    public function pinForm(string $id): void
    {
        $this->form['form_id'] = $id;

        // Resolve the name from the loaded form list rather than trusting a value
        // round-tripped through the DOM (names can contain quotes/emoji).
        $name = collect($this->availableForms)->firstWhere('id', $id)['name'] ?? null;
        $this->form['form_name'] = (string) ($name ?? '');
    }

    public function saveSource(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $data = $this->validate([
            'form.label'                 => ['required', 'string', 'max:120'],
            'form.page_id'               => ['nullable', 'string', 'max:64'],
            'form.form_id'               => ['nullable', 'string', 'max:64'],
            'form.form_name'             => ['nullable', 'string', 'max:255'],
            'form.default_client_name'   => ['nullable', 'string', 'max:120'],
            'form.default_campaign_name' => ['nullable', 'string', 'max:120'],
            'form.lookback_days'         => ['required', 'integer', 'min:1', 'max:365'],
            'form.refresh_hours'         => ['required', 'integer', 'min:1', 'max:8760'],
            'form.is_active'             => ['boolean'],
        ])['form'];

        $pageId = trim((string) $data['page_id']);
        $formId = trim((string) $data['form_id']);

        if ($pageId === '' && $formId === '') {
            $this->addError('form.page_id', __('Enter a Page ID, or pin a specific Form ID.'));

            return;
        }

        $payload = [
            'tenant_id'             => Tenant::DEFAULT_ID,
            'label'                 => $data['label'],
            'page_id'               => $pageId ?: null,
            'form_id'               => $formId ?: null,
            'form_name'             => trim((string) $data['form_name']) ?: null,
            'default_client_name'   => $data['default_client_name'] ?: null,
            'default_campaign_name' => $data['default_campaign_name'] ?: null,
            'lookback_days'         => (int) $data['lookback_days'],
            'refresh_hours'         => (int) $data['refresh_hours'],
            'is_active'             => (bool) $data['is_active'],
        ];

        if ($this->editingId) {
            MetaLeadSource::forTenant(Tenant::DEFAULT_ID)
                ->findOrFail($this->editingId)
                ->update($payload);
        } else {
            MetaLeadSource::create($payload);
        }

        $this->mode = 'list';
        $this->dispatch('toast', message: __('Meta Lead Ads source saved.'), type: 'success');
    }

    public function deleteSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id)->delete();
        $this->dispatch('toast', message: __('Meta Lead Ads source deleted.'), type: 'success');
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function fetchNow(int $id, ImportRunner $runner, MetaLeadsSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $leadSource = MetaLeadSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => $leadSource->label.' · '.now()->format('Y-m-d H:i'),
            'meta'      => ['meta_lead_source_id' => $leadSource->id],
        ]);

        try {
            $result = $runner->run($import, $source);
            $leadSource->update(['last_fetched_at' => now()]);

            $this->dispatch('toast', message: __(
                'Fetched: :imported imported, :dup duplicates, :inv invalid.',
                [
                    'imported' => $result->rows_imported,
                    'dup'      => $result->rows_duplicate,
                    'inv'      => $result->rows_invalid,
                ]
            ), type: 'success');
        } catch (Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->reset(['editingId', 'availableForms', 'formsLoaded', 'loadError']);
    }

    public function render(): View
    {
        $sources = MetaLeadSource::forTenant(Tenant::DEFAULT_ID)
            ->latest()
            ->get();

        $recentImports = Import::where('source', 'meta_leads')
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.imports.meta-leads-import-page', [
            'sources'         => $sources,
            'recentImports'   => $recentImports,
            'metaConnected'   => AdPlatformSetting::resolveSafe(Tenant::DEFAULT_ID)->isMetaConnected(),
            'adPlatformsUrl'  => route('settings.ad-platforms'),
            'lookbackOptions' => [
                7   => __('Last 7 days'),
                30  => __('Last 30 days'),
                90  => __('Last 90 days'),
                365 => __('Last 365 days'),
            ],
            'refreshOptions'  => [
                1   => __('Every hour'),
                6   => __('Every 6 hours'),
                12  => __('Every 12 hours'),
                24  => __('Every 24 hours'),
                48  => __('Every 2 days'),
                168 => __('Weekly'),
            ],
        ]);
    }
}
