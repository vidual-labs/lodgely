<?php

namespace App\Jobs;

use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Services\AiSummarizer;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Support\Audit\AiAuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs the LLM call for a pending AiSummary. Failure modes are mapped to
 * AiSummaryStatus::Failed with a truncated error message — operators see
 * failed rows on the Drafts page and can retry by clicking "regenerate".
 *
 * Daily per-tenant call cap is enforced top-of-handle so a runaway loop
 * cannot blow past it.
 */
class GenerateAiSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $aiSummaryId) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(AiSummarizer $summarizer, AiAuditLogger $audit): void
    {
        /** @var AiSummary|null $summary */
        $summary = AiSummary::find($this->aiSummaryId);
        if (! $summary) {
            return;
        }

        // Re-check global kill-switch — settings could have been turned off between dispatch and pickup.
        if (! config('lodgely.ai.enabled')) {
            $this->markFailed($summary, $audit, 'AI is disabled at the application level.');
            return;
        }

        $cap = (int) config('lodgely.ai.max_calls_per_day', 100);
        if ($cap > 0) {
            $todayCount = AiSummary::query()
                ->where('tenant_id', $summary->tenant_id)
                ->whereDate('created_at', now()->toDateString())
                ->whereNotNull('response')
                ->count();

            if ($todayCount >= $cap) {
                $this->markFailed($summary, $audit, "Daily AI call cap of {$cap} reached for this tenant.");
                return;
            }
        }

        $settings = AiSetting::forTenant((int) $summary->tenant_id);

        if (! $settings->enabled || ! $settings->provider) {
            $this->markFailed($summary, $audit, 'AI is disabled for this tenant.');
            return;
        }

        try {
            $provider = $summarizer->providerFor($settings);

            $request = $this->extractRequest($summary, $settings);

            $response = $provider->complete($request, $settings);

            $summary->forceFill([
                'response'    => $response->text,
                'model'       => $response->model,
                'provider'    => $provider->key(),
                'token_usage' => $response->tokenUsage,
            ])->save();

            $audit->record($summary, 'ai.summary.completed', [
                'model'       => $response->model,
                'token_usage' => $response->tokenUsage,
            ], $summary->requested_by);
        } catch (Throwable $e) {
            $this->markFailed($summary, $audit, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reconstruct the LlmRequest from the stored prompt text. The prompt
     * column holds "[SYSTEM]\n...\n\n[USER]\n..." — kept this way so the
     * exact disclosure is auditable, and so retries don't depend on
     * re-running the data assemblers (which could yield different numbers
     * after-the-fact).
     */
    private function extractRequest(AiSummary $summary, AiSetting $settings): LlmRequest
    {
        $body = (string) $summary->prompt;

        if (preg_match('/^\[SYSTEM\]\n(.*?)\n\n\[USER\]\n(.*)$/s', $body, $m)) {
            return new LlmRequest(
                system: $m[1],
                user:   $m[2],
                temperature: $settings->temperature,
            );
        }

        // Defensive fallback: treat the whole blob as the user message.
        return new LlmRequest(system: '', user: $body, temperature: $settings->temperature);
    }

    private function markFailed(AiSummary $summary, AiAuditLogger $audit, string $error): void
    {
        $summary->forceFill([
            'status' => AiSummaryStatus::Failed->value,
            'error'  => mb_substr($error, 0, 1000),
        ])->save();

        $audit->record($summary, 'ai.summary.failed', [
            'error' => mb_substr($error, 0, 400),
        ], $summary->requested_by);
    }
}
