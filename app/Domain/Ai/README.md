# Domain · Ai

AI-assisted summaries and automatic lead ranking on top of the Reporting and
Leads domains. Inert by default — an operator enables AI and configures a
provider at `/settings/ai`. `LODGELY_AI_ENABLED=false` is the opt-out hard
kill-switch that removes the AI routes and menu.

## Layout

- `Contracts/LlmProvider.php` — adapter interface for LLM backends.
- `Providers/` — `OpenAiCompatibleProvider`, `OllamaProvider`. New
  providers should implement `LlmProvider` and be registered in
  `AppServiceProvider::LLM_PROVIDERS`.
- `DTOs/` — `LlmRequest`, `LlmResponse`. Provider-agnostic shapes.
- `DTOs/LeadRanking.php` — the parsed ranking answer; `parse()` accepts the
  strict JSON contract below (tolerating code fences / prose around it).
- `Enums/` — `AiSummaryKind` (`report_view`, `lead_qualification`,
  `lead_ranking`) and `AiSummaryStatus` (`pending` →
  `approved`/`rejected`/`shared`/`failed`, plus `applied` for auto-applied
  rankings).
- `Services/` — `AiSummarizer` (report summaries + advisory lead
  qualification), `LeadRanker` (automatic ranking: request / dispatchBatch /
  execute / apply), `PromptBuilder` (per-kind system + user prompts, incl.
  the built-in `RANKING_TASK` master prompt), and
  `ReportSummaryDataAssembler` (wraps `ClientViewDataBuilder` to produce
  the aggregated data block for report summaries).
- `Support/Pseudonymizer.php` — PII masking for the lead-level kinds.
- `Support/DailyCap.php` — the shared per-tenant daily call budget.
- `Exceptions/` — `AiDisabledException`, `LlmCallException`.

## Design constraints

- AI operates **only** on the Reporting layer (aggregates) or on
  explicitly selected, pseudonymized leads — never on the full lead corpus.
- A single config switch (`LODGELY_AI_ENABLED=false`) turns AI features off
  entirely; the per-tenant `enabled` toggle is the everyday on/off.
- Tenant admins additionally control runtime config in the `ai_settings`
  table (provider, API key, model, house style, per-kind toggles, and an
  explicit `lead_data_consent` checkbox required for lead-level kinds).
- API keys are encrypted at rest with Laravel's `Crypt` facade.
- Self-hosted-friendly: `OllamaProvider` and any local LM-Studio /
  vLLM endpoint that speaks OpenAI's chat-completions shape work
  without leaving the host.

## Lead-ranking answer contract

The model must reply with one bare JSON object; `LeadRanking::parse()`
enforces this schema and normalises tags (lower-case, `[a-z0-9 -]`, ≤ 30
chars, ≤ 5):

```json
{
  "type": "object",
  "required": ["priority", "reason"],
  "additionalProperties": false,
  "properties": {
    "priority": { "type": "string", "enum": ["low", "medium", "high"] },
    "reason":   { "type": "string", "minLength": 1, "maxLength": 200 },
    "tags":     { "type": "array", "maxItems": 5,
                  "items": { "type": "string", "minLength": 1, "maxLength": 30,
                             "pattern": "^[a-z0-9][a-z0-9 -]*$" } }
  }
}
```

## Adding a new provider

1. Create `app/Domain/Ai/Providers/MyProvider.php` implementing `LlmProvider`.
2. Register it in `AppServiceProvider::LLM_PROVIDERS` with a stable key.
3. The settings UI picks it up automatically via the provider's `label()`.
