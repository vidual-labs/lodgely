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
├── Domain/
│   ├── Leads/               core domain: enums, services, events
│   │   ├── Enums/           LeadStatus, LeadPriority, UserRole
│   │   └── Services/        LeadNormalizer, DuplicateDetector,
│   │                        LeadIngestor, ImportRunner, LeadKpis
│   ├── Reporting/           AdMetricsSource contract, AdMetricsSnapshot DTO,
│   │                        MetricsIngestor, CampaignRollup,
│   │                        ClientViewDataBuilder, ReportEmailDispatcher,
│   │                        ReportColumn enum
│   ├── Ai/                  LlmProvider contract, OpenAI/Ollama adapters,
│   │                        AiSummarizer + PromptBuilder + Pseudonymizer
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
│   ├── Google/               GoogleAdsSource (live Google Ads REST API)
│   ├── GoogleMock/          GoogleMockAdMetricsSource adapter
│   ├── GoogleSheets/        GoogleSheetsClient (OAuth + Sheets v4 API)
│   ├── Meta/                MetaAdsSource (live Meta Marketing API),
│   │                        MetaLeadsSource (live Meta Lead Ads import)
│   ├── MetaMock/            MetaMockAdMetricsSource adapter
│   └── Manual/              ManualLeadSource adapter
├── Jobs/                    GenerateAiSummary, SendClientReportEmail
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
│                            MetaLeadSource, AdPlatformSetting
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

AI is **off by default**. Enable it in two places:

1. Set `LODGELY_AI_ENABLED=true` in `.env` (master kill-switch — the server
   operator controls this).
2. As an operator, open `/settings/ai` and:
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
is enforced inside the job so a runaway loop cannot blow past it.

## Meta Lead Ads fields

The `leads` table carries ten pre-wired nullable columns for Meta Lead Ads
payloads: `meta_lead_id` (idempotency key), `ad_id` / `ad_name`,
`adset_id` / `adset_name`, `campaign_id`, `form_id` / `form_name`,
`platform` (`facebook` | `instagram`), and `is_organic`.
`IncomingLead` exposes matching optional properties so a future Meta
importer adapter can pass them through without any further schema work.
Per-form custom question answers continue to flow through `raw_payload`.
