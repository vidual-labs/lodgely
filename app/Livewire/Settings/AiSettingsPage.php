<?php

namespace App\Livewire\Settings;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Services\AiSummarizer;
use App\Models\AiSetting;
use App\Models\Tenant;
use App\Providers\AppServiceProvider;
use App\Support\Audit\AiAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AiSettingsPage extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        'enabled'           => false,
        'provider'          => '',
        'base_url'          => '',
        'api_key'           => '',                 // write-only; blank means "leave as-is"
        'has_existing_key'  => false,
        'model'             => '',
        'house_style'       => '',
        'kinds_enabled'     => [
            'report_view'        => false,
            'lead_qualification' => false,
        ],
        'lead_data_consent' => false,
        'temperature'       => null,
    ];

    public ?string $testResult = null;

    public function mount(): void
    {
        $this->guardOperator();
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);

        $this->form = [
            'enabled'           => (bool) $row->enabled,
            'provider'          => (string) ($row->provider ?? ''),
            'base_url'          => (string) ($row->base_url ?? ''),
            'api_key'           => '',
            'has_existing_key'  => (bool) $row->api_key_encrypted,
            'model'             => (string) ($row->model ?? ''),
            'house_style'       => (string) ($row->house_style ?? ''),
            'kinds_enabled'     => array_merge(
                ['report_view' => false, 'lead_qualification' => false],
                (array) ($row->kinds_enabled ?? []),
            ),
            'lead_data_consent' => (bool) $row->lead_data_consent,
            'temperature'       => $row->temperature,
        ];
    }

    public function save(AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $data = $this->validate([
            'form.enabled'           => ['boolean'],
            'form.provider'          => ['nullable', 'string', Rule::in(array_keys(AppServiceProvider::LLM_PROVIDERS))],
            'form.base_url'          => ['nullable', 'string', 'max:255'],
            'form.api_key'           => ['nullable', 'string', 'max:500'],
            'form.model'             => ['nullable', 'string', 'max:120'],
            'form.house_style'       => ['nullable', 'string', 'max:2000'],
            'form.kinds_enabled.report_view'        => ['boolean'],
            'form.kinds_enabled.lead_qualification' => ['boolean'],
            'form.lead_data_consent' => ['boolean'],
            'form.temperature'       => ['nullable', 'numeric', 'min:0', 'max:2'],
        ])['form'];

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);

        $row->enabled           = (bool) $data['enabled'];
        $row->provider          = $data['provider'] ?: null;
        $row->base_url          = $data['base_url'] ?: null;
        $row->model             = $data['model']    ?: null;
        $row->house_style       = $data['house_style'] ?: null;
        $row->kinds_enabled     = [
            'report_view'        => (bool) ($data['kinds_enabled']['report_view'] ?? false),
            'lead_qualification' => (bool) ($data['kinds_enabled']['lead_qualification'] ?? false),
        ];
        $row->lead_data_consent = (bool) $data['lead_data_consent'];
        $row->temperature       = $data['temperature'] !== null && $data['temperature'] !== ''
            ? (float) $data['temperature']
            : null;

        if (! empty($data['api_key'])) {
            $row->setApiKey($data['api_key']);
        }

        $row->save();

        $audit->recordSettings(Tenant::DEFAULT_ID, 'ai.settings.updated', [
            'enabled'           => $row->enabled,
            'provider'          => $row->provider,
            'model'             => $row->model,
            'kinds_enabled'     => $row->kinds_enabled,
            'lead_data_consent' => $row->lead_data_consent,
        ]);

        $this->loadFromDb();
        $this->dispatch('toast', message: __('AI settings saved.'), type: 'success');
    }

    public function testConnection(AiSummarizer $summarizer): void
    {
        $this->guardOperator();

        // Save unsaved provider/url/key first by reloading the current row.
        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);

        if (! $row->provider) {
            $this->testResult = 'error:'.__('Pick a provider first.');
            return;
        }

        try {
            $provider = $summarizer->providerFor($row);
            $ok = $provider->ping($row);
            $this->testResult = $ok
                ? 'success:'.__('Connected — :label responded.', ['label' => $provider->label()])
                : 'error:'.__('Could not reach the provider — check base URL and API key.');
        } catch (\Throwable $e) {
            $this->testResult = 'error:'.$e->getMessage();
        }
    }

    public function clearApiKey(AiAuditLogger $audit): void
    {
        $this->guardOperator();

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->setApiKey(null);
        $row->save();

        $audit->recordSettings(Tenant::DEFAULT_ID, 'ai.settings.updated', [
            'cleared_api_key' => true,
        ]);

        $this->loadFromDb();
        $this->dispatch('toast', message: __('API key cleared.'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.settings.ai-settings-page', [
            'providerOptions' => array_map(
                static fn (string $class, string $key) => [
                    'value' => $key,
                    'label' => app($class)->label(),
                ],
                array_values(AppServiceProvider::LLM_PROVIDERS),
                array_keys(AppServiceProvider::LLM_PROVIDERS),
            ),
            'kindOptions' => AiSummaryKind::options(),
            'defaultUrls' => [
                'openai_compatible' => config('lodgely.ai.defaults.openai_compatible.base_url'),
                'ollama'            => config('lodgely.ai.defaults.ollama.base_url'),
            ],
            'defaultModels' => [
                'openai_compatible' => config('lodgely.ai.defaults.openai_compatible.model'),
                'ollama'            => config('lodgely.ai.defaults.ollama.model'),
            ],
        ]);
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
