# Architecture

Full detail behind the [README's architecture summary](../README.md#architecture-at-a-glance).
See also `CLAUDE.md` for the architectural rails (modular monolith, where
domain code lives, the duplicate-detection/visibility chokepoints) that this
tree implements.

```
app/
├── Console/Commands/        artisan commands (create-user, mock pull, purge,
│                            ad-metrics pull, report-emails dispatch,
│                            sheets fetch/dedupe, backup create/restore)
│   └── Concerns/            FetchesRecurringLeadSources — the shared body of
│                            the lodgely:*:fetch recurring-source commands
├── Domain/
│   ├── Leads/               core domain: enums, services, events
│   │   ├── Enums/           LeadStatus, LeadPriority, UserRole
│   │   └── Services/        LeadNormalizer, DuplicateDetector,
│   │                        LeadIngestor, ImportRunner, LeadKpis
│   ├── Reporting/           AdMetricsSource + CreativeMetricsSource contracts,
│   │                        AdMetricsSnapshot + CreativeMetricsSnapshot DTOs,
│   │                        MetricsIngestor, CreativeMetricsIngestor,
│   │                        CampaignRollup, CreativeRollup,
│   │                        ClientViewDataBuilder, ReportEmailDispatcher,
│   │                        ReportColumn enum
│   ├── Ai/                  LlmProvider contract, OpenAI/Ollama adapters,
│   │                        AiSummarizer + PromptBuilder + Pseudonymizer,
│   │                        LeadRanker (automatic ranking) + DailyCap
│   └── Demo/                DemoDataManager — load/unload canonical demo
│                            dataset shared with the DatabaseSeeder
├── Http/
│   ├── Controllers/Auth/    LoginController, PasswordResetController
│   ├── Controllers/OAuth/   GoogleSheetsOAuthController, GoogleAdsOAuthController
│   ├── Controllers/         WebhookController
│   └── Middleware/          SetLocale, EnsureAiEnabled, SecurityHeaders
├── Importers/
│   ├── Contracts/           LeadSource interface, IncomingLead DTO
│   ├── Csv/                 CsvLeadSource adapter
│   ├── Email/               ImapLeadSource + MailBodyParser
│   ├── EmailMock/           EmailMockLeadSource adapter
│   ├── Google/               GoogleAdsSource + GoogleCreativeSource
│   │                        (live Google Ads REST API: campaigns,
│   │                        keywords, ads)
│   ├── GoogleMock/          GoogleMockAdMetricsSource +
│   │                        GoogleMockCreativeSource adapters
│   ├── GoogleSheets/        GoogleSheetsClient (OAuth + Sheets v4 API)
│   ├── Meta/                MetaAdsSource + MetaCreativeSource (live Meta
│   │                        Marketing API: campaigns, ads, segments),
│   │                        MetaLeadsSource (live Meta Lead Ads import)
│   ├── MetaMock/            MetaMockAdMetricsSource +
│   │                        MetaMockCreativeSource adapters
│   ├── Openflow/            OpenflowClient + OpenflowLeadSource (OpenFlow form pull)
│   └── Manual/              ManualLeadSource adapter
├── Jobs/                    GenerateAiSummary, RankLeadWithAi, SendClientReportEmail
├── Livewire/
│   ├── Ai/DraftsPage        operator review of AI drafts
│   ├── Inbox/InboxPage      the main UI
│   │   └── Concerns/        URL filters, saved views, bulk actions,
│   │                        manual-lead modal (composed via traits)
│   ├── Imports/*            CSV + email (mock & IMAP) import UIs;
│   │                        GoogleSheetsImportPage (sheet sources CRUD);
│   │                        MetaLeadsImportPage (Meta Lead Ads API CRUD)
│   ├── Reporting/
│   │   ├── ReportingPage    operator ad spend + campaign rollup dashboard
│   │   ├── ReportingViewsPage  operator CRUD for client reporting views
│   │   ├── ReportEmailsPage    operator-composed scheduled report emails
│   │   └── MyReportsPage    per-client monthly reporting tab
│   ├── Settings/AdPlatformsPage             operator Meta/Google Ads connection UI
│   ├── Settings/AiSettingsPage              operator AI provider config
│   ├── Settings/BackupsPage                 operator backup create/download/restore
│   ├── Settings/DemoDataPage                operator demo-data load/unload
│   ├── Settings/GoogleSheetsSettingsPage    Google Sheets OAuth + credential mgmt
│   ├── Settings/MailSettingsPage            operator outbound mail (SMTP) config + test
│   ├── Settings/ProfilePage                 per-user profile + password change
│   ├── Users/UsersPage      operator user management
│   └── Webhooks/WebhooksPage webhook endpoint management
├── Mail/                    ClientReportEmailMessage, TestMailMessage
├── Models/                  User, Tenant, Lead, LeadNote, LeadEvent,
│                            Import, UserLeadScope, SavedFilter,
│                            WebhookEndpoint, AdSpendReport,
│                            ClientReportingView, AiSetting, AiSummary,
│                            AiEvent, ClientReportEmail,
│                            ClientReportEmailSchedule, ClientReportEmailSend,
│                            GoogleSheetsSetting, GoogleSheetSource,
│                            MetaLeadSource, OpenflowSource, AdPlatformSetting
│   └── Concerns/            ScopesToClientConnectors — the shared
│                            "which ad rows belong to this client" scope;
│                            HasRecurringFetchSchedule — isDue()/forTenant()
│                            shared by the three recurring source models
├── Providers/AppServiceProvider
├── Support/Audit/           AuditLogger, AiAuditLogger
└── Support/Backup/          BackupManager (pg_dump/pg_restore archive create/restore)
```

Adding a new lead source means:

1. Drop a class under `app/Importers/<Name>/` implementing `LeadSource`.
2. Register it in `AppServiceProvider::IMPORTERS`.
3. (Optionally) add a Livewire page to expose it in the UI.

No changes to migrations, models or the inbox are needed.

## How AI summaries work

AI is **inert by default**: the settings page and the AI menu exist, but no
model is called until an operator turns it on. (`LODGELY_AI_ENABLED=false`
in `.env` is the opt-out hard kill-switch that removes the AI routes and
menu entirely.)

1. As an operator, open `/settings/ai`, tick **Enable AI for this tenant**, and:
   - Pick a provider — **OpenAI-compatible** (works with OpenAI, Together,
     Groq, LM Studio, vLLM, …) or **Ollama** (local or self-hosted).
   - Paste your API key (stored encrypted at rest via Laravel's `Crypt`
     facade; the form never re-displays it).
   - Optionally override the base URL and model name; otherwise the
     provider defaults from `config/lodgely.php` are used.
   - Write a free-text **house style** — "what is important, where to
     look" — the AI reads it on every call.
   - Toggle which **kinds** to enable: report-view summaries,
     lead qualification, or both.
   - For lead qualification, tick the **data-sharing consent** checkbox.
     Without it, lead-level kinds refuse to run.
   - Use **Test connection** to verify reachability before going live.

Flow per generation:

1. An operator clicks "Generate AI summary" on a reporting view row, on
   `/my-reports`, or on a lead's side panel.
2. A draft row is created in `ai_summaries` (status `pending`) and a
   `GenerateAiSummary` job is queued. The exact prompt (including any
   pseudonymized lead data) is stored verbatim for audit.
3. The job calls the configured provider, writes the response back, and
   leaves the status at `pending` for review.
4. At `/ai/drafts`, the operator reviews the prompt + response and:
   `approve` (visible to operators only), `share` (visible to assigned
   clients in `/my-reports` for `report_view` summaries), `reject`
   (closed, with optional reason), or `regenerate` (re-queue with the
   same prompt).
5. Every transition is written to `ai_events` (sibling of `lead_events`);
   API keys and bearer tokens are redacted from every payload.

A daily per-tenant call cap (`LODGELY_AI_MAX_CALLS_PER_DAY`, default 100)
is enforced inside the job so a runaway loop cannot blow past it. The cap
is shared with automatic lead ranking (below).

## How automatic lead ranking works

Unlike the advisory kinds above, **lead ranking writes to the lead**. It is
a separate task (`kinds_enabled.lead_ranking`) that also needs the
data-sharing consent, so nothing changes until an operator opts in.

Gates, checked at dispatch *and* again inside the job: the config
kill-switch, the tenant's *Enable AI* toggle + provider, the ranking task,
the consent, and what is left of the daily cap.

Flow per sweep (`lodgely:ai:rank-leads`, hourly via the scheduler, or the
**"Rank unranked leads now"** button on `/settings/ai`):

1. `LeadRanker::dispatchBatch()` selects the oldest *candidates*
   (`Lead::scopeRankingCandidates()` is the single definition): never
   ranked, no human-set priority, not a duplicate, and no `lead_ranking`
   attempt in the last 24 h. The batch is bounded by *Leads per hourly run*
   and by the remaining daily cap.
2. For each lead, `LeadRanker::request()` builds the prompt — the shared
   preamble, the built-in master ranking task (`PromptBuilder::RANKING_TASK`),
   the operator-wide ranking profile (`ai_settings.lead_ranking_profile`)
   and the per-client profile (`client_ai_profiles`, matched on
   `client_name` case-insensitively) — from the pseudonymized lead (now
   including scrubbed `custom_answers`), stores it verbatim in a pending
   `ai_summaries` row (kind `lead_ranking`) and queues `RankLeadWithAi`.
   Creating the row at dispatch time is what makes step 1 idempotent: the
   hourly run and the button can never double-dispatch, and a lead the
   model keeps failing on is retried at most once a day.
3. The job (`LeadRanker::execute()`) re-checks every gate, calls the
   provider, and parses the answer with `LeadRanking::parse()` — strict
   JSON `{"priority","reason","tags"}`, tolerant of code fences and prose
   around the object. An unparseable answer marks the row *failed* with the
   raw text and leaves the lead untouched.
4. On success `LeadRanker::apply()` writes `priority`, `priority_source =
   ai`, `ai_priority`, `ai_reason`, `ai_tags`, `ai_ranked_at`, records
   `lead.ai_ranked` (and `lead.priority_changed {automatic, ai}` when the
   value moved) in `lead_events`, and closes the summary as **Applied**.

Override semantics: `InboxPage::setPriority()` and the bulk action set
`priority_source = user` on any human change (with `overrode_ai` in the
audit payload when it replaced an AI value). The sparkle shows only while
the source is `ai`; the panel then shows "AI suggested X · overridden". A
user-sourced lead is never a candidate again; an operator's **Re-run AI
ranking** button in the lead panel forces a fresh attempt.

## Meta Lead Ads fields

The `leads` table carries ten pre-wired nullable columns for Meta Lead Ads
payloads: `meta_lead_id` (idempotency key), `ad_id` / `ad_name`,
`adset_id` / `adset_name`, `campaign_id`, `form_id` / `form_name`,
`platform` (`facebook` | `instagram`), and `is_organic`.
`IncomingLead` exposes matching optional properties so a future Meta
importer adapter can pass them through without any further schema work.
Per-form custom question answers continue to flow through `raw_payload`.
