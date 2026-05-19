# Changelog

All notable changes to lodgely are documented here. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
semantic-ish versioning once a 1.0 is tagged.

## [Unreleased]

### Added

- **Google Sheets settings page.** New operator-only page at
  `/settings/google-sheets` (reachable from the Imports nav) where operators
  enter their Google OAuth client ID and secret, click "Connect to Google" to
  run the consent flow, and disconnect or test the connection — all without
  touching `.env`. Credentials are stored encrypted in a new
  `google_sheets_settings` DB table via a `GoogleSheetsSetting` model.
  The `GoogleSheetsClient` service reads from the DB first (falling back to
  the legacy `LODGELY_GOOGLE_SHEETS_*` env vars for existing installs).
  The OAuth callback now saves the refresh token to the DB automatically
  and redirects back to the settings page with a flash confirmation.
- **`phpunit.xml` self-contained.** Added `APP_KEY` and
  `LODGELY_DEFAULT_RETENTION_DAYS` so the test suite runs without a local
  `.env` file — removes a silent dependency that caused failures in fresh
  CI environments.

### Changed

- **Roomier inputs and selects.** Text inputs, selects and textareas now
  get explicit `0.5rem` block / `0.75rem` inline padding via global CSS
  in `resources/css/app.css`, instead of relying on browser defaults
  which felt cramped next to the `text-sm` font used throughout the app.
  No view changes — applies everywhere automatically.

### Added

- **Per-user inbox column picker.** A new "Columns" button in the filter
  bar opens a panel where each user (operator or client) toggles which
  columns the inbox table renders. Pickable static columns: `name`,
  `email`, `phone`, `client`, `source`, `campaign`, `form`, `platform`,
  `status`, `priority`, `outreach`. The `Received` column is always
  on as the anchor. Picks are persisted to a new `users.inbox_columns`
  JSONB column (nullable — null = role-based default), so each user
  carries their layout across sessions and devices.
- **Custom form-question columns.** The picker also auto-discovers
  questions present in `custom_answers` across the user's visible leads
  and offers each as a toggleable column. The cell renders that lead's
  answer to that exact question, or "—" if absent. Use case: clients
  whose Meta form asks "Event size" can promote it to its own column
  alongside the standard fields.
- **Caps to keep the table readable.** Max 7 picked columns total
  (combined static + question), max 3 of which can be custom-question
  columns. Attempts to exceed either cap surface a "limit reached"
  toast. The picker also offers a one-click "Reset to default" that
  drops back to the role-based default (operators keep `client`;
  clients drop it as redundant).
- **Database migration** `2026_05_18_000230_add_inbox_columns_to_users_table`
  adds the JSONB column. Backward compatible — existing users land on
  the role-based default automatically.



- **Meta Lead Ads sample data and "Meta-aware" lead detail view.** The lead
  detail panel now renders three additional sections when applicable:
  *Ad source* (platform, organic/paid badge, campaign / adset / ad / form
  names), *Custom questions* (Q&A pairs as they came back from the lead
  form), and *Outreach* (Qualified / Called / Mailed pill toggles). The
  outreach pills are settable by **clients** themselves — the request comes
  from the in-tool worker, not from the upstream lead source — and clicking
  a pill toggles a timestamped state that also surfaces as `Q` / `C` / `M`
  badges on the inbox row. Each toggle writes a `lead.outreach_toggled`
  audit event.
- **Database migration** `2026_05_18_000220_add_meta_lead_view_fields_to_leads_table`
  adds `custom_answers` (JSONB), `qualified_at`, `called_at` and
  `mailed_at` (timestamps) plus tenant-scoped indexes on the three
  outreach columns. Backward compatible — all columns nullable.
- **Seeder coverage for Meta leads.** `LeadFactory::meta()` state populates
  a realistic ad / adset / form combination, a Meta lead ID, platform
  (Facebook / Instagram), an organic-vs-paid flag, and 2–4 randomized
  custom-question answers drawn from a pool of plausible Lead Ads
  questions. `DatabaseSeeder` now creates six Meta leads per demo client
  (Northwind Studio, Acme Wellness) and pre-marks a handful as
  qualified / called / mailed so both client logins land on a populated,
  Meta-aware inbox out of the box.
- **`meta_ads` source label** added to the inbox source filter ("Meta Lead
  Ads"). Existing labels unchanged.
- **`lodgely:import:meta-mock` artisan command** for injecting Meta lead
  demo data into an existing install without re-running the seeder.
  Accepts `--count=N` (default 6, per client) and one or more
  `--client="Name"` flags; if no client is passed, the command spreads the
  leads across whichever `client_name` values already exist in the DB.
  Uses `LeadFactory::meta()` so each lead arrives with full ad attribution
  (campaign / adset / ad / form / platform / organic flag) and 2–4
  custom-question answers. Dev installs only — `fakerphp/faker` is a
  `require-dev` dependency.

### Changed

- **Footer GitHub link target is now hardcoded** to `https://github.com/vidual-labs/lodgely` and no longer reads `LODGELY_GITHUB_URL` from the environment. The link is part of GPL-3.0 source-attribution for the project and should not be a deployment-time toggle. Forks are free to change the constant in `config/lodgely.php`. Removed `LODGELY_GITHUB_URL` from `.env.example`.

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
