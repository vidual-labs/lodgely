# Features

Full detail behind the condensed list in the [README](../README.md#features).
See also [Architecture](ARCHITECTURE.md) for how each of these is implemented,
and [Configuration reference](CONFIGURATION.md) for every env var mentioned
below.

- 📥 **Unified lead inbox** — server-rendered table with a compact inline filter
  bar (search, source, status, priority, client, sort), active-filter count badge,
  per-query lead count, sortable column headers (click to cycle asc/desc/default),
  column picker, saved views, and pagination.
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
  import and its leads, and **"Delete all imports"** clears the whole backlog in
  one click. The scheduler pulls all due sources hourly via
  `lodgely:google-sheets:fetch`. **Re-fetches are idempotent** — each row gets a
  stable content fingerprint, so re-reading the same sheet skips rows already
  imported instead of creating duplicates (the import summary shows a *Skipped*
  count). Run `lodgely:google-sheets:dedupe` once to collapse any duplicate
  backlog left by older versions. Google OAuth credentials (client ID + secret,
  stored encrypted in the DB) are managed at `/settings/google-sheets`. A fetch
  that fails (e.g. an expired refresh token — see the production-consent note
  below) is recorded with its reason and shown as a red **Failed** row under
  "Recent imports" instead of a silent empty import, and the source then waits
  for its next scheduled slot rather than retrying every hour; fix the cause and
  hit **Fetch** to retry right away.
- 📥 **Meta Lead Ads lead source (API)** — pull leads straight from Meta Lead
  Ads instead of routing them through a Google Sheet. Once Meta is connected
  under **Settings → Ad platforms**, an **Imports → Meta Lead Ads (API)** page
  (`/imports/meta-leads`) appears. Configure one or more connections by Facebook
  Page ID (every active lead form on the page is pulled) or pin a single Form ID;
  a **"Load forms"** button validates the token and lists the page's lead forms.
  Standard Meta fields (`full_name`, `email`, `phone_number`, first/last name)
  map onto the core lead columns plus the pre-wired Meta attribution fields
  (ad / adset / campaign / form / platform), and every other answer is preserved
  as a custom answer. The Meta lead id is the stable `external_id`, so re-fetches
  are **idempotent**. Each connection has its own look-back window, refresh
  interval and active toggle; "Fetch now" runs an immediate import, and the
  scheduler sweeps due connections hourly via `lodgely:meta-leads:fetch`. Reuses
  the existing Meta access token — it must additionally carry the
  `leads_retrieval` permission and access to the page that owns the forms.
  **Set the Google OAuth consent screen to "In production"** — apps left in
  Testing status get their refresh tokens expired by Google after 7 days,
  which silently breaks the connection every week.
- 🌊 **OpenFlow lead source** — pull submissions from a self-hosted
  [OpenFlow](https://github.com/vidual-labs/openflow) form straight into a
  lodgely client. The **Imports → OpenFlow** page (`/imports/openflow`) lets an
  operator add one or more sources; each stores the OpenFlow base URL, a login
  email and an **encrypted** password (OpenFlow has no API token, so the
  connector signs in to mint a short-lived JWT and scrapes it from the login
  cookie). A **"Load forms"** button validates the login and lists the account's
  forms; **"Load fields"** then fetches the picked form's fields so the operator
  can map each one to a lead column (`full_name` / `email` / `phone` / `message`
  / status / priority / named custom answer). Any unmapped field is preserved as
  a custom answer using the OpenFlow field label, so the full submission
  survives. Each source is assigned to a **Client** (the source's default client
  name), so the leads land in exactly one customer's scope. The OpenFlow
  submission id is the stable `external_id`, making re-fetches **idempotent**;
  `last_fetched_at` additionally bounds incremental pulls so the whole backlog
  isn't re-walked each run. Each source has its own refresh interval and active
  toggle; "Fetch" runs an immediate import, and the scheduler sweeps due sources
  hourly via `lodgely:openflow:fetch`.
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
- ☑️ **Bulk actions** — operators select multiple leads via checkboxes (with
  select-all toggle) and apply a status change, priority change, or bulk delete
  to all in one step. Audit events recorded per lead.
- ⬇️ **Inbox export** — operators can download the currently filtered inbox as
  CSV or newline-delimited JSON (`/inbox/export?format=csv|ndjson`). Streams in
  chunks so it stays memory-safe at any size; honours the same `q / status /
  priority / source / client / sort` filters as the inbox URL. Excludes
  `raw_payload` and internal dedupe keys. Each export writes a `lead.exported`
  log line for auditability.
- 💾 **Backup & recovery** (`/settings/backups`, operator-only) — create a
  full-database backup as a single downloadable `.zip` (a `pg_dump` archive
  plus a manifest), download it to a local machine, prune old ones, or
  restore the database from a previously downloaded archive straight from
  the UI (typed "RESTORE" confirmation, since it overwrites every table and
  signs the operator out). The same flows ship as artisan commands —
  `lodgely:backup:create [--keep=N]` and `lodgely:backup:restore <path>` —
  for cron jobs and scripted server migrations. **Note:** integration
  secrets (Google Sheets, Google Ads, Meta, AI keys) are encrypted with the
  install's `APP_KEY`, so a backup restored onto a *different* server — or
  after `APP_KEY` rotation — can't decrypt them; you'll be prompted to
  re-enter and re-verify those credentials under Settings after such a
  restore.
- 🔖 **Saved filters & default views** — any filter combination (search, status,
  priority, source, client, sort) can be saved as a named view. Saved views appear
  as chips in the filter bar; one can be starred as the user's default, loaded
  automatically on each inbox visit.
- 🧱 **Per-user column picker** — a "Custom columns" toggle in the filter bar
  expands an inline chip row where each user picks which fields the inbox
  table renders (`received`, `name`, `email`, `phone`, `client`, `source`,
  `campaign`, `form`, `platform`, `status`, `priority`, `outreach`). Each
  chip toggle auto-persists to `users.inbox_columns`. The picker also
  auto-discovers questions present in the user's leads'
  `custom_answers` and offers each as a column — clients whose Meta form asks
  "Event size" can promote it to its own column. Capped at 8 columns total
  (5 custom-question columns max) to keep the table readable. Defaults are
  role-aware: operators see `client`, clients drop it as redundant. Even
  `received` is removable — operators tracking a different date in custom
  answers can swap it for that custom column.
- 🌙 **Dark / Light mode switch** — OS preference is respected on first load; a labeled pill toggle (`Light · Dark`) in the topbar lets users switch manually. For authenticated users the choice is saved to `users.ui_theme` in the database and injected server-side on the next load (no localStorage flash); guests fall back to `localStorage`.
- 🌍 **i18n ready** — all UI strings go through Laravel's `__()` helper. Ships with English (`en`) and German (`de`). Language is switched via a `POST /locale` route; for authenticated users the preference is saved to `users.locale` in the database; for guests it falls back to session.
- 📈 **Reporting** — operator-only `/reporting` page with platform/date-range filters, KPI cards (total spend, clicks, impressions, cost per lead, lodgely lead count), a row of **modern trend charts** (TradingView-style daily area/line charts — smooth line, gradient fill and an interactive hover crosshair + tooltip, all dependency-free inline SVG, no chart library), a per-campaign breakdown table (spend, CPL, platform leads vs. lodgely leads), and a leads-by-source table. A **"Clear ad-metrics data"** button wipes the `ad_spend_reports` rows — the mock spend a demo install ships with has no per-import tag, so this is the way to get a clean slate (mock sources repopulate on the next import run). Ad spend data is ingested via swappable adapter classes (`AdMetricsSource`) — ships with deterministic mock adapters for Meta and Google Ads, plus live API adapters that pull aggregate campaign metrics from Meta's Marketing API and Google Ads' REST API once credentials are configured. Operators connect both platforms from **Settings → Ad platforms** (`/settings/ad-platforms`): paste the Meta access token / ad account, and for Google Ads click **"Connect Google Ads"** for a one-click OAuth flow that captures the refresh token automatically — no `.env` editing or token scripts. Credentials are stored encrypted, each platform has a "Test connection" button, and per-platform Enable toggles control the daily pull. Env vars (see [Configuration reference](CONFIGURATION.md)) remain supported as a fallback. Scheduled to pull yesterday's data daily at 05:00 — so a freshly connected platform shows an empty report until that run. A **"Fetch data now"** button (header toolbar and empty state) triggers an immediate pull of the last 7 days, so reporting fills right after you connect a platform without waiting for the cron. Run `php artisan lodgely:import:ad-metrics --days=30` to seed/backfill a wider window from the CLI.
- 📊 **Custom client reporting views** — operators define named reporting views by selecting any combination of metrics (Leads, Clicks, Impressions, CTR, Ad Spend, Cost per Lead, Platform Leads, **CPC**, **CPM**, **Conversion rate**, etc.) and assign each view to specific client users. Each view has a **Live / Hidden** toggle: views default to Live (assigning a client makes them visible), and an operator can take a view offline without unassigning anyone — hidden views disappear from clients' "My reports" and pause their scheduled report emails. Clients see a "My reports" tab page at `/my-reports` with per-metric monthly **trend charts** (the same modern TradingView-style area/line charts as the operator dashboard, with a hover crosshair + tooltip — dependency-free inline SVG) above a monthly table, showing only their assigned columns. Different clients can see entirely different views — client A might see only Leads and Clicks, client B sees full ad performance metrics. Lead data is always scoped to each client's own leads.
- 🤖 **AI summaries & lead qualification** *(optional, off by default)* — admins plug in either an OpenAI-compatible API (OpenAI, Together, Groq, LM Studio, …) or a local Ollama endpoint at `/settings/ai`, set a free-text "house style" instruction, and choose which AI tasks to enable. Two kinds in v1: **report-view summaries** (narrative + evaluation + follow-ups on a custom reporting view, aggregate data only) and **lead qualification** (priority recommendation with pseudonymized lead context). Every AI output is a draft that an operator reviews at `/ai/drafts` — approve, reject, regenerate, then share with the client. API keys are stored encrypted; lead-level kinds require an explicit consent toggle; a daily per-tenant cap prevents cost runaway. See [Architecture → How AI summaries work](ARCHITECTURE.md#how-ai-summaries-work) for the full generation/review flow.
- 📨 **Custom client report emails** — operators compose modular report emails at `/reporting/emails` and either send them now, schedule them as a one-off, or recur them weekly / monthly. Each template picks any combination of: a free-text intro (markdown), the KPI summary strip, the monthly metrics table, and the latest operator-approved AI summary for a reporting view. The HTML email is **mobile-responsive** — a `<style>` media query stacks the KPI cards one-per-row and lets the metrics table scroll/shrink on phones, so it reads cleanly in iOS / Gmail / webmail. Recipients are existing Client users (visibility is honoured because the metrics are built against the recipient). Every dispatch writes a `client_report_email_sends` audit row that surfaces as a "Recent sends" history. The `lodgely:report-emails:dispatch` artisan command runs hourly via the scheduler and advances each schedule's `next_run_at`.
- ✉️ **Outbound mail (SMTP) settings** — operators configure the mail server from **Settings → Email** (`/settings/mail`): transport (SMTP or log-only), host, port, encryption (STARTTLS / SSL / none), username, password, and the From identity — no `.env` editing required. The password is stored encrypted, and the saved settings override the `MAIL_*` env config at runtime for **both** web requests (password resets) and the queue worker (reporting emails). A **"Send test email"** button sends a real message synchronously so SMTP errors surface immediately. The default `MAIL_MAILER=log` driver writes mail to the log instead of sending it — the usual reason reporting emails appear not to arrive — so switching this to SMTP here is what gets mail flowing. Env vars remain supported as a fallback when the toggle is off.
- 🧪 **Demo data toggle** — operators get a `/settings/demo-data` page (under the topbar **Settings** menu) with two buttons: load the canonical demo dataset (~60 neutral leads + 12 Meta Lead Ads leads across two demo clients, a known duplicate pair, and the two scoped demo client logins `client.northwind@example.com` / `client.acme@example.com` with password `password`) or wipe it again. Demo leads are tagged by attaching them to a dedicated `imports` row with `source = 'demo_seed'`, so unloading is a single scoped delete — real CSV / webhook / IMAP imports are never touched. Unloading **also clears the mock ad-spend rows** behind Reporting (skipped automatically once a live Meta or Google Ads connection exists, so real spend is never deleted — clear that from the Reporting page instead). Same code path the `DatabaseSeeder` uses, lifted into a reusable `App\Domain\Demo\DemoDataManager` service.
