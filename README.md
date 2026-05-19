<p align="center">
  <img src=".github/logo.png" alt="lodgely logo" width="320" />
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0-blue.svg" alt="License: GPL-3.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.4+">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Livewire-3.x-FB70A9?logo=livewire&logoColor=white" alt="Livewire 3">
  <img src="https://img.shields.io/badge/version-0.21.0-6366F1" alt="Version 0.21.0">
  <a href="https://github.com/vidual-labs/lodgely/stargazers"><img src="https://img.shields.io/github/stars/vidual-labs/lodgely?style=social" alt="GitHub Stars"></a>
</p>

# lodgely

**lodgely** is a lightweight, open-source **lead intake hub** for small teams.
It collects leads from CSV files, email (mock and real IMAP), webhooks and
manual entry, normalizes them into a single schema, and gives reviewers a
clean inbox to prioritize, deduplicate and forward.

> lodgely is intentionally **not** a CRM. No deals, no pipelines, no
> sequences, no forecasts. It is the layer *before* a CRM — the place where
> incoming leads from many sources stop being scattered and start being
> actionable.

---

## Table of Contents

- [Who it is for](#who-it-is-for)
- [Features](#features)
- [What's intentionally out of scope](#whats-intentionally-out-of-scope-for-now)
- [Tech stack](#tech-stack)
- [Quick start (Docker)](#quick-start-docker)
- [Quick start (local PHP, no Docker)](#quick-start-local-php-no-docker)
- [CSV import](#csv-import)
- [Architecture at a glance](#architecture-at-a-glance)
- [Privacy & GDPR notes for self-hosters](#privacy--gdpr-notes-for-self-hosters)
- [Configuration reference](#configuration-reference)
- [Roadmap](#roadmap)
- [Ethical use](#ethical-use)
- [License](#license)

---

## Who it is for

- **Small businesses** that want to handle their own lead intake without a
  €100/seat CRM.
- **Agencies** that handle leads for several client brands. One lodgely
  install hosts many `client_name`s; scoped logins let each client see only
  their own leads.
- **Inhouse marketing teams** that just need a sane shared inbox for the
  leads coming out of forms, lists and inboxes.

If you need a full sales pipeline, lodgely is the wrong tool. If you need a
clean place to *triage* leads before anything else happens, you are at home.

---

## Features

- 📥 **Unified lead inbox** — server-rendered table with search, filter by
  source / client / status / priority, sortable, paginated.
- 🧹 **Duplicate detection** — leads with a matching normalized email or
  phone are flagged automatically; you can re-check on demand.
- 📝 **Side-panel review** — open any lead, change status & priority,
  add notes, see the audit trail. For Meta Lead Ads leads the panel also
  shows the ad-source attribution (platform, organic/paid, campaign,
  adset, ad and form names) and the form's custom-question answers — so
  clients can see at a glance *where* a lead came from and what they said.
- ✅ **Outreach state (Qualified / Called / Mailed)** — three timestamped
  pill toggles on every lead, settable by both operators and clients,
  with corresponding `Q` / `C` / `M` badges on the inbox row. The
  toggles represent activity inside lodgely (not data from the upstream
  lead source) and write a `lead.outreach_toggled` audit event so the
  history is preserved.
- 📂 **CSV importer** — common header names recognized (`name`, `email`,
  `phone`, `message`, `client`, `campaign`). Up to 10k rows per file.
- ✉️ **Email importer** — mock generator for demos, plus a real IMAP backend
  (`LODGELY_EMAIL_IMPORT_DRIVER=imap`) that polls unseen messages on a
  15-minute schedule and parses contact-form bodies automatically.
- 🔗 **Webhook importer** — operators create signed token URLs at `/webhooks`;
  any HTTP client can POST JSON leads and they flow through the full ingest
  pipeline instantly.
- ✍️ **Manual entry** — quick "new lead" modal for phone calls and walk-ins.
- 📊 **Google Sheets lead source** — configure multiple Google Sheets as
  recurring lead sources at `/imports/google-sheets` (Imports nav). Paste the
  full sheet URL (or just the ID) and the page strips it down automatically.
  "Load columns" fetches the first row and **auto-maps** recognised headers
  (`name`, `email`, `phone`, `utm_*`, `created_time`, etc.) — operators review
  and adjust as needed. 27 mappable lead fields are supported, including the
  external `lead_id` / `form_id` / `created_time`, status / priority,
  outreach toggles, UTM attribution, and a free-form **"Custom answer (named
  key)…"** option that lets operators choose their own key for any extra
  column (the sheet header becomes the question label in the inbox).
  Each sheet source has its own refresh interval (hourly to weekly), default
  client/campaign, and active toggle. "Fetch now" triggers an immediate import
  and shows a result toast; a Delete button on each import row removes the
  import and its leads. The scheduler pulls all due sources hourly via
  `lodgely:google-sheets:fetch`. Google OAuth credentials (client ID + secret,
  stored encrypted in the DB) are managed at `/settings/google-sheets`.
- 👥 **In-app user management** — operators create, edit and enable/disable
  users at `/users`, including client-name scoping, without needing artisan.
  A one-click "Reset link" issues a single-use email so users can choose
  their own password without an operator ever seeing it.
- 🔐 **Multi-user logins, two roles**:
  - `operator` — agency or inhouse team. Sees every lead, can import.
  - `client` — scoped to one or more `client_name` values. Read-friendly,
    review-only access to *their* leads.
- 👤 **Per-user profile page** at `/profile` (linked from the topbar avatar
  for every role) — change name, email, password (with current-password
  challenge), interface language and theme. Clients use the same page to
  manage their own account without seeing any operator screens.
- 🔑 **Password recovery** — public `/forgot-password` flow that issues a
  rate-limited reset email through Laravel's password broker. Inactive
  accounts never receive a link, and the form response is uniform so the
  endpoint cannot be used to enumerate accounts.
- 🧾 **Audit log** of lead lifecycle changes (created, status changed,
  priority changed, note added, duplicate reconciled).
- 🗑️ **Retention awareness** — every lead carries a `retention_until`
  field, with an opt-in `php artisan lodgely:leads:purge` command.
- 📊 **Inbox KPIs** — new, duplicates, incomplete, total, leads by source.
- ☑️ **Bulk actions** — operators select multiple leads via checkboxes and apply
  a status or priority change to all in one step. Audit events recorded per lead.
- ⬇️ **Inbox export** — operators can download the currently filtered inbox as
  CSV or newline-delimited JSON (`/inbox/export?format=csv|ndjson`). Streams in
  chunks so it stays memory-safe at any size; honours the same `q / status /
  priority / source / client / sort` filters as the inbox URL. Excludes
  `raw_payload` and internal dedupe keys. Each export writes a `lead.exported`
  log line for auditability.
- 🔖 **Saved filters & default views** — any filter combination (search, status,
  priority, source, client, sort) can be saved as a named view. Saved views appear
  as chips in the filter bar; one can be starred as the user's default, loaded
  automatically on each inbox visit.
- 🧱 **Per-user column picker** — a "Columns" panel in the filter bar lets each
  user toggle which fields the inbox table renders (`received`, `name`, `email`,
  `phone`, `client`, `source`, `campaign`, `form`, `platform`, `status`,
  `priority`, `outreach`). Picks are persisted to `users.inbox_columns`. The
  picker also auto-discovers questions present in the user's leads'
  `custom_answers` and offers each as a column — clients whose Meta form asks
  "Event size" can promote it to its own column. Capped at 8 columns total
  (5 custom-question columns max) to keep the table readable. Defaults are
  role-aware: operators see `client`, clients drop it as redundant. Even
  `received` is removable — operators tracking a different date in custom
  answers can swap it for that custom column.
- 🌙 **Dark / Light mode switch** — OS preference is respected on first load; a labeled pill toggle (`Light · Dark`) in the topbar lets users switch manually. For authenticated users the choice is saved to `users.ui_theme` in the database and injected server-side on the next load (no localStorage flash); guests fall back to `localStorage`.
- 🌍 **i18n ready** — all UI strings go through Laravel's `__()` helper. Ships with English (`en`) and German (`de`). Language is switched via a `POST /locale` route; for authenticated users the preference is saved to `users.locale` in the database; for guests it falls back to session.
- 📈 **Reporting** — operator-only `/reporting` page with platform/date-range filters, KPI cards (total spend, clicks, impressions, cost per lead, lodgely lead count), per-campaign breakdown table (spend, CPL, platform leads vs. lodgely leads), and a leads-by-source table. Ad spend data is ingested via swappable adapter classes (`AdMetricsSource`) — ships with deterministic mock adapters for Meta and Google Ads, plus live API adapters that pull aggregate campaign metrics from Meta's Marketing API and Google Ads' REST API once credentials are configured (see [Configuration reference](#configuration-reference)). Run `php artisan lodgely:import:ad-metrics --days=30` to seed demo data. Scheduled to pull yesterday's data daily at 05:00.
- 📊 **Custom client reporting views** — operators define named reporting views by selecting any combination of metrics (Leads, Clicks, Impressions, CTR, Ad Spend, Cost per Lead, Platform Leads, etc.) and assign each view to specific client users. Clients see a "My reports" tab page at `/my-reports` with a monthly time-series table showing only their assigned columns. Different clients can see entirely different views — client A might see only Leads and Clicks, client B sees full ad performance metrics. Lead data is always scoped to each client's own leads.
- 🤖 **AI summaries & lead qualification** *(optional, off by default)* — admins plug in either an OpenAI-compatible API (OpenAI, Together, Groq, LM Studio, …) or a local Ollama endpoint at `/settings/ai`, set a free-text "house style" instruction, and choose which AI tasks to enable. Two kinds in v1: **report-view summaries** (narrative + evaluation + follow-ups on a custom reporting view, aggregate data only) and **lead qualification** (priority recommendation with pseudonymized lead context). Every AI output is a draft that an operator reviews at `/ai/drafts` — approve, reject, regenerate, then share with the client. API keys are stored encrypted; lead-level kinds require an explicit consent toggle; a daily per-tenant cap prevents cost runaway.
- 📨 **Custom client report emails** — operators compose modular report emails at `/reporting/emails` and either send them now, schedule them as a one-off, or recur them weekly / monthly. Each template picks any combination of: a free-text intro (markdown), the KPI summary strip, the monthly metrics table, and the latest operator-approved AI summary for a reporting view. Recipients are existing Client users (visibility is honoured because the metrics are built against the recipient). Every dispatch writes a `client_report_email_sends` audit row that surfaces as a "Recent sends" history. The `lodgely:report-emails:dispatch` artisan command runs hourly via the scheduler and advances each schedule's `next_run_at`.

---

## What's intentionally out of scope (for now)

These have architecture seams reserved but are not yet implemented:

- Multi-tenancy (`tenant_id` exists everywhere; only the default tenant is wired)

---

## Tech stack

- PHP 8.4, Laravel 12
- Livewire 3 + Alpine.js, Blade-first server rendering
- Tailwind CSS 4
- PostgreSQL 16
- Caddy 2 (reverse proxy)
- Database-driver queues (no Redis required)
- Docker Compose for local + small-VPS deployments
- Google Sheets v4 REST API + OAuth 2.0 (optional; for the Sheets import source)

A typical lodgely install runs comfortably on ~512 MB RAM.

---

## Quick start (Docker)

```bash
# 1. Clone and configure
git clone https://github.com/vidual-labs/lodgely.git
cd lodgely
cp .env.example .env
```

Open `.env` and set at minimum:

| Key | What to set |
|-----|-------------|
| `APP_URL` | Your public URL, e.g. `https://lodgely.example.com` |
| `DB_PASSWORD` | A strong password (must match across all `DB_*` vars) |
| `LODGELY_HTTP_PORT` | Host port for HTTP (default `8080`); change if that port is taken |
| `SESSION_SECURE_COOKIE` | `true` if serving over HTTPS, `false` for plain HTTP |
| `SESSION_DRIVER` | `file` is simplest; `database` works but requires the DB to be up first |

```bash
# 2. Build and start
docker compose up -d --build

# 3. Fix storage permissions (required on first start)
docker compose exec app chown -R www-data:www-data storage bootstrap/cache

# 4. First-time bootstrap
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm ci
docker compose exec app npm run build
```

Then open your `APP_URL` and sign in with one of the seeded accounts:

| Role     | Email                        | Password   | Sees                              |
|----------|------------------------------|------------|-----------------------------------|
| operator | `operator@example.com`       | `password` | everything                        |
| client   | `client.northwind@example.com` | `password` | leads with `client_name = Northwind Studio` |
| client   | `client.acme@example.com`    | `password` | leads with `client_name = Acme Wellness`    |

> 🔒 **Change these before going live.** The seeded accounts exist so
> you can take lodgely for a spin — they should be removed or rotated
> on any real deployment.

To create a fresh user:

```bash
docker compose exec app php artisan lodgely:user:create \
  --name="Jane Doe" --email=jane@example.com --role=operator
# or, scoped client:
docker compose exec app php artisan lodgely:user:create \
  --name="Brand Owner" --email=owner@example.com --role=client \
  --client="Northwind Studio"
```

### Behind a reverse proxy or Cloudflare

If lodgely sits behind Cloudflare, nginx, or any other reverse proxy:

- Set `APP_URL` to the **public** HTTPS URL (e.g. `https://lodgely.example.com`), not the internal address.
- Set `SESSION_SECURE_COOKIE=true` (the browser is on HTTPS even if the internal hop is HTTP).
- Set `SESSION_DRIVER=file` or ensure `SESSION_DRIVER=database` is working before testing login.
- The app already calls `trustProxies(at: '*')` in `bootstrap/app.php`, so `X-Forwarded-Proto` and other forwarded headers are trusted automatically — no extra config needed.

---

## Quick start (local PHP, no Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate

# Point .env at a local Postgres 16 instance
# DB_HOST=127.0.0.1, DB_DATABASE=lodgely, ...

php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

Run the queue worker in a second terminal:

```bash
php artisan queue:work
```

---

## CSV import

Upload any UTF-8 CSV with a header row from **Imports → CSV import**. lodgely
recognizes the following column aliases (case-insensitive):

| Logical field | Accepted column names                                |
|---------------|------------------------------------------------------|
| `full_name`   | `name`, `full_name`, `contact`, `contact name`       |
| `email`       | `email`, `email address`, `e-mail`, `mail`           |
| `phone`       | `phone`, `phone number`, `tel`, `telephone`, `mobile`|
| `message`     | `message`, `note`, `comment`, `enquiry`, `inquiry`   |
| `client_name` | `client`, `client_name`, `brand`, `account`          |
| `campaign_name` | `campaign`, `campaign name`, `source campaign`     |

A working sample lives at `database/samples/leads-sample.csv`.

---

## Architecture at a glance

```
app/
├── Console/Commands/        artisan commands (create-user, mock pull, purge,
│                            ad-metrics pull, report-emails dispatch)
├── Domain/
│   ├── Leads/               core domain: enums, services, events
│   │   ├── Enums/           LeadStatus, LeadPriority, UserRole
│   │   └── Services/        LeadNormalizer, DuplicateDetector,
│   │                        LeadIngestor, ImportRunner, LeadKpis
│   ├── Reporting/           AdMetricsSource contract, AdMetricsSnapshot DTO,
│   │                        MetricsIngestor, CampaignRollup,
│   │                        ClientViewDataBuilder, ReportEmailDispatcher,
│   │                        ReportColumn enum
│   └── Ai/                  LlmProvider contract, OpenAI/Ollama adapters,
│                            AiSummarizer + PromptBuilder + Pseudonymizer
├── Http/
│   ├── Controllers/Auth/    LoginController, PasswordResetController
│   ├── Controllers/OAuth/   GoogleSheetsOAuthController
│   ├── Controllers/         WebhookController
│   └── Middleware/          SetLocale, EnsureAiEnabled, SecurityHeaders
├── Importers/
│   ├── Contracts/           LeadSource interface, IncomingLead DTO
│   ├── Csv/                 CsvLeadSource adapter
│   ├── Email/               ImapLeadSource + MailBodyParser
│   ├── EmailMock/           EmailMockLeadSource adapter
│   ├── GoogleMock/          GoogleMockAdMetricsSource adapter
│   ├── GoogleSheets/        GoogleSheetsClient (OAuth + Sheets v4 API)
│   ├── MetaMock/            MetaMockAdMetricsSource adapter
│   └── Manual/              ManualLeadSource adapter
├── Jobs/                    GenerateAiSummary, SendClientReportEmail
├── Livewire/
│   ├── Ai/DraftsPage        operator review of AI drafts
│   ├── Inbox/InboxPage      the main UI
│   │   └── Concerns/        URL filters, saved views, bulk actions,
│   │                        manual-lead modal (composed via traits)
│   ├── Imports/*            CSV + email (mock & IMAP) import UIs;
│   │                        GoogleSheetsImportPage (sheet sources CRUD)
│   ├── Reporting/
│   │   ├── ReportingPage    operator ad spend + campaign rollup dashboard
│   │   ├── ReportingViewsPage  operator CRUD for client reporting views
│   │   ├── ReportEmailsPage    operator-composed scheduled report emails
│   │   └── MyReportsPage    per-client monthly reporting tab
│   ├── Settings/AiSettingsPage              operator AI provider config
│   ├── Settings/GoogleSheetsSettingsPage    Google Sheets OAuth + credential mgmt
│   ├── Settings/ProfilePage                 per-user profile + password change
│   ├── Users/UsersPage      operator user management
│   └── Webhooks/WebhooksPage webhook endpoint management
├── Mail/                    ClientReportEmailMessage
├── Models/                  User, Tenant, Lead, LeadNote, LeadEvent,
│                            Import, UserLeadScope, SavedFilter,
│                            WebhookEndpoint, AdSpendReport,
│                            ClientReportingView, AiSetting, AiSummary,
│                            AiEvent, ClientReportEmail,
│                            ClientReportEmailSchedule, ClientReportEmailSend,
│                            GoogleSheetsSetting, GoogleSheetSource
├── Providers/AppServiceProvider
└── Support/Audit/           AuditLogger, AiAuditLogger
```

Adding a new lead source means:

1. Drop a class under `app/Importers/<Name>/` implementing `LeadSource`.
2. Register it in `AppServiceProvider::IMPORTERS`.
3. (Optionally) add a Livewire page to expose it in the UI.

No changes to migrations, models or the inbox are needed.

### How AI summaries work

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

### Meta Lead Ads fields

The `leads` table carries ten pre-wired nullable columns for Meta Lead Ads
payloads: `meta_lead_id` (idempotency key), `ad_id` / `ad_name`,
`adset_id` / `adset_name`, `campaign_id`, `form_id` / `form_name`,
`platform` (`facebook` | `instagram`), and `is_organic`.
`IncomingLead` exposes matching optional properties so a future Meta
importer adapter can pass them through without any further schema work.
Per-form custom question answers continue to flow through `raw_payload`.

---

## Privacy & GDPR notes for self-hosters

lodgely is built with privacy-by-design defaults, but **you, the operator,
are the data controller** for everything you put in it. The product gives
you the tools; the policies are yours.

- **Data minimization.** Only the lead fields in the schema are stored.
  Raw CSV rows / mock email bodies are kept in `raw_payload` for audit but
  are never displayed in summary views.
- **Retention.** Every lead has a `retention_until` column. The
  `lodgely:leads:purge` command soft-deletes leads past their date; it is
  scheduled daily but does nothing unless `LODGELY_DEFAULT_RETENTION_DAYS`
  is configured.
- **Soft deletes** on `leads` and `lead_notes` mean an accidental delete is
  reversible until you hard-delete in the DB.
- **Audit trail.** `lead_events` records create/update/note actions with
  actor and timestamp.
- **Access scoping.** Client users see only their own `client_name`'s
  leads, enforced both in queries and in mutations.
- **Phone / email normalization** is for duplicate detection only; the
  original values remain visible to operators.
- **No telemetry, no external calls** out of the box. lodgely does not
  phone home.
- **HTTPS.** Use the Caddyfile's TLS-on-real-hostname mode for production.

What this product does **not** do for you (yet, on purpose):
consent capture, data-subject access reports, automatic right-to-erasure
workflow, lawful-basis tagging. Those belong to a future compliance module
and are listed in the roadmap.

---

## Configuration reference

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_NAME` | Display name in titles/headers | `lodgely` |
| `APP_URL`  | Public URL of the install | `http://localhost:8080` |
| `LODGELY_BRAND_NAME` / `LODGELY_BRAND_TAGLINE` | Optional white-label-ish strings (still under the lodgely identity) | `lodgely` / `Lead intake, unified.` |
| `LODGELY_CSV_MAX_ROWS` | Hard cap on rows ingested per CSV | `10000` |
| `LODGELY_EMAIL_IMPORT_DRIVER` | `mock` or `imap` | `mock` |
| `LODGELY_IMAP_HOST` | IMAP server hostname (activates real email backend) | — |
| `LODGELY_IMAP_PORT` | IMAP port | `993` |
| `LODGELY_IMAP_ENCRYPTION` | `ssl`, `tls`, or `notls` | `ssl` |
| `LODGELY_IMAP_USERNAME` / `LODGELY_IMAP_PASSWORD` | Mailbox credentials | — |
| `LODGELY_IMAP_MAILBOX` | Folder to poll | `INBOX` |
| `LODGELY_IMAP_MAX_MESSAGES` | Max unseen messages per pull | `50` |
| `LODGELY_DEFAULT_RETENTION_DAYS` | Default lead retention, empty = retain | `365` |
| `LODGELY_AI_ENABLED` | Master kill-switch for the AI module. When `false`, all AI routes 404, buttons are hidden, and jobs no-op. Per-tenant config at `/settings/ai` only matters when this is true. | `false` |
| `LODGELY_AI_MAX_CALLS_PER_DAY` | Maximum completed AI generations per tenant per day. `0` disables the cap. | `100` |
| `LODGELY_AI_TIMEOUT` | HTTP timeout (seconds) for a single LLM provider call. | `60` |
| `LODGELY_AD_METRICS_SOURCES` | Comma-separated list of ad source adapters to activate. Available: `meta_mock`, `google_mock`, `meta`, `google`. | `meta_mock,google_mock` |
| `LODGELY_AD_METRICS_HTTP_TIMEOUT` | HTTP timeout (seconds) for outbound ad platform API calls. | `30` |
| `LODGELY_META_ADS_ACCESS_TOKEN` | Meta Marketing API long-lived (system-user) access token. Required when `meta` is in `LODGELY_AD_METRICS_SOURCES`. | — |
| `LODGELY_META_ADS_ACCOUNT_ID` | Meta ad account id, with or without the `act_` prefix. | — |
| `LODGELY_META_ADS_API_VERSION` | Graph API version. | `v21.0` |
| `LODGELY_META_ADS_CURRENCY` | Currency code matching the ad account currency (lodgely stores cents + this code). | `USD` |
| `LODGELY_GOOGLE_ADS_CLIENT_ID` / `LODGELY_GOOGLE_ADS_CLIENT_SECRET` / `LODGELY_GOOGLE_ADS_REFRESH_TOKEN` | OAuth installed-application credentials. Required when `google` is in `LODGELY_AD_METRICS_SOURCES`. | — |
| `LODGELY_GOOGLE_ADS_DEVELOPER_TOKEN` | Approved Google Ads API developer token. | — |
| `LODGELY_GOOGLE_ADS_LOGIN_CUSTOMER_ID` | Manager (MCC) account id. Set only when the OAuth user authenticates via a manager account. | — |
| `LODGELY_GOOGLE_ADS_CUSTOMER_ID` | Target Google Ads account id (digits only or hyphenated). | — |
| `LODGELY_GOOGLE_ADS_API_VERSION` | Google Ads REST API version. | `v18` |
| `LODGELY_GOOGLE_SHEETS_CLIENT_ID` / `LODGELY_GOOGLE_SHEETS_CLIENT_SECRET` / `LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN` | Legacy env-based fallback for Google Sheets OAuth credentials. Prefer the in-app settings page at `/settings/google-sheets` — credentials entered there are stored encrypted in the DB and take precedence over these env vars. | — |
| `LODGELY_GOOGLE_SHEETS_HTTP_TIMEOUT` | HTTP timeout (seconds) for outbound calls to Google Sheets / OAuth endpoints. | `30` |
| `DB_*` | Postgres credentials | see `.env.example` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | All default to `database` | — |

---

## Roadmap

1. **Stronger compliance tooling** — lawful-basis tagging, DSAR export,
   one-click subject erasure.
2. **Multi-tenancy** — `tenant_id` exists everywhere; wire the full
   tenant-resolution stack so a single install can host many isolated
   workspaces.

### Completed

- ~~**Google Sheets lead source**~~ ✓ Done in v0.20.0 — `/imports/google-sheets` page where operators configure multiple sheets as recurring lead sources with per-column field mapping, per-sheet refresh interval, "Fetch now" button, and a `lodgely:google-sheets:fetch` hourly cron. OAuth credentials managed at `/settings/google-sheets` (done in v0.19.0).
- ~~**Password recovery + per-user profile page**~~ ✓ Done in v0.14.0 — public `/forgot-password` flow with rate-limited reset emails, operator "Reset link" action on the `/users` table, and a `/profile` page that lets every role manage their name, email, password, language and theme.
- ~~**Custom client report emails**~~ ✓ Done in v0.12.0 — `/reporting/emails` for composing modular templates (intro, KPI strip, monthly table, latest approved AI summary), send-now / one-off / weekly / monthly schedules, audited `client_report_email_sends` history, `lodgely:report-emails:dispatch` hourly cron.
- ~~**Custom client reporting views**~~ ✓ Done in v0.10.0 — `/reporting/views` for operators to define named views and assign per-client column sets, `/my-reports` per-client monthly time-series.
- ~~**AI summaries & lead qualification**~~ ✓ Done in v0.11.0 — `/settings/ai` for provider config (OpenAI-compatible or Ollama), `/ai/drafts` for operator review, report-view summaries and pseudonymized lead qualification with approve-then-share workflow.
- ~~**Reporting module**~~ ✓ Done in v0.9.0 — `/reporting` page with Meta + Google Ads mock adapters, `ad_spend_reports` table, campaign rollup, KPI cards.
- ~~**Bulk actions** in the inbox (mass-forward, mass-status).~~ ✓ Done in v0.7.0.
- ~~**Saved filters** and per-user view defaults.~~ ✓ Done in v0.7.0.
- ~~**Dark / Light mode** with OS-preference detection and manual toggle.~~ ✓ Done in v0.7.0.
- ~~**i18n** — English and German, per-user language preference persisted in DB.~~ ✓ Done in v0.7.0.

---

## Ethical use

lodgely is a marketing tool. We ask, as a non-binding ethical request,
that you do **not** use lodgely to run lead intake for clients in:

- **Weapons and armaments**
- **Fossil-fuel energy** (extraction, refining, distribution, generation)
- **Internal-combustion / fossil-fuel passenger vehicles** — electric
  vehicles, bicycles and public transit are explicitly fine.

This is a request from the maintainers, not a legal restriction (lodgely
remains GPL-3.0). See the preamble in `LICENSE` for the full statement.

---

## License

GPL-3.0 — see `LICENSE`.
