# Changelog

All notable changes to lodgely are documented here. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
semantic-ish versioning once a 1.0 is tagged.

## [Unreleased]

### Changed

- **Inbox "Received" column is now pickable.** Previously the first column
  (lead `created_at`) was a fixed anchor; now it's listed alongside the other
  columns in the column picker. Operators who track a different "date" in
  custom answers (e.g. a mapped `created_time` from Google Sheets) can hide
  Received and surface that custom column instead. Total column cap bumped
  from 7 to 8 to accommodate Received as a default pick.

### Added

- **Delete import** — each row in the "Recent imports" table on the Google Sheets
  page now has a Delete button. Deleting an import also removes all leads it
  created (confirmed before proceeding with an exact lead count).

### Fixed

- **"Custom answer (named key)…" key input now appears immediately** when the
  field dropdown is switched to that option. Changed from Alpine `x-show`
  (which didn't react to `wire:model` without `.live`) to `wire:model.live`
  + Blade `@if`, matching the pattern used elsewhere in the codebase.

### Added

- **Google Sheets column mapping: `lead_id`, `form_id`, `created_time` fields.**
  Operators can now map sheet columns to the external lead ID (`lead_id` →
  `meta_lead_id`), form ID (`form_id`), and creation timestamp (`created_time`,
  stored in `custom_answers`).
- **Named custom-answer columns.** Selecting "Custom answer (named key)…" in the
  column-mapping dropdown reveals a key-name text field. The value is stored as
  `custom_answer:<key>` in `column_map` and surfaces under that key in the
  lead's `custom_answers` JSON.

### Fixed

- **Google Sheets custom answers now appear in the inbox.** `GoogleSheetsLeadSource`
  now emits `custom_answers` as a list of `{question, answer}` objects (matching
  the Meta Lead Ads convention) instead of a flat key-value map. The sheet column
  header becomes the question label when present; named custom-answer keys fall
  back to a humanised version of the key (e.g. `event_size` → "Event size"). This
  makes UTM tags, `question_01–04`, `is_quality`, `is_converted`, `created_time`
  and operator-named custom answers show up in the inbox column picker under
  "Custom form questions" and in the lead-detail panel.

---

## [0.20.0] · 2026-05-19

### Added

- **Google Sheets lead source.** Operators can now configure multiple Google
  Sheets as recurring lead sources at `/imports/google-sheets`. Each sheet
  source stores its own spreadsheet ID, range, header-row flag, column
  mapping, default client/campaign names, refresh interval, and active flag.
  - **Column mapping UI.** A "Load columns" button fetches the first row of
    the sheet and surfaces each column by its header name (or letter when no
    header). Operators assign each column to a lead field via dropdown.
    Mappings are stored as an index-based JSON map so header renames do not
    break imports.
  - **Per-sheet refresh interval.** Choose hourly, every 6/12/24 hours,
    2 days, or weekly. The `google_sheet_sources` table records
    `last_fetched_at` so each source skips itself if not yet due.
  - **"Fetch now" button** in the list view triggers an immediate import and
    shows the result count as a toast notification.
  - **`GoogleSheetsLeadSource`** importer registered in `AppServiceProvider`
    under key `google_sheets`; passes `IncomingLead` DTOs to `LeadIngestor`
    via the existing `ImportRunner` contract.
  - **`lodgely:google-sheets:fetch` artisan command** with `--source=<id>`
    and `--force` flags; scheduled to run hourly via `routes/console.php`.
  - **`google_sheet_sources` migration** with indexes on
    `(tenant_id, is_active)`.

### Fixed

- **Google Sheets redirect URI now uses `APP_URL`** instead of `route()`,
  so the generated URI always carries the scheme from the operator's
  configured public address. Previously, when PHP received plain HTTP from
  a reverse proxy (Caddy, nginx, Cloudflare), the redirect URI was `http://`
  even on HTTPS sites, causing Google Cloud Console to reject it.

### Changed

- **Google Sheets settings page setup guide** expanded into a numbered
  step-by-step card with direct links to Google Cloud Console (Sheets API
  Library, OAuth consent screen, Credentials), a one-click copy button for
  the redirect URI, and an HTTPS warning banner when `APP_URL` is not
  `https://`.
- **Imports nav** now includes a "Google Sheets" entry pointing to
  `/imports/google-sheets` and a separate "Google Sheets settings" entry for
  the OAuth/credential page.

---

## [0.19.0] · 2026-05-19

### Added

- **Google Sheets settings page.** New operator-only page at
  `/settings/google-sheets` (reachable from the Imports nav) where operators
  enter their Google OAuth client ID and secret, click "Connect to Google" to
  run the consent flow, and disconnect or test the connection — all without
  touching `.env`. Credentials are stored encrypted in a new
  `google_sheets_settings` DB table via a `GoogleSheetsSetting` model.
  The `GoogleSheetsClient` service reads from the DB first (falling back to
  the legacy `LODGELY_GOOGLE_SHEETS_*` env vars for existing installs).
  The OAuth callback saves the refresh token to the DB automatically
  and redirects back to the settings page with a flash confirmation.
- **`phpunit.xml` self-contained.** Added `APP_KEY` and
  `LODGELY_DEFAULT_RETENTION_DAYS` so the test suite runs without a local
  `.env` file.
- **Per-user inbox column picker.** A "Columns" button in the filter
  bar lets each user toggle which columns the inbox table renders. Pickable
  static columns: `name`, `email`, `phone`, `client`, `source`, `campaign`,
  `form`, `platform`, `status`, `priority`, `outreach`. Picks are persisted
  to `users.inbox_columns` (JSONB). The picker also auto-discovers
  custom-question keys from `custom_answers` across visible leads and offers
  each as a toggleable column. Capped at 7 total / 3 custom-question columns.
- **Meta Lead Ads sample data and "Meta-aware" lead detail view.** The lead
  detail panel renders *Ad source*, *Custom questions*, and *Outreach* sections
  when applicable. Outreach pills (Qualified / Called / Mailed) are settable
  by clients and write `lead.outreach_toggled` audit events. New
  `lodgely:import:meta-mock` artisan command seeds Meta demo data without
  re-running the full seeder.

### Changed

- **Roomier inputs and selects.** Text inputs, selects and textareas now
  use explicit `0.5rem` block / `0.75rem` inline padding via global CSS.
- **Footer GitHub link hardcoded** to the upstream repo as part of GPL-3.0
  attribution; removed the `LODGELY_GITHUB_URL` env toggle.

### Fixed

- Topbar logo height is now set inline (`style="height: 2.5rem"`) instead of via the Tailwind `h-10` utility. Tailwind v4 only ships classes it can find at build time, so when a deploy pulled the latest blade template without re-running `npm run build`, `h-10` was missing from the compiled CSS bundle and the browser fell back to the image's natural 1774×887 size — the logo filled the page. Inline style sidesteps the build dependency entirely.
- Added `'unsafe-eval'` to the `script-src` directive in `SecurityHeaders` middleware. Alpine.js (bundled with Livewire 3) evaluates `x-show`, `@click`, `:class`, etc. via `new Function()`, which the previous CSP blocked — so every Alpine directive silently failed. Symptoms: dropdowns (Reporting / Imports / AI) stuck visible, light/dark toggle inert, several `wire:click` handlers dead.
- Bumped the topbar brand logo from `h-8` to `h-10` so the wordmark reads cleanly at the topbar's `h-14` row.
- Removed the manual `alpinejs` import from `resources/js/app.js`. Livewire 3 bundles its own Alpine and starts it automatically; importing Alpine a second time mounted it twice, which left `x-data` dropdowns (Reporting, Imports, AI) permanently open, broke the light/dark theme toggle, and disabled `wire:click` handlers (clicking a lead row in the inbox did nothing).
- Swapped the topbar and auth-screen logo from `img/logo.svg` to `img/logo.png`. The SVG used Inter and did not match the brand wordmark; the PNG is the authoritative artwork.
- Bumped Docker base image from `php:8.3-fpm-alpine` to `php:8.4-fpm-alpine` to match the PHP 8.4 requirement of the locked Symfony 8.x dependencies; composer install now succeeds without errors.
- Added `package-lock.json` so `npm ci` works in clean Docker/CI environments.
- Removed `imap`, `imap-dev`, `krb5-dev` and the `docker-php-ext-configure imap` step from the Dockerfile; PHP 8.4 dropped the `imap` extension from its bundled set, causing `docker build` to fail. The IMAP email driver is optional (default `LODGELY_EMAIL_IMPORT_DRIVER=mock`) — see the config reference for how to enable it with a custom image.
- Added `trustProxies(at: '*')` in `bootstrap/app.php` so Laravel correctly reads forwarded HTTPS headers from reverse proxies (Cloudflare, nginx, etc.), fixing 419 CSRF errors and broken secure-cookie sessions behind a proxy.
- Improved Quick start (Docker) docs: added required `.env` keys table, storage permissions step, and a "Behind a reverse proxy / Cloudflare" section explaining `APP_URL`, `SESSION_SECURE_COOKIE`, and proxy trust.
- Updated `.env.example` with clearer inline comments, safer defaults (`SESSION_DRIVER=file`, `SESSION_SECURE_COOKIE=false`), and explicit `LODGELY_HTTP_PORT` / `LODGELY_HTTPS_PORT` entries so port conflicts are easier to resolve.

### Added

- **GitHub Actions CI workflow.** `.github/workflows/ci.yml` runs on every
  push to `main` and on every pull request: installs composer deps on
  PHP 8.4 (with composer cache) and runs the full `vendor/bin/phpunit`
  suite against the in-memory SQLite config in `phpunit.xml`. No
  external services, no matrix — one green check per PR. (Pint is
  intentionally left out for now; the codebase isn't Pint-clean and
  enforcing it would block every PR until a separate formatting pass.)

- **Live Meta Ads and Google Ads API adapters.** Two new ad metrics sources
  ship alongside the existing mocks: `MetaAdsSource` (Marketing API
  `/act_{id}/insights`, campaign-level only) and `GoogleAdsSource` (REST
  `googleAds:search` over a GAQL campaign query, OAuth refresh-token flow
  with the access token cached for 55 minutes). Both adapters implement
  the existing `AdMetricsSource` contract and feed `MetricsIngestor`
  unchanged — aggregate metrics only, no PII leaves either platform.
  Activate by setting `LODGELY_AD_METRICS_SOURCES=meta,google` and
  providing the relevant credentials (`LODGELY_META_ADS_ACCESS_TOKEN`,
  `LODGELY_META_ADS_ACCOUNT_ID`, `LODGELY_GOOGLE_ADS_CLIENT_ID`,
  `LODGELY_GOOGLE_ADS_CLIENT_SECRET`, `LODGELY_GOOGLE_ADS_REFRESH_TOKEN`,
  `LODGELY_GOOGLE_ADS_DEVELOPER_TOKEN`, `LODGELY_GOOGLE_ADS_CUSTOMER_ID`,
  optional `LODGELY_GOOGLE_ADS_LOGIN_CUSTOMER_ID`). Mocks remain the
  default for demo installs.

### Changed

- **`lodgely:import:ad-metrics` now honours `LODGELY_AD_METRICS_SOURCES`.**
  The command previously fanned out across every registered adapter; it
  now filters to the comma-separated list of source keys in
  `config('lodgely.reporting.sources')`. This is what the env var was
  always advertised to do, but the wiring was missing.

### Fixed

- **HTML sanitization on report email intro text.** `ReportEmailComposer` now
  runs `strip_tags()` with an explicit allowlist over the CommonMark-rendered
  intro HTML before passing it to the email view, preventing any script or
  unsafe tags from reaching `{!! $data['intro_html'] !!}` in the Blade template.
- **Webhook endpoint tenant scoping.** `WebhooksPage::toggleActive()`,
  `delete()`, and `revealToken()` now scope `WebhookEndpoint` lookups to
  `Tenant::DEFAULT_ID`, ensuring an authenticated operator cannot manipulate
  endpoints belonging to another tenant.

### Added

- **Password recovery and per-user profile page (v0.14.0).** Public
  `/forgot-password` and `/reset-password/{token}` routes (Laravel's
  password broker, 5-req/min throttle on every endpoint, 12-character
  minimum on the new password) let users recover access without an
  operator handout. Inactive accounts never receive a reset email,
  and the request form always returns the same generic confirmation so
  it cannot be used to enumerate accounts. A new `/profile` page —
  reachable from the topbar avatar by every role — lets users edit
  their name, email, language and theme, and change their password
  with a current-password challenge. Operators get a one-click
  "Reset link" action on each row of the `/users` table so they can
  hand off password setup to the user without ever seeing the value.
  The login form now exposes a "Forgot your password?" link and
  surfaces flash status messages after a successful reset.

### Changed

- **Logo replaced with vector SVG.** `public/img/logo.svg` replaces the
  raster `logo.png` in the login page and topbar. The SVG is crisp at
  every size and adds a subtle inner border so the dark pill reads
  cleanly on both light and dark page backgrounds.

### Added

- **Ethical use statement.** Added a non-binding preamble to `LICENSE`
  and a matching `## Ethical use` section in `README.md` asking that
  lodgely not be used to run lead intake for clients in the weapons,
  fossil-fuel energy, or internal-combustion vehicle industries. Framed
  as an ethical request because GPL-3.0 §10 prohibits further
  restrictions on the software itself.
- **Footer version badge + GitHub link.** The app footer now shows the
  current package version (read from `composer.json` via the new
  `lodgely.version` config value, so it stays in sync with the
  every-commit version bump) and an icon-labelled link to the source
  repository. The link target is configurable via `LODGELY_GITHUB_URL`
  and disappears when the env var is left blank, so self-hosted
  deployments that don't want to advertise a public repo can hide it.

### Changed

- **Topbar nav restructured into grouped dropdowns + mobile hamburger.**
  The operator topbar used to render 8–11 sibling links across a single
  row, which overflowed on mid-sized desktops and was completely hidden
  on screens below `md` (mobile users had no way to navigate after
  login). The new layout collapses `CSV import / Email (mock) / Email
  (IMAP)` under an **Imports** dropdown, `Reporting / Report views /
  Report emails` under **Reporting**, and `AI drafts / AI settings`
  under **AI**, leaving Inbox / Users / Webhooks as top-level links.
  A hamburger button (`< lg`) now opens a full grouped panel for
  mobile and small-laptop widths, including the sign-out action that
  was previously only reachable on `sm+` screens.

### Fixed

- Saved-filter star button on the inbox showed the nonsensical title
  "Default view – Clear filters" while already-default; the tooltip now
  reflects what the action will do ("Set as default view" /
  "Remove as default view") and exposes the same string via
  `aria-label` for screen readers.
- The new-lead modal, user create/edit modal, webhook create modal,
  and lead detail panel now close on `Escape`. The three modals also
  close when clicking the backdrop, matching the existing behaviour
  of the report-email and report-view modals.
- Footer stacks vertically on `< sm` screens instead of cramming both
  notices onto one line.

### Added

- **Operator-only inbox export to CSV and NDJSON.** New `/inbox/export`
  endpoint streams the currently filtered/sorted lead set to disk in
  either CSV (header row + one row per lead) or newline-delimited JSON
  (one lead object per line). Filters and sort are read from the same
  query keys the inbox already URL-binds (`q`, `status`, `priority`,
  `source`, `client`, `sort`), so the inbox "Export CSV" / "Export JSON"
  buttons just link to the route with the current filter state preserved.
  Visibility goes through `Lead::scopeVisibleTo()`; clients receive 403.
  Streaming via `lazyById()` keeps memory bounded for large workspaces.
  Each export is logged as a single `lead.exported` info line with user,
  format, row count and active filters. Internal/PII-adjacent columns
  (`raw_payload`, `email_normalized`, `phone_normalized`, internal IDs)
  are intentionally excluded.

### Changed

- Inbox filter/sort semantics extracted from `WithLeadFilters` into a
  shared `App\Domain\Leads\Services\LeadFilter` so the export controller
  and the Livewire inbox use one definition. Behaviour unchanged.

### Fixed

- `ReportEmailDispatcher::dispatchSchedule()` now skips sends when the
  underlying `ClientReportEmail` template is inactive. Previously a
  deactivated template would still fire on cron as long as the attached
  schedule's `is_active` flag was true, because only the schedule row
  was being checked. Deactivating a template now behaves as the operator
  expects — a pause on all triggers.
- `ReportEmailsPage::save()` no longer overwrites `created_by` when an
  existing template is edited. The original author is preserved, and
  `created_by` is only set on creation.

### Added

- **Custom client report emails.** Operators can compose modular report
  emails at `/reporting/emails` and send them now, schedule them as a
  one-off, or recur them weekly / monthly. Each template chooses any
  combination of: a free-text intro (markdown), the KPI summary strip
  for a `ClientReportingView`, the monthly metrics table, and the
  latest operator-approved AI summary for that view. Recipients are
  picked from existing Client users; visibility scoping is honoured
  because metrics are built through `ClientViewDataBuilder` against
  the recipient. Every dispatch writes a `client_report_email_sends`
  audit row (period covered, recipient ids, AI summary id, status,
  error) which renders as a "Recent sends" history on the page.
  New artisan command `lodgely:report-emails:dispatch` (with
  `--dry-run`) runs hourly via the scheduler. New tables:
  `client_report_emails`, `client_report_email_recipients`,
  `client_report_email_schedules`, `client_report_email_sends`.
  New mailable `App\Mail\ClientReportEmailMessage` + queued job
  `App\Jobs\SendClientReportEmail`.

### Changed

- Extracted the inbox KPI query out of `App\Livewire\Inbox\InboxPage` into
  `App\Domain\Leads\Services\LeadKpis::compute(Builder $base)`. The Livewire
  page now resolves the service via `render()` constructor-injection; the
  page no longer carries raw aggregate SQL or a `DB` facade import. Added
  `tests/Feature/LeadKpisTest.php` covering zero/aggregate/scope cases.
- Replaced the placeholder "L" gradient square in the topbar and login screen
  with the actual lodgely logo graphic (wordmark + dot mark). The logo is
  served from `public/img/logo.png`, copied from the existing
  `.github/logo.png` artwork already referenced by the README.
- Decoupled `WithLeadFilters` from `WithBulkLeadActions`. `clearFilters()`
  no longer reaches into the bulk trait's `$bulkSelected`; it dispatches a
  self-targeted `inbox-filters-cleared` Livewire event, and the bulk trait
  reacts via an `#[On('inbox-filters-cleared')]` listener. Each trait now
  only knows the event name, not the other trait's state.

### Added

- `tests/Feature/InboxPageTest.php` — Livewire feature coverage for the
  inbox page: URL filters, saved views (default load + persist + toggle),
  bulk status updates with audit events, manual-lead form (validation,
  authorization, happy-path ingest), and per-lead note creation.

### Changed

- Refactored `App\Livewire\Inbox\InboxPage` (532 → 260 lines): extracted
  saved-views, bulk lead actions, the manual-lead modal, and URL filter
  state into composable traits under `App\Livewire\Inbox\Concerns\`
  (`WithLeadFilters`, `WithSavedFilters`, `WithBulkLeadActions`,
  `WithManualLeadForm`). No behavior changes — the route, view bindings
  and request handling are unchanged.

### Security

- Added `throttle:5,1` rate limit to the login POST endpoint to prevent brute-force attacks.
- Added `SecurityHeaders` middleware (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Content-Security-Policy) applied to all web responses.
- Removed `lead_id` from webhook 201 response to prevent lead-count enumeration.
- Added payload key-count guard (>20 keys → 422) to the webhook endpoint.
- Wrapped `SavedFilter` default-toggle in a `DB::transaction` to prevent a race condition leaving no default filter.
- Fixed unescaped `{!! !!}` in the user password hint template; emphasis is now rendered via inline HTML rather than via a PHP-translated string.
- Hardened `.env.example` defaults: `APP_DEBUG=false`, `LOG_LEVEL=error`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`; added `php artisan key:generate` hint; cleared IMAP password example value.

### Added

- **AI summaries & lead qualification** (`app/Domain/Ai/`) — operators
  can plug in either an OpenAI-compatible API (OpenAI, Together, Groq,
  LM Studio, …) or a local/self-hosted Ollama endpoint. Two AI tasks
  ship in v1: report-view summaries (aggregate data, no PII leaves the
  server) and lead qualification (pseudonymized PII — masked name /
  email / phone). All AI output is a draft that flows through an
  operator approval gate before reaching a client.
  - `ai_settings` table — per-tenant provider config (provider, base URL,
    encrypted API key, model, "house style" instruction, per-kind toggles,
    explicit `lead_data_consent` checkbox required for lead-level kinds).
  - `ai_summaries` table — drafts with status `pending → approved → shared`
    (and `rejected` / `failed`). Stores prompt verbatim so disclosures
    are auditable.
  - `ai_events` table — audit trail (sibling of `lead_events`). API keys
    are redacted from every recorded payload.
  - `LlmProvider` contract + `OpenAiCompatibleProvider` and
    `OllamaProvider` implementations, registered in
    `AppServiceProvider::LLM_PROVIDERS`. New providers are adapters, not
    new tables.
  - `AiSummarizer` service — the only place that calls providers. Mirrors
    the centralization rule from `LeadIngestor`.
  - `Pseudonymizer` — masks `full_name → "Lead #N"`, emails and phones,
    drops PII-shaped keys from `raw_payload`. Only the lead kind is
    pseudonymized; aggregate kinds work on totals.
  - `GenerateAiSummary` queued job — calls the configured provider,
    writes back `response` + `model` + `token_usage`, leaves the row at
    `pending` for operator review. Enforces a per-tenant daily call cap
    (`LODGELY_AI_MAX_CALLS_PER_DAY`, default 100).
  - `EnsureAiEnabled` middleware — `/settings/ai` and `/ai/drafts` 404 when
    `LODGELY_AI_ENABLED=false` (master kill-switch). All trigger buttons
    are also hidden in that state.
  - New Livewire pages: `AiSettingsPage` at `/settings/ai` (provider
    config, write-only API key, "Test connection" button) and `DraftsPage`
    at `/ai/drafts` (review, approve, reject, share, regenerate).
  - Trigger buttons added to `ReportingViewsPage` rows, `MyReportsPage`
    (operator view), and the lead detail panel. Approved & shared
    report-view summaries render as a card above the table in
    `/my-reports`; approved lead-qualification summaries render in the
    operator's lead panel.
  - Off by default — `LODGELY_AI_ENABLED=false` ships in `.env.example`.
- **Custom client reporting views** — operators can now define named reporting views (choosing any combination of metrics from: Leads, New Leads, Reviewed Leads, Clicks, Impressions, Ad Spend, Reach, CTR, Cost per Lead, Platform Leads) and assign each view to specific client users. Clients see a "My reports" page with a tab per assigned view and a monthly time-series table showing only the selected columns. Different clients can see entirely different views. Operators see all views at `/my-reports` for preview. New nav links: "Report views" for operators, "My reports" for clients.
  - `client_reporting_views` table — stores view name and JSON column list per tenant.
  - `client_reporting_view_user` pivot table — maps views to client users with cascade-delete.
  - `ReportColumn` enum (`app/Domain/Reporting/Enums/`) — single registry of all selectable metric keys with labels, descriptions, format helpers, and source flags.
  - `ClientReportingView` model with `columnEnums()`, `assignedUsers()` pivot relation.
  - `ClientViewDataBuilder` service — merges ad-spend and lead sub-queries by month, zero-fills gaps, computes CTR and CPL, respects `Lead::scopeVisibleTo()` for lead metrics.
  - `ReportingViewsPage` Livewire component at `/reporting/views` (operators only) — full CRUD with column checkbox grid and client assignment.
  - `MyReportsPage` Livewire component at `/my-reports` — tab-based view selector, 3/6/12-month range filter, KPI summary strip, monthly table.

- **README header** — added lodgely logo, shields.io badges (license, PHP, Laravel, Livewire, version, GitHub stars), and a table of contents.

- **Reporting module** (`app/Domain/Reporting/`) — light ad spend ingestion and campaign rollup dashboard for operators:
  - New `ad_spend_reports` table stores daily aggregate metrics per campaign (platform, campaign ID/name, impressions, clicks, spend in cents, currency, reach, platform-reported lead count). No PII stored. Unique constraint on `(tenant_id, platform, date, campaign_id)` makes re-ingestion idempotent.
  - `AdMetricsSource` contract (adapter interface): `platform()`, `label()`, `fetch(tenantId, date): iterable<AdMetricsSnapshot>`. Drop a class implementing this, register it in `AppServiceProvider::AD_METRICS_SOURCES`, and it runs on schedule.
  - `AdMetricsSnapshot` readonly DTO carries one day's aggregate metrics from an ad platform into `MetricsIngestor`.
  - `MetricsIngestor` service upserts snapshots into `ad_spend_reports` (insert or update by unique key).
  - `CampaignRollup` service aggregates spend/click/impression totals across the date range and joins with lodgely's own lead counts via `leads.campaign_id`.
  - Mock adapters: `MetaMockAdMetricsSource` (3 campaigns, deterministic seed) and `GoogleMockAdMetricsSource` (2 campaigns) for demos and dev.
  - `lodgely:import:ad-metrics` artisan command — `--days=N`, `--date=YYYY-MM-DD`, `--platform=meta|google` options. Scheduled daily at 05:00.
  - `/reporting` Livewire page (operators only): platform pill filter (All / Meta / Google), date range pill (7 / 30 / 90 days), KPI cards (total spend, clicks, impressions, platform leads, lodgely leads), cost-per-lead callout, per-campaign breakdown table, and leads-by-source table from lodgely's own data.
  - `Reporting` link added to operator nav in the topbar.
  - `LODGELY_AD_METRICS_SOURCES` env var (comma-separated) controls which sources run; defaults to `meta_mock,google_mock`.
- **Meta Lead Ads fields on `leads` table** — ten new nullable columns cover the structural fields present on every Meta lead: `meta_lead_id` (Meta's own ID, for idempotent webhook ingestion), `ad_id` / `ad_name`, `adset_id` / `adset_name`, `campaign_id`, `form_id` / `form_name`, `platform` (`facebook` | `instagram`), and `is_organic`. Indexes added on `(tenant_id, meta_lead_id)`, `(tenant_id, ad_id)`, and `(tenant_id, form_id)`. Dynamic per-form custom question answers continue to flow through `raw_payload`.
- `IncomingLead` DTO extended with the same ten optional Meta fields so the future Meta Lead Ads importer adapter can pass them through to the ingestor without any further changes.

### Fixed

- **Client persona could mutate leads from the inbox** — `setStatus`, `setPriority`, `reconcileDuplicate`, and `addNote` on `InboxPage` only checked lead visibility, not the operator role, so a client user with `user_lead_scopes` matching a lead could change its status/priority or add notes via Livewire. Now matches every other action on the page and aborts with 403 for non-operators.
- **Lead detail panel rendered operator-only controls to clients** — the Workflow status/priority `<select>`s, the duplicate "Re-check" button, and the Add-note form are now wrapped in an operator gate. The notes list itself stays visible to clients.
- **Webhook-created leads lost their audit actor** — `WebhookController` now passes the endpoint's `user_id` to `LeadIngestor::ingest()`, so `lead_events.user_id` is populated instead of falling back to a null `Auth::id()` on the API request.
- **`saved_filters.tenant_id` lacked a foreign-key constraint** — replaced the plain `unsignedBigInteger` column with a proper `foreignId('tenant_id')->constrained()->cascadeOnDelete()`, matching every other tenant-scoped table.
- License listed as MIT in README, composer.json, and app footer — corrected to GPL-3.0 to match the `LICENSE` file.

## [0.7.0] · 2026-05-15

### Added

- **Per-user preference persistence** — language and theme are now stored in the `users` table (`locale`, `ui_theme` columns). For authenticated users: the `SetLocale` middleware reads `users.locale` (falling back to session for guests); clicking a language option saves to DB via `POST /locale`; toggling Light/Dark fires a background `fetch` to `POST /user/theme` and saves to `users.ui_theme`. On the next page load the server injects the stored theme into the FOUC-prevention script, so the correct mode is applied before any CSS loads without relying solely on `localStorage`.
- **i18n / localization infrastructure**: JSON-based translations via Laravel's `__()` helper. All user-visible strings in every Blade view are now wrapped in `__()`. Language files ship for `en` (English) and `de` (German). A `SetLocale` middleware reads the locale from session on every request. A `POST /locale` route lets the language switcher in the topbar persist the choice server-side.
- **Language switcher** in the topbar: pill-shaped EN / DE toggle (same visual style as the dark mode switch). Works on both authenticated and guest pages.
- **Dark / Light mode pill switch**: replaced the single icon button with a labelled two-option pill (`Light · Dark`) that clearly shows the active mode and makes both choices a single click.
- **Dark mode** with OS-preference detection and manual toggle. All pages, modals, side panels, tables, and form controls support `dark:` variants. The `@custom-variant dark` directive in Tailwind CSS v4 enables class-based toggling via `.dark` on `<html>`.
- **Modernized UI**: cards and panels now use `rounded-xl` / `rounded-2xl` with `shadow-sm`; topbar is sticky with `backdrop-blur` glass effect; brand logo uses a gradient; KPI cards have a colored top-accent bar; buttons use `transition-colors`; focus rings use the brand color.
- **Saved filters and per-user view defaults**:
  - Any combination of search, status, priority, source, client, and sort can be saved as a named filter via a "Save view" button in the filter bar.
  - Saved filters appear as chips; clicking applies the filter set instantly.
  - One filter can be starred as the user's default, loaded automatically on `/inbox` visits with no explicit URL parameters.
  - New `saved_filters` table (`user_id`, `tenant_id`, `name`, `filters` JSONB, `is_default`).
- **Bulk actions** in the inbox:
  - Per-row checkboxes and a "select all on page" header checkbox for operators.
  - Bulk action bar with set-status and set-priority dropdowns; audit events recorded per affected lead.
- **Dynamic source filter** — Source dropdown queries distinct sources present in the visible lead set rather than a hardcoded list.

### Changed

- Brand logo updated to `bg-gradient-to-br from-brand-500 to-brand-900` gradient throughout.
- Login card uses `rounded-2xl` with depth shadow; sign-in button uses brand color in dark mode.
- All primary buttons and filter inputs focus with `brand-500` ring.
- All modal/panel overlays carry `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` for screen-reader compatibility.
- Toast container carries `role="status"` / `aria-live="polite"` / `aria-atomic="true"`.
- Submit buttons on note, user, and webhook forms dim and disable during Livewire network requests (`wire:loading`).

## [0.2.0] · 2026-05-14

### Added

- In-app **user management** for operators (`/users`):
  - List / search / filter users by role.
  - Create and edit users (name, email, role, password, active flag).
  - Client users get a comma-separated list of `client_name` scopes; the
    list is reconciled on every save.
  - Inline enable / disable action with safety rail (cannot disable self).
  - Self-demotion from operator → client is blocked.
  - `Generate` button produces a strong random password and surfaces a
    one-time hint to the operator.
  - Sensitive changes are written to the standard Laravel log
    (`lodgely.user.*`) — promoted to a dedicated audit table later.
- **Webhook importer** — signed token URL per endpoint (`/webhooks`):
  - Operators create endpoints from the UI; each gets a unique 48-char token.
  - `POST /api/webhooks/{token}` accepts JSON leads and runs them through
    the full ingest pipeline (normalization, duplicate detection, audit).
  - Rate-limited to 60 req/min. Returns `201 {status, lead_id, duplicate}`.
  - Endpoints can be enabled/disabled and deleted from the UI.
- **Real IMAP email backend** (`LODGELY_EMAIL_IMPORT_DRIVER=imap`):
  - `ImapLeadSource` connects to any IMAP server, fetches unseen messages,
    and marks them read after processing.
  - `MailBodyParser` extracts name / phone / message from structured
    contact-form bodies; falls back to full-body message for plain prose.
    HTML bodies are stripped before parsing.
  - `lodgely:import:email-imap` artisan command; scheduled every 15 minutes
    when a host is configured.
  - `/imports/email-imap` Livewire page with connection status, optional
    overrides, and import history. Nav link shown only when host is set.
  - PHP `imap` extension added to the Docker image.

### Changed

- Topbar gains `Users` and `Webhooks` links for operators.
- `Email (IMAP)` nav link appears when `LODGELY_IMAP_HOST` is configured.
- `config/lodgely.php` gains a full `importers.email.imap` block.

### Notes

- `php artisan lodgely:user:create` stays available for bootstrap /
  scripted deployments; the UI is for day-to-day work.
- Mock email driver (`email_mock`) remains available for demos; it is still
  the default until `LODGELY_EMAIL_IMPORT_DRIVER=imap` is set.

## [0.1.0] · MVP

### Added

- Initial MVP scaffold:
  - Laravel 12 / PHP 8.3 modular monolith
  - Livewire 3 inbox with server-side search, filters and pagination
  - Lead detail side panel with status/priority editing, notes, audit trail
  - CSV importer (with header-alias matching) and mock email importer
  - Manual lead entry modal
  - Duplicate detection on normalized email & phone, with reconciliation
  - Two-role auth: `operator` (full access) and `client` (scoped by `client_name`)
  - Audit logging via `lead_events`
  - Retention support via `retention_until` + `lodgely:leads:purge` command
  - Docker Compose stack with Postgres 16, PHP-FPM, queue worker, Caddy
  - Seed data: one operator, two scoped client users, ~60 neutral demo leads
