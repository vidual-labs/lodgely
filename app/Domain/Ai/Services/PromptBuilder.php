<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\DTOs\LlmRequest;
use App\Domain\Ai\Enums\AiSummaryKind;
use App\Models\AiSetting;

/**
 * Builds the system + user prompts for each AiSummaryKind. The system
 * prompt is stable per kind for prompt-snapshot tests; the user message
 * carries the kind-specific data block (JSON).
 *
 * The lead-ranking kind is the one strict-JSON task: it gets the shared
 * preamble, the ranking task, and the operator-wide + per-client "ideal
 * customer" profiles — but deliberately NOT the house style, which is prose
 * guidance for narrative summaries and would only muddy a JSON answer.
 */
class PromptBuilder
{
    /** Ranking answers are short JSON; cap the completion so a chatty model can't run away. */
    public const RANKING_MAX_TOKENS = 300;

    /** @param array<string, mixed> $data */
    public function build(
        AiSummaryKind $kind,
        AiSetting $settings,
        array $data,
        ?string $clientProfile = null,
        ?string $clientName = null,
    ): LlmRequest {
        $system = $this->system($kind, $settings, $clientProfile, $clientName);
        $user   = $this->user($kind, $data);

        if ($kind === AiSummaryKind::LeadRanking) {
            return new LlmRequest(
                system: $system,
                user: $user,
                temperature: $settings->temperature ?? 0.0,
                maxTokens: self::RANKING_MAX_TOKENS,
            );
        }

        return new LlmRequest(
            system: $system,
            user: $user,
            temperature: $settings->temperature,
        );
    }

    private function system(AiSummaryKind $kind, AiSetting $settings, ?string $clientProfile, ?string $clientName): string
    {
        $blocks = [];

        $blocks[] = <<<TXT
You are an analyst inside lodgely, a lead intake hub. You write tight, operator-grade summaries.
You never invent numbers. If data is missing, say so plainly.
Use lodgely's vocabulary: Lead, Source, Status, Priority, Note, Campaign. Do not use CRM jargon like
"Deal", "Pipeline", "Stage", "Quota", or "Forecast".
Keep tone neutral, factual, and useful for a busy operator.
TXT;

        if ($kind === AiSummaryKind::LeadRanking) {
            $blocks[] = self::RANKING_TASK;
            array_push($blocks, ...$this->rankingProfileBlocks($settings, $clientProfile, $clientName));

            return implode("\n\n", $blocks);
        }

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

    /**
     * The master ranking prompt. Operators cannot edit this; they steer it
     * through the ranking profiles appended after it.
     */
    public const RANKING_TASK = <<<'TXT'
Task: rank ONE pseudonymized lead for follow-up priority on behalf of the client it belongs to.

Judge two things only, from the data you are given:
1. Fit — how closely the lead matches the ideal customer described in the ranking profiles below. Read the operator profile first, then the client profile; where they disagree, the client profile wins.
2. Intent — how concrete the lead's own words are. A request for a quote, call, booking or visit, or a stated budget, timeframe, quantity or location is strong intent. A vague "send me more info" is weak intent. Spam, test submissions and off-topic messages are no intent.

Signals you may use: message, form answers, campaign / adset / ad / form names, source, platform, organic vs paid, and whether an email and a phone number are present. Do not use the pseudonymized name, email or phone to guess who the person is.

Priority scale:
- "high": clear fit and concrete intent, or a case the profiles name as must-call. Worth contacting first.
- "medium": plausible fit or intent, but something is missing, unclear or contradictory.
- "low": weak or no fit, no discernible intent, an exclusion from the profiles applies, or it looks like spam or a test.
If no ranking profile is given, rank on intent and completeness alone.

Reply with exactly one JSON object and nothing else — no markdown, no code fences, no text before or after it:
{"priority":"low|medium|high","reason":"<one sentence, max 200 characters, plain English, naming the signal that decided it>","tags":["<tag>","<tag>"]}
tags: 1 to 5 short lower-case labels, max 30 characters each, using only letters, digits, spaces and hyphens, describing what you saw — for example "quote request", "budget stated", "wrong region", "missing phone", "spam".
TXT;

    public const RANKING_NO_PROFILE = 'No ranking profile has been provided for this lead. Rank on intent and completeness alone.';

    /** @return list<string> */
    private function rankingProfileBlocks(AiSetting $settings, ?string $clientProfile, ?string $clientName): array
    {
        $blocks = [];

        $operator = trim((string) $settings->lead_ranking_profile);
        if ($operator !== '') {
            $blocks[] = "Operator ranking profile — what counts as an ideal customer across every client:\n".$operator;
        }

        $client = trim((string) $clientProfile);
        if ($client !== '') {
            $label = trim((string) $clientName) !== '' ? ' for "'.trim((string) $clientName).'"' : '';
            $blocks[] = 'Client ranking profile'.$label." — what this client considers an ideal customer:\n".$client;
        }

        if ($blocks === []) {
            $blocks[] = self::RANKING_NO_PROFILE;
        }

        return $blocks;
    }

    /** @param array<string, mixed> $data */
    private function user(AiSummaryKind $kind, array $data): string
    {
        $label = match ($kind) {
            AiSummaryKind::ReportView        => 'Reporting view data:',
            AiSummaryKind::LeadQualification,
            AiSummaryKind::LeadRanking       => 'Pseudonymized lead data:',
        };

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $label."\n\n```json\n".$json."\n```";
    }
}
