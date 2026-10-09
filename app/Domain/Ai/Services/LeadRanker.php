<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\DTOs\LeadRanking;
use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Support\DailyCap;
use App\Domain\Ai\Support\Pseudonymizer;
use App\Domain\Leads\Enums\PrioritySource;
use App\Jobs\RankLeadWithAi;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\ClientAiProfile;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AiAuditLogger;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Automatic lead ranking: the model proposes a priority, a one-line reason
 * and a few tags, and — unlike the advisory lead-qualification kind — the
 * result is written straight onto the lead. Any human change to the
 * priority afterwards wins (see InboxPage::setPriority()).
 *
 * Three entry points, all funnelling through one AiSummary row per attempt
 * so the exact prompt stays auditable and the daily cap counts rankings
 * like every other kind:
 *
 *  - request()        one lead → pending AiSummary + queued RankLeadWithAi
 *  - dispatchBatch()  the hourly command / "Rank now" button
 *  - execute()        the job body: call the model, parse, apply
 */
class LeadRanker
{
    public function __construct(
        private PromptBuilder $prompts,
        private Pseudonymizer $pseudonymizer,
        private AiAuditLogger $aiAudit,
        private AuditLogger $leadAudit,
        private DailyCap $cap,
        private AiSummarizer $summarizer,
    ) {}

    /**
     * Queue a ranking for one lead. Without $force, a lead that already
     * carries a human-set priority or a previous ranking is refused — the
     * hourly batch never second-guesses people; an operator's "Re-run"
     * button passes $force.
     */
    public function request(Lead $lead, ?User $requester = null, bool $force = false): AiSummary
    {
        $tenantId = (int) ($lead->tenant_id ?? Tenant::DEFAULT_ID);
        $settings = $this->settingsOrFail($tenantId);

        if (! $force) {
            if ($lead->priority_source === PrioritySource::User) {
                throw new AiDisabledException('This lead\'s priority was set by a person and is not re-ranked automatically.');
            }
            if ($lead->ai_ranked_at !== null) {
                throw new AiDisabledException('This lead has already been ranked.');
            }
        }

        $data    = $this->pseudonymizer->maskedLead($lead);
        $profile = ClientAiProfile::textFor($tenantId, $lead->client_name);
        $req     = $this->prompts->build(AiSummaryKind::LeadRanking, $settings, $data, $profile, $lead->client_name);

        $summary = DB::transaction(function () use ($lead, $requester, $tenantId, $req, $settings) {
            $row = AiSummary::create([
                'tenant_id'    => $tenantId,
                'kind'         => AiSummaryKind::LeadRanking->value,
                'subject_type' => Lead::class,
                'subject_id'   => $lead->id,
                'prompt'       => $req->toStoredPrompt(),
                'model'        => $settings->effectiveModel(),
                'provider'     => $settings->provider,
                'status'       => AiSummaryStatus::Pending->value,
                'requested_by' => $requester?->id,
            ]);

            $this->aiAudit->record($row, 'ai.summary.requested', [
                'kind'      => AiSummaryKind::LeadRanking->value,
                'lead_id'   => $lead->id,
                'automatic' => $requester === null,
            ], $requester?->id);

            return $row;
        });

        RankLeadWithAi::dispatch($summary->id);

        return $summary;
    }

    /**
     * Queue rankings for the oldest unranked leads, bounded by the batch
     * size and by what is left of today's cap. Quiet when any gate is off —
     * the hourly scheduler must not spam logs on installs without AI.
     *
     * @return array{dispatched: int, reason: ?string}
     */
    public function dispatchBatch(int $tenantId, ?int $limit = null, ?User $requester = null): array
    {
        try {
            $settings = $this->settingsOrFail($tenantId);
        } catch (AiDisabledException $e) {
            return ['dispatched' => 0, 'reason' => $e->getMessage()];
        }

        $remaining = $this->cap->remaining($tenantId);
        if ($remaining !== null && $remaining <= 0) {
            return ['dispatched' => 0, 'reason' => 'cap'];
        }

        $limit = max(1, $limit ?? $settings->rankingBatchSize());
        if ($remaining !== null) {
            $limit = min($limit, $remaining);
        }

        $dispatched = 0;
        foreach (Lead::query()->rankingCandidates($tenantId)->limit($limit)->get() as $lead) {
            try {
                $this->request($lead, $requester);
                $dispatched++;
            } catch (AiDisabledException) {
                break;
            }
        }

        return ['dispatched' => $dispatched, 'reason' => null];
    }

