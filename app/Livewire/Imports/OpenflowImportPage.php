<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Import;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Operator UI for pulling leads from an OpenFlow form into lodgely.
 *
 * Mirrors the Google Sheets / Meta Lead Ads import pages: a list of configured
 * sources plus an add/edit form. "Load forms" validates the login and lists the
 * account's forms; "Load fields" fetches the picked form's fields so the
 * operator can map each one to a lead column. Everything routes through the
 * standard ImportRunner → LeadIngestor path.
 */
#[Layout('components.layouts.app')]
class OpenflowImportPage extends Component
{
    public string $mode = 'list'; // 'list' | 'form'

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'label'                 => '',
        'base_url'              => '',
        'api_token'             => '',
        'email'                 => '',
        'password'              => '',
        'form_id'               => '',
        'form_name'             => '',
        'default_client_name'   => '',
        'default_campaign_name' => '',
        'refresh_hours'         => 24,
        'is_active'             => true,
    ];

    /** @var array<int, array{id:string, title:?string, submission_count:int}> */
    public array $availableForms = [];

    public bool $formsLoaded = false;

    /**
     * One row per OpenFlow field:
     * ['id' => string, 'label' => string, 'type' => ?string, 'field' => string, 'custom_key' => string]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $mappedFields = [];

    public bool $fieldsLoaded = false;

    public ?string $loadError = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->reset(['editingId', 'availableForms', 'formsLoaded', 'mappedFields', 'fieldsLoaded', 'loadError']);
        $this->form = [
            'label'                 => '',
            'base_url'              => '',
            'api_token'             => '',
            'email'                 => '',
            'password'              => '',
            'form_id'               => '',
            'form_name'             => '',
            'default_client_name'   => '',
            'default_campaign_name' => '',
            'refresh_hours'         => 24,
            'is_active'             => true,
        ];
        $this->mode = 'form';
    }

    public function editSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = OpenflowSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'label'                 => $source->label,
            'base_url'              => (string) $source->base_url,
            'api_token'             => '', // never round-trip the stored secret
            'email'                 => (string) ($source->email ?? ''),
            'password'              => '', // never round-trip the stored secret
            'form_id'               => (string) $source->form_id,
            'form_name'             => (string) ($source->form_name ?? ''),
            'default_client_name'   => (string) ($source->default_client_name ?? ''),
            'default_campaign_name' => (string) ($source->default_campaign_name ?? ''),
            'refresh_hours'         => $source->refresh_hours,
            'is_active'             => (bool) $source->is_active,
        ];

        // Restore the saved mapping so it shows without a round-trip. Labels
        // fall back to the field id until the operator clicks "Load fields".
        $this->mappedFields = [];
        foreach ((is_array($source->field_map) ? $source->field_map : []) as $fieldId => $target) {
            $target = (string) $target;
            $customKey = '';
            if (str_starts_with($target, 'custom_answer:')) {
                $customKey = substr($target, strlen('custom_answer:'));
                $target = 'custom_answer';
            }
            $this->mappedFields[] = [
                'id'         => (string) $fieldId,
                'label'      => (string) $fieldId,
                'type'       => null,
                'field'      => $target,
                'custom_key' => $customKey,
            ];
        }
        $this->fieldsLoaded = ! empty($this->mappedFields);
        $this->reset(['availableForms', 'formsLoaded', 'loadError']);
        $this->mode = 'form';
    }

    public function loadForms(OpenflowLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->loadError = null;
        $this->formsLoaded = false;
        $this->availableForms = [];

        [$token, $email, $password] = $this->resolveAuth();
        if (! $this->hasUsableAuth($token, $email, $password)) {
            $this->loadError = __('Enter an API token, or an email and password, before loading forms.');

            return;
        }

        try {
            $this->availableForms = $source->availableForms(
                trim((string) $this->form['base_url']),
                $token,
                $email,
                $password,
            );
            $this->formsLoaded = true;
        } catch (Throwable $e) {
            $this->loadError = $e->getMessage();
        }
    }

    public function pinForm(string $id): void
    {
        $this->form['form_id'] = $id;

        $title = collect($this->availableForms)->firstWhere('id', $id)['title'] ?? null;
        $this->form['form_name'] = (string) ($title ?? '');

        // A different form invalidates any previously loaded field mapping.
        $this->mappedFields = [];
        $this->fieldsLoaded = false;
    }

    public function loadFields(OpenflowLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->loadError = null;

        $formId = trim((string) $this->form['form_id']);
        if ($formId === '') {
            $this->loadError = __('Pick a form (or enter a Form ID) before loading fields.');

            return;
        }

        [$token, $email, $password] = $this->resolveAuth();
        if (! $this->hasUsableAuth($token, $email, $password)) {
            $this->loadError = __('Enter an API token, or an email and password, before loading fields.');

            return;
        }

        try {
            $form = $source->availableFields(
                trim((string) $this->form['base_url']),
                $token,
                $email,
                $password,
                $formId,
            );
        } catch (Throwable $e) {
            $this->loadError = $e->getMessage();

            return;
        }

        if (trim((string) $this->form['form_name']) === '' && $form['title']) {
            $this->form['form_name'] = (string) $form['title'];
        }

        // Preserve any existing per-field choices while refreshing labels/order.
        $existing = collect($this->mappedFields)->keyBy('id');

        $this->mappedFields = [];
        foreach ($form['fields'] as $field) {
            $prior = $existing->get($field['id']);
            $this->mappedFields[] = [
                'id'         => $field['id'],
                'label'      => $field['label'],
                'type'       => $field['type'],
                'field'      => (string) ($prior['field'] ?? $this->autoMapField($field)),
                'custom_key' => (string) ($prior['custom_key'] ?? ''),
            ];
        }
        $this->fieldsLoaded = true;
    }

    public function saveSource(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $data = $this->validate([
            'form.label'                 => ['required', 'string', 'max:120'],
            'form.base_url'              => ['required', 'url', 'max:255'],
            'form.api_token'             => ['nullable', 'string', 'max:255'],
            'form.email'                 => ['nullable', 'email', 'max:255'],
            'form.password'              => ['nullable', 'string', 'max:255'],
            'form.form_id'               => ['required', 'string', 'max:64'],
            'form.form_name'             => ['nullable', 'string', 'max:255'],
            'form.default_client_name'   => ['nullable', 'string', 'max:120'],
            'form.default_campaign_name' => ['nullable', 'string', 'max:120'],
            'form.refresh_hours'         => ['required', 'integer', 'min:1', 'max:8760'],
            'form.is_active'             => ['boolean'],
        ])['form'];

        $token = trim((string) $data['api_token']);
        $email = trim((string) $data['email']);
        $password = (string) $data['password'];

        // Require *some* credential: a token, or an email + password login. On
        // edit, blanks keep the stored secrets, so an existing source already
        // satisfies this.
        if (! $this->editingId && $token === '' && ! ($email !== '' && $password !== '')) {
            $this->addError('form.api_token', __('Provide an API token, or an email and password.'));

            return;
        }

        // A password without a token needs an email to log in with.
        if ($token === '' && $password !== '' && $email === '') {
            $this->addError('form.email', __('An email is required to use a password login.'));

            return;
        }

        $fieldMap = $this->buildFieldMap();

        $source = $this->editingId
            ? OpenflowSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($this->editingId)
            : new OpenflowSource(['tenant_id' => Tenant::DEFAULT_ID]);

        $source->fill([
            'label'                 => $data['label'],
            'base_url'              => rtrim(trim($data['base_url']), '/'),
            'email'                 => $email ?: null,
            'form_id'               => trim($data['form_id']),
            'form_name'             => trim((string) $data['form_name']) ?: null,
            'field_map'             => $fieldMap ?: null,
            'default_client_name'   => $data['default_client_name'] ?: null,
            'default_campaign_name' => $data['default_campaign_name'] ?: null,
            'refresh_hours'         => (int) $data['refresh_hours'],
            'is_active'             => (bool) $data['is_active'],
        ]);

        if ($token !== '') {
            $source->setApiToken($token);
        }
        if ($password !== '') {
            $source->setPassword($password);
        }

        $source->save();

        $this->mode = 'list';
        $this->dispatch('toast', message: __('OpenFlow source saved.'), type: 'success');
    }

    public function deleteSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        OpenflowSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id)->delete();
        $this->dispatch('toast', message: __('OpenFlow source deleted.'), type: 'success');
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = OpenflowSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function fetchNow(int $id, ImportRunner $runner, OpenflowLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $openflowSource = OpenflowSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => $openflowSource->label.' · '.now()->format('Y-m-d H:i'),
            'meta'      => ['openflow_source_id' => $openflowSource->id],
        ]);

        try {
            $result = $runner->run($import, $source);
            $openflowSource->update(['last_fetched_at' => now()]);

            $this->dispatch('toast', message: __(
                'Fetched: :imported imported, :skipped skipped, :dup duplicates, :inv invalid.',
                [
                    'imported' => $result->rows_imported,
                    'skipped'  => $result->rows_skipped,
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
        $this->reset(['editingId', 'availableForms', 'formsLoaded', 'mappedFields', 'fieldsLoaded', 'loadError']);
    }

    public function render(): View
    {
        $sources = OpenflowSource::forTenant(Tenant::DEFAULT_ID)
            ->latest()
            ->get();

        $recentImports = Import::where('source', 'openflow')
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.imports.openflow-import-page', [
            'sources'        => $sources,
            'recentImports'  => $recentImports,
            'leadFields'     => OpenflowSource::leadFields(),
            'refreshOptions' => [
                1   => __('Every hour'),
                6   => __('Every 6 hours'),
                12  => __('Every 12 hours'),
                24  => __('Every 24 hours'),
                48  => __('Every 2 days'),
                168 => __('Weekly'),
            ],
        ]);
    }

    /**
     * Resolve the credentials to use for a live API call (Load forms / fields):
     * freshly typed values win, falling back to the stored secrets when editing
     * and a field was left blank.
     *
     * @return array{0:?string, 1:?string, 2:?string} [apiToken, email, password]
     */
    private function resolveAuth(): array
    {
        $stored = $this->editingId
            ? OpenflowSource::forTenant(Tenant::DEFAULT_ID)->find($this->editingId)
            : null;

        $token = trim((string) ($this->form['api_token'] ?? ''));
        if ($token === '' && $stored) {
            $token = (string) ($stored->apiToken() ?? '');
        }

        $email = trim((string) ($this->form['email'] ?? ''));
        if ($email === '' && $stored) {
            $email = (string) ($stored->email ?? '');
        }

        $password = $this->resolvePassword();

        return [$token !== '' ? $token : null, $email !== '' ? $email : null, $password];
    }

    private function hasUsableAuth(?string $token, ?string $email, ?string $password): bool
    {
        return $token !== null
            || ($email !== null && $email !== '' && $password !== null && $password !== '');
    }

    /**
     * Resolve the password to use for a live API call: the freshly typed value,
     * or the stored one when editing and the field was left blank. Null means
     * "no password available".
     */
    private function resolvePassword(): ?string
    {
        $typed = trim((string) ($this->form['password'] ?? ''));
        if ($typed !== '') {
            return $typed;
        }

        if ($this->editingId) {
            $stored = OpenflowSource::forTenant(Tenant::DEFAULT_ID)->find($this->editingId)?->password();
            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        }

        return null;
    }

    /**
     * Compile the per-field choices into the stored {fieldId: target} map.
     *
     * @return array<string, string>
     */
    private function buildFieldMap(): array
    {
        $map = [];
        foreach ($this->mappedFields as $row) {
            $fieldId = trim((string) ($row['id'] ?? ''));
            $field = trim((string) ($row['field'] ?? ''));
            if ($fieldId === '' || $field === '') {
                continue;
            }
            if ($field === 'custom_answer') {
                $key = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string) ($row['custom_key'] ?? ''))));
                $key = trim((string) $key, '_');
                if ($key === '') {
                    continue; // skip unnamed custom-answer rows
                }
                $field = 'custom_answer:'.$key;
            }
            $map[$fieldId] = $field;
        }

        return $map;
    }

    /**
     * Suggest a lead field for an OpenFlow field based on its declared type and
     * label. Returns '' when nothing confident matches (operator picks manually).
     *
     * @param  array{id:string, label:string, type:?string}  $field
     */
    private function autoMapField(array $field): string
    {
        $type = strtolower((string) ($field['type'] ?? ''));
        if ($type === 'email') {
            return 'email';
        }
        if ($type === 'phone') {
            return 'phone';
        }

        $label = strtolower(trim((string) $field['label']));
        $label = trim((string) preg_replace('/[^a-z0-9]+/', '_', $label), '_');

        return match (true) {
            in_array($label, ['email', 'e_mail', 'email_address'], true)                 => 'email',
            in_array($label, ['phone', 'phone_number', 'telephone', 'mobile', 'tel'], true) => 'phone',
            in_array($label, ['name', 'full_name', 'your_name', 'contact_name'], true)    => 'full_name',
            in_array($label, ['message', 'comments', 'comment', 'notes', 'note'], true)   => 'message',
            default                                                                       => '',
        };
    }
}
