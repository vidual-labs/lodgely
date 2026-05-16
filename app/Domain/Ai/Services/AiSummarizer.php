<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Contracts\LlmProvider;
use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Support\Pseudonymizer;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\ClientReportingView;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Audit\AiAuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place that knows how to turn a request from a Livewire surface
 * into a pending AiSummary row + a queued job. Provider implementations
 * are resolved by the `ai_settings.provider` key.
 */
class AiSummarizer
{
    public function __construct(
        private PromptBuilder $prompts,
        private ReportSummaryDataAssembler $reportData,
        private Pseudonymizer $pseudonymizer,
        private AiAuditLogger $audit,
    ) {}

    /**
     * Create a pending AiSummary for a custom reporting view and dispatch
     * the generation job. Returns the created row so the UI can link to it.
     */
    public function requestReportSummary(
        ClientReportingView $view,
        User $requester,
        string $from,
        string $to,
    ): AiSummary {
        $tenantId = (int) $view->tenant_id;
        $settings = $this->settingsOrFail($tenantId);
        $this->ensureKindAvailable($settings, AiSummaryKind::ReportView);

        $data = $this->reportData->assemble($view, $requester, $tenantId, $from, $to);
        $req  = $this->prompts->build(AiSummaryKind::ReportView, $settings, $data);

        $summary = DB::transaction(function () use ($view, $requester, $tenantId, $from, $to, $req, $settings) {
            $row = AiSummary::create([
                'tenant_id'    => $tenantId,
                'kind'         => AiSummaryKind::ReportView->value,
                'subject_type' => ClientReportingView::class,
                'subject_id'   => $view->id,
                'period_start' => $from,
                'period_end'   => $to,
                'prompt'       => "[SYSTEM]\n".$req->system."\n\n[USER]\n".$req->user,
                'model'        => $settings->effectiveModel(),
                'provider'     => $settings->provider,
                'status'       => AiSummaryStatus::Pending->value,
                'requested_by' => $requester->id,
            ]);

            $this->audit->record($row, 'ai.summary.requested', [
                'kind'    => AiSummaryKind::ReportView->value,
                'view_id' => $view->id,
                'period'  => ['from' => $from, 'to' => $to],
            ], $requester->id);

            return $row;
        });

        GenerateAiSummary::dispatch($summary->id);

        return $summary;
    }

    /**
     * Create a pending AiSummary for a lead and dispatch the job. PII is
     * pseudonymized before going into the prompt.
     */
    public function requestLeadQualification(Lead $lead, User $requester): AiSummary
    {
        $tenantId = (int) ($lead->tenant_id ?? Tenant::DEFAULT_ID);
        $settings = $this->settingsOrFail($tenantId);
        $this->ensureKindAvailable($settings, AiSummaryKind::LeadQualification);

        if (! $settings->lead_data_consent) {
            throw new AiDisabledException('Lead data consent has not been granted in AI settings.');
        }

        $data = $this->pseudonymizer->maskedLead($lead);
        $req  = $this->prompts->build(AiSummaryKind::LeadQualification, $settings, $data);

        $summary = DB::transaction(function () use ($lead, $requester, $tenantId, $req, $settings) {
            $row = AiSummary::create([
                'tenant_id'    => $tenantId,
                'kind'         => AiSummaryKind::LeadQualification->value,
                'subject_type' => Lead::class,
                'subject_id'   => $lead->id,
                'prompt'       => "[SYSTEM]\n".$req->system."\n\n[USER]\n".$req->user,
                'model'        => $settings->effectiveModel(),
                'provider'     => $settings->provider,
                'status'       => AiSummaryStatus::Pending->value,
                'requested_by' => $requester->id,
            ]);

            $this->audit->record($row, 'ai.summary.requested', [
                'kind'    => AiSummaryKind::LeadQualification->value,
                'lead_id' => $lead->id,
            ], $requester->id);

            return $row;
        });

        GenerateAiSummary::dispatch($summary->id);

        return $summary;
    }

    /** Resolve the LlmProvider singleton matching the tenant's chosen provider key. */
    public function providerFor(AiSetting $settings): LlmProvider
    {
        $key = $settings->provider;
        $map = AppServiceProvider::LLM_PROVIDERS;

        if (! $key || ! isset($map[$key])) {
            throw new InvalidArgumentException("Unknown LLM provider: ".($key ?? 'null'));
        }

        return app($map[$key]);
    }

    /** Throws if AI is disabled at the config or tenant level. */
    public function settingsOrFail(int $tenantId): AiSetting
    {
        if (! config('lodgely.ai.enabled')) {
            throw new AiDisabledException('AI is disabled at the application level.');
        }

        $settings = AiSetting::forTenant($tenantId);

        if (! $settings->enabled) {
            throw new AiDisabledException('AI is disabled for this tenant.');
        }
        if (! $settings->provider) {
            throw new AiDisabledException('No AI provider is configured.');
        }

        return $settings;
    }

    private function ensureKindAvailable(AiSetting $settings, AiSummaryKind $kind): void
    {
        if (! $settings->isKindEnabled($kind->value)) {
            throw new AiDisabledException(
                sprintf('AI kind "%s" is disabled for this tenant.', $kind->value)
            );
        }
    }
}