    /**
     * Job body. Every gate is re-checked because settings can change between
     * dispatch and pickup. Gate failures and unparseable answers mark the row
     * failed without throwing (a retry would not help); transport errors
     * rethrow so the queue retries once.
     */
    public function execute(AiSummary $summary): void
    {
        $tenantId = (int) $summary->tenant_id;

        try {
            $settings = $this->settingsOrFail($tenantId);
        } catch (AiDisabledException $e) {
            $this->markFailed($summary, $e->getMessage());

            return;
        }

        /** @var Lead|null $lead */
        $lead = Lead::query()->find($summary->subject_id);
        if (! $lead) {
            $this->markFailed($summary, 'Lead no longer exists.');

            return;
        }

        if ($this->cap->reached($tenantId)) {
            $this->markFailed($summary, "Daily AI call cap of {$this->cap->cap()} reached for this tenant.");

            return;
        }

        $request = LlmRequest::fromStoredPrompt(
            (string) $summary->prompt,
            $settings->temperature ?? 0.0,
            PromptBuilder::RANKING_MAX_TOKENS,
        );

        try {
            $provider = $this->summarizer->providerFor($settings);
            $response = $provider->complete($request, $settings);
        } catch (Throwable $e) {
            $this->markFailed($summary, $e->getMessage());
            throw $e;
        }

        try {
            $ranking = LeadRanking::parse($response->text);
        } catch (InvalidArgumentException $e) {
            $summary->forceFill([
                'response'    => $response->text,
                'model'       => $response->model,
                'provider'    => $provider->key(),
                'token_usage' => $response->tokenUsage,
            ])->save();
            $this->markFailed($summary, 'Could not parse ranking: '.$e->getMessage());

            return;
        }

        $this->apply($lead, $ranking, $summary, [
            'response'    => $response->text,
            'model'       => $response->model,
            'provider'    => $provider->key(),
            'token_usage' => $response->tokenUsage,
        ]);
    }

    /**
     * Write the ranking onto the lead and close the summary as applied.
     *
     * @param  array<string, mixed>  $summaryFields
     */
    public function apply(Lead $lead, LeadRanking $ranking, AiSummary $summary, array $summaryFields = []): void
    {
        DB::transaction(function () use ($lead, $ranking, $summary, $summaryFields) {
            $from = $lead->priority?->value;

            $lead->forceFill([
                'priority'        => $ranking->priority,
                'priority_source' => PrioritySource::Ai,
                'ai_priority'     => $ranking->priority,
                'ai_reason'       => $ranking->reason,
                'ai_tags'         => $ranking->tags,
                'ai_ranked_at'    => now(),
            ])->save();

            $this->leadAudit->record($lead, 'lead.ai_ranked', [
                'priority'      => $ranking->priority->value,
                'reason'        => $ranking->reason,
                'tags'          => $ranking->tags,
                'ai_summary_id' => $summary->id,
            ], $summary->requested_by);

            if ($from !== $ranking->priority->value) {
                $this->leadAudit->record($lead, 'lead.priority_changed', [
                    'from'      => $from,
                    'to'        => $ranking->priority->value,
                    'automatic' => true,
                    'ai'        => true,
                ], $summary->requested_by);
            }

            $summary->forceFill(array_merge($summaryFields, [
                'status' => AiSummaryStatus::Applied->value,
                'error'  => null,
            ]))->save();

            $this->aiAudit->record($summary, 'ai.summary.applied', [
                'lead_id'  => $lead->id,
                'priority' => $ranking->priority->value,
                'tags'     => $ranking->tags,
            ], $summary->requested_by);
        });
    }

    /** All four ranking gates, as an AiSetting or an AiDisabledException. */
    public function settingsOrFail(int $tenantId): AiSetting
    {
        $settings = $this->summarizer->settingsOrFail($tenantId);

        if (! $settings->isKindEnabled(AiSummaryKind::LeadRanking->value)) {
            throw new AiDisabledException('Automatic lead ranking is turned off in AI settings.');
        }
        if (! $settings->lead_data_consent) {
            throw new AiDisabledException('Lead data consent has not been granted in AI settings.');
        }

        return $settings;
    }

    private function markFailed(AiSummary $summary, string $error): void
    {
        $summary->forceFill([
            'status' => AiSummaryStatus::Failed->value,
            'error'  => mb_substr($error, 0, 1000),
        ])->save();

        $this->aiAudit->record($summary, 'ai.summary.failed', [
            'error' => mb_substr($error, 0, 400),
        ], $summary->requested_by);
    }
}
