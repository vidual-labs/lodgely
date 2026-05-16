<p align="center">
  <img src=".github/logo.png" alt="lodgely logo" width="320" />
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0-blue.svg" alt="License: GPL-3.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Livewire-3.x-FB70A9?logo=livewire&logoColor=white" alt="Livewire 3">
  <img src="https://img.shields.io/badge/version-0.9.0-6366F1" alt="Version 0.9.0">
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
  add notes, see the audit trail.
- 📂 **CSV importer** — common header names recognized (`name`, `email`,
  `phone`, `message`, `client`, `campaign`). Up to 10k rows per file.
- ✉️ **Email importer** — mock generator for demos, plus a real IMAP backend
  (`LODGELY_EMAIL_IMPORT_DRIVER=imap`) that polls unseen messages on a
  15-minute schedule and parses contact-form bodies automatically.
- 🔗 **Webhook importer** — operators create signed token URLs at `/webhooks`;
  any HTTP client can POST JSON leads and they flow through the full ingest
  pipeline instantly.
- ✍️ **Manual entry** — quick "new lead" modal for phone calls and walk-ins.
- 👥 **In-app user management** — operators create, edit and enable/disable
  users at `/users`, including client-name scoping, without needing artisan.
- 🔐 **Multi-user logins, two roles**:
  - `operator` — agency or inhouse team. Sees every lead, can import.
  - `client` — scoped to one or more `client_name` values. Read-friendly,
    review-only access to *their* leads.
- 🧾 **Audit log** of lead lifecycle changes (created, status changed,
  priority changed, note added, duplicate reconciled).
- 🗑️ **Retention awareness** — every lead carries a `retention_until`
  field, with an opt-in `php artisan lodgely:leads:purge` command.
- 📊 **Inbox KPIs** — new, duplicates, incomplete, total, leads by source.
- ☑️ **Bulk actions** — operators select multiple leads via checkboxes and apply
  a status or priority change to all in one step. Audit events recorded per lead.
- 🔖 **Saved filters & default views** — any filter combination (search, status,
  priority, source, client, sort) can be saved as a named view. Saved views appear
  as chips in the filter bar; one can be starred as the user's default, loaded
  automatically on each inbox visit.
- 🌙 **Dark / Light mode switch** — OS preference is respected on first load; a labeled pill toggle (`Light · Dark`) in the topbar lets users switch manually. For authenticated users the choice is saved to `users.ui_theme` in the database and injected server-side on the next load (no localStorage flash); guests fall back to `localStorage`.
- 🌍 **i18n ready** — all UI strings go through Laravel's `__()` helper. Ships with English (`en`) and German (`de`). Language is switched via a `POST /locale` route; for authenticated users the preference is saved to `users.locale` in the database; for guests it falls back to session.
- 📈 **Reporting** — operator-only `/reporting` page with platform/date-range filters, KPI cards (total spend, clicks, impressions, cost per lead, lodgely lead count), per-campaign breakdown table (spend, CPL, platform leads vs. lodgely leads), and a leads-by-source table. Ad spend data is ingested via swappable adapter classes (`AdMetricsSource`) — ships with deterministic mock adapters for Meta and Google Ads. Run `php artisan lodgely:import:ad-metrics --days=30` to seed demo data. Scheduled to pull yesterday's data daily at 05:00.
- 📊 **Custom client reporting views** — operators define named reporting views by selecting any combination of metrics (Leads, Clicks, Impressions, CTR, Ad Spend, Cost per Lead, Platform Leads, etc.) and assign each view to specific client users. Clients see a "My reports" tab page at `/my-reports` with a monthly time-series table showing only their assigned columns. Different clients can see entirely different views — client A might see only Leads and Clicks, client B sees full ad performance metrics. Lead data is always scoped to each client's own leads.

---

## What's intentionally out of scope (for now)

These have architecture seams reserved but are not yet implemented:

