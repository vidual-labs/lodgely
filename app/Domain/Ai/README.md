# Domain · Ai

AI-assisted summaries on top of the Reporting and Leads domains. Off by
default — flip `LODGELY_AI_ENABLED=true`, then configure a provider at
`/settings/ai` (operator-only).

## Layout

- `Contracts/LlmProvider.php` — adapter interface for LLM backends.
- `Providers/` — `OpenAiCompatibleProvider`, `OllamaProvider`. New
  providers should implement `LlmProvider` and be registered in
  `AppServiceProvider::LLM_PROVIDERS`.
- `DTOs/` — `LlmRequest`, `LlmResponse`. Provider-agnostic shapes.
- `Enums/` — `AiSummaryKind` (`report_view`, `lead_qualification`) and
  `AiSummaryStatus` (`pending` → `approved`/`rejected`/`shared`/`failed`).
- `Services/` — `AiSummarizer` (the only place that talks to providers),
  `PromptBuilder` (per-kind system + user prompts), and
  `ReportSummaryDataAssembler` (wraps `ClientViewDataBuilder` to produce
  the aggregated data block for report summaries).
- `Support/Pseudonymizer.php` — PII masking for `lead_qualification`.
- `Exceptions/` — `AiDisabledException`, `LlmCallException`.

## Design constraints

- AI operates **only** on the Reporting layer (aggregates) or on
  explicitly selected, pseudonymized leads — never on the full lead corpus.
- A single config switch (`LODGELY_AI_ENABLED`) turns AI features off.
- Tenant admins additionally control runtime config in the `ai_settings`
  table (provider, API key, model, house style, per-kind toggles, and an
  explicit `lead_data_consent` checkbox required for lead-level kinds).
- API keys are encrypted at rest with Laravel's `Crypt` facade.
- Self-hosted-friendly: `OllamaProvider` and any local LM-Studio /
  vLLM endpoint that speaks OpenAI's chat-completions shape work
  without leaving the host.

## Adding a new provider

1. Create `app/Domain/Ai/Providers/MyProvider.php` implementing `LlmProvider`.
2. Register it in `AppServiceProvider::LLM_PROVIDERS` with a stable key.
3. The settings UI picks it up automatically via the provider's `label()`.
