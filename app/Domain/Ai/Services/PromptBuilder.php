<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\Enums\AiSummaryKind;
use App\Models\AiSetting;

/**
 * Builds the system + user prompts for each AiSummaryKind. The system
 * prompt is stable per kind for prompt-snapshot tests; the user message
 * carries the kind-specific data block (JSON).
 */
class PromptBuilder
{
    /** @param array<string, mixed> $data */
    public function build(AiSummaryKind $kind, AiSetting $settings, array $data): LlmRequest
    {
        $system = $this->system($kind, $settings);
        $user   = $this->user($kind, $data);

        return new LlmRequest(
            system: $system,
            user: $user,
            temperature: $settings->temperature,
        );
    }

    private function system(AiSummaryKind $kind, AiSetting $settings): string
    {
        $blocks = [];

        $blocks[] = <<<TXT
You are an analyst inside lodgely, a lead intake hub. You write tight, operator-grade summaries.
You never invent numbers. If data is missing, say so plainly.
Use lodgely's vocabulary: Lead, Source, Status, Priority, Note, Campaign. Do not use CRM jargon like
"Deal", "Pipeline", "Stage", "Quota", or "Forecast".
Keep tone neutral, factual, and useful for a busy operator.
TXT;

        if (! empty(trim((string) $settings->house_style))) {
            $blocks[] = "House style — what the admin wants you to emphasise:\n".trim((string) $settings->house_style);
        }

        $blocks[] = match ($kind) {
            AiSummaryKind::ReportView => <<<TXT
Task: given monthly aggregated metrics from a client reporting view, produce a markdown reply
with EXACTLY these top-level sections, in this order, and nothing else:

## Summary
2 to 4 short paragraphs describing the trend across the period.

## Evaluation
Exactly 3 bullet points: what is working, what is not, what is uncertain.

## Suggested follow-ups
2 to 4 bullet points the operator could action this week.

Quote concrete numbers from the data, formatted plainly (e.g. "1,243 leads", "CTR 2.1%", "Cost per Lead \$48.20").
Never invent a metric that is not in the data.
TXT,
            AiSummaryKind::LeadQualification => <<<TXT
Task: given a pseudonymized lead, produce a markdown reply with EXACTLY these top-level sections,
in this order, and nothing else:

## Recommended priority
One of: low, medium, high. Just the word, on its own line.

## Reasoning
One short paragraph (max 4 sentences) explaining the recommendation, referring to lead signals you can see
(message intent, campaign/ad context, source).

## Suggested next action
One short bullet — the single most useful thing the operator should do next.

The lead has been pseudonymized: full name is replaced with "Lead #N", email and phone are masked. Do not
attempt to guess the underlying person. Do not output anything except the three sections above.
TXT,
        };

        return implode("\n\n", $blocks);
    }

    /** @param array<string, mixed> $data */
    private function user(AiSummaryKind $kind, array $data): string
    {
        $label = match ($kind) {
            AiSummaryKind::ReportView        => 'Reporting view data:',
            AiSummaryKind::LeadQualification => 'Pseudonymized lead data:',
        };

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $label."\n\n```json\n".$json."\n```";
    }
}
