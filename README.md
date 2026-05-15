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

---

## What's intentionally out of scope (for now)

These have architecture seams reserved but are not yet implemented:

- Reporting module (Meta Ads / Google Ads ingestion, campaign rollups)
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
│   ├── Reporting/           (reserved) Meta / Google Ads ingestion
│   └── Ai/                  (reserved) AI summaries / scoring
├── Http/Controllers/Auth/   LoginController
├── Importers/
│   ├── Contracts/           LeadSource interface, IncomingLead DTO
│   ├── Csv/                 CsvLeadSource adapter
│   ├── Email/               ImapLeadSource + MailBodyParser
│   ├── EmailMock/           EmailMockLeadSource adapter
│   └── Manual/              ManualLeadSource adapter
├── Livewire/
│   ├── Inbox/InboxPage      the main UI
│   ├── Imports/*            CSV + email (mock & IMAP) import UIs
│   ├── Users/UsersPage      operator user management
│   └── Webhooks/WebhooksPage webhook endpoint management
├── Models/                  User, Tenant, Lead, LeadNote, LeadEvent,
│                            Import, UserLeadScope
├── Providers/AppServiceProvider
└── Support/Audit/AuditLogger
```

Adding a new lead source means:

1. Drop a class under `app/Importers/<Name>/` implementing `LeadSource`.
2. Register it in `AppServiceProvider::IMPORTERS`.
3. (Optionally) add a Livewire page to expose it in the UI.

No changes to migrations, models or the inbox are needed.

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

1. **Saved filters** and per-user view defaults.
2. **Stronger compliance tooling** — lawful-basis tagging, DSAR export,
   one-click subject erasure.
3. **Reporting module** (in `app/Domain/Reporting/`) — light Meta Ads + Google Ads
   ingestion, campaign/source rollups.
4. **AI summaries / quality scoring** (in `app/Domain/Ai/`) — operating on
   the reporting layer with pseudonymization / aggregation defaults.

### Completed

- ~~**Bulk actions** in the inbox (mass-forward, mass-status).~~ ✓ Done in v0.3.0.

---

## License

MIT — see `LICENSE`.