- AI summaries / quality scoring on top of reporting data
- Multi-tenancy (`tenant_id` exists everywhere; only the default tenant is wired)

---

## Tech stack

- PHP 8.3, Laravel 12
- Livewire 3 + Alpine.js, Blade-first server rendering
- Tailwind CSS 4
- PostgreSQL 16
- Caddy 2 (reverse proxy)
- Database-driver queues (no Redis required)
- Docker Compose for local + small-VPS deployments

A typical lodgely install runs comfortably on ~512 MB RAM.

---

## Quick start (Docker)

```bash
# 1. Clone and copy env
git clone https://github.com/vidual-labs/lodgely.git
cd lodgely
cp .env.example .env

# 2. Bring the stack up
docker compose up -d --build

# 3. First-time bootstrap (inside the app container)
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm ci
docker compose exec app npm run build
```

Then open <http://localhost:8080> and sign in with one of the seeded accounts:

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
├── Console/Commands/        artisan commands (create-user, mock pull, purge)
├── Domain/
│   ├── Leads/               core domain: enums, services, events
│   │   ├── Enums/           LeadStatus, LeadPriority, UserRole
│   │   └── Services/        LeadNormalizer, DuplicateDetector,
│   │                        LeadIngestor, ImportRunner
│   ├── Reporting/           AdMetricsSource contract, AdMetricsSnapshot DTO,
│   │                        MetricsIngestor, CampaignRollup services
│   └── Ai/                  (reserved) AI summaries / scoring
├── Http/Controllers/Auth/   LoginController
├── Importers/
│   ├── Contracts/           LeadSource interface, IncomingLead DTO
│   ├── Csv/                 CsvLeadSource adapter
│   ├── Email/               ImapLeadSource + MailBodyParser
│   ├── EmailMock/           EmailMockLeadSource adapter
│   ├── GoogleMock/          GoogleMockAdMetricsSource adapter
│   ├── MetaMock/            MetaMockAdMetricsSource adapter
│   └── Manual/              ManualLeadSource adapter
├── Livewire/
│   ├── Inbox/InboxPage      the main UI
│   ├── Imports/*            CSV + email (mock & IMAP) import UIs
│   ├── Reporting/ReportingPage  operator ad spend + campaign rollup dashboard
│   ├── Users/UsersPage      operator user management
│   └── Webhooks/WebhooksPage webhook endpoint management
├── Models/                  User, Tenant, Lead, LeadNote, LeadEvent,
│                            Import, UserLeadScope, AdSpendReport
├── Providers/AppServiceProvider
└── Support/Audit/AuditLogger
```

Adding a new lead source means:

1. Drop a class under `app/Importers/<Name>/` implementing `LeadSource`.
2. Register it in `AppServiceProvider::IMPORTERS`.
3. (Optionally) add a Livewire page to expose it in the UI.

No changes to migrations, models or the inbox are needed.

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
| `DB_*` | Postgres credentials | see `.env.example` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | All default to `database` | — |

---

## Roadmap

1. **Stronger compliance tooling** — lawful-basis tagging, DSAR export,
   one-click subject erasure.
2. **AI summaries / quality scoring** (in `app/Domain/Ai/`) — operating on
   the reporting layer with pseudonymization / aggregation defaults.

### Completed

- ~~**Reporting module**~~ ✓ Done in v0.9.0 — `/reporting` page with Meta + Google Ads mock adapters, `ad_spend_reports` table, campaign rollup, KPI cards.
- ~~**Bulk actions** in the inbox (mass-forward, mass-status).~~ ✓ Done in v0.7.0.
- ~~**Saved filters** and per-user view defaults.~~ ✓ Done in v0.7.0.
- ~~**Dark / Light mode** with OS-preference detection and manual toggle.~~ ✓ Done in v0.7.0.
- ~~**i18n** — English and German, per-user language preference persisted in DB.~~ ✓ Done in v0.7.0.

---

## License

GPL-3.0 — see `LICENSE`.
