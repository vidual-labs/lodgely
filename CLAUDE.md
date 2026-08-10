# Working notes for Claude / future maintainers

This file is a concise orientation, not exhaustive docs (the README is the
full reference). It exists to keep future contributors — human or AI — from
wandering off the architectural rails, and to record the current feature
surface and the hard-won gotchas worth not repeating.

## Product north star

lodgely is a **lead intake hub**, not a CRM. If a feature request feels like
it belongs in HubSpot, Pipedrive or Salesforce, it almost certainly does
*not* belong in lodgely. Push back, or land it in a downstream tool.

The two real personas:

- **Operator** — agency person or inhouse marketer who needs the leads sorted.
- **Client** — small business owner who mostly wants to *see* their leads,
  plus enough light self-service to not have to ask an operator for every
  small thing: status/priority, the qualified/called/mailed outreach toggles,
  notes, bulk-editing status/priority, and CSV export are all available to
  clients, always scoped to their own leads via `Lead::scopeVisibleTo()`.
  What stays operator-only is anything destructive (bulk delete), anything
  that creates/imports data (manual lead entry, all lead-source config), and
  anything CRM-adjacent (AI evaluation, duplicate reconciliation, the raw
  NDJSON export). When adding a new lead action, default to opening it to
  both roles unless it's one of those three categories — that mirrors how
  the existing actions are split and is what "light self-service" means here.

## Tech stack

- **PHP 8.4 · Laravel 12 · Livewire 3.5.** Server-rendered Blade + Livewire,
  no SPA. `league/csv` for CSV parsing.
- **Tailwind CSS 4 · Alpine.js 3 · Vite 5.** Alpine only for local UI state
  (dropdowns, toggles); no front-end framework, no chart library (the reporting
  charts are hand-rolled inline SVG).
- **PostgreSQL** is the only supported DB (migrations use `jsonb`, partial
  indexes, etc.). **Queue driver is `database`** — the worker runs AI summaries
  and report emails. Sessions/cache configurable; tests run on SQLite `:memory:`.
- **Docker** for production (see Runtime environment below).
- **Outbound HTTP** goes through Laravel's `Http` client with timeout + retry;
  every integration is opt-in and credential-gated (self-hosted-friendly).

## Feature surface (what's actually built)

Beyond the lead inbox, these are all live — don't treat them as greenfield:

- **Lead intake** from CSV, Email (mock + real IMAP), Webhook, Manual entry,
  **Google Sheets** (`/imports/google-sheets`), **Meta Lead Ads via the
  Graph API** (`/imports/meta-leads`) and **OpenFlow forms**
  (`/imports/openflow`). All flow through `LeadIngestor`.
- **OpenFlow lead source** (`app/Importers/Openflow/`, `OpenflowSource`) —
  pulls a self-hosted OpenFlow form's submissions into a single client. Auth is
  a **read-only OpenFlow API token** (preferred, stored **encrypted**, sent as a
  Bearer token) or an email + **encrypted** password login fallback (mints a
  short-lived JWT per pull, scraped from the login cookie). Operator-defined
  field mapping; unmapped fields become custom answers; idempotent on the
  OpenFlow submission id. Recurring via `lodgely:openflow:fetch` (hourly
  scheduler) + a "Fetch" button.
- **Reporting** (`app/Domain/Reporting/`, operator `/reporting` + client
  `/my-reports`) — ad-spend ingestion from Meta Marketing API + Google Ads
  REST API (with deterministic mock adapters), campaign rollups, KPI cards,
  inline-SVG trend charts, client reporting views, and scheduled report emails.
  Also a **creative performance overview** on `/reporting`: top ads +
  age/gender segments (Meta) and top keywords + ads (Google), stored in
  `ad_creative_reports` via the `CreativeMetricsSource` adapter family and
  fetched by the same daily pull / "Fetch data now" button.
- **Ad platform / API connections** (`/settings/ad-platforms`,
  `AdPlatformSetting`) — Meta token + ad account, Google Ads one-click OAuth.
  Secrets encrypted at rest; env vars remain a fallback.
- **Outbound mail / SMTP** (`/settings/mail`, `MailSetting`) — UI-configured
  SMTP that overrides `.env` MAIL_* at runtime (applied in `AppServiceProvider`
  for the web request and again on `Queue::before` so the long-lived worker
  picks up changes without a restart).
- **AI** (`app/Domain/Ai/`, opt-in via `ai.enabled`) — report-view summaries
  and pseudonymized lead qualification via OpenAI-compatible or Ollama
  providers, behind an operator approve-then-share workflow.
- **Ops** — encrypted DB backups (`/settings/backups`), demo data load/unload
  (`/settings/demo-data`), webhook endpoints (`/webhooks`), users (`/users`),
  EN/DE i18n, dark/light mode.

## Architecture rails

- **Modular monolith.** No microservices, no SPA, no live websockets in MVP.
- **Domain code lives under `app/Domain/`** — `Leads/` (intake, dedupe,
  normalization), `Reporting/` (ad metrics, rollups, report emails), `Ai/`
  (summaries, qualification). Importers/adapters live in `app/Importers/`.
  UI lives in `app/Livewire/` and `resources/views/`.
- **`app/Importers/` holds three adapter families.** Lead-intake adapters
  implement `App\Importers\Contracts\LeadSource` (CSV, IMAP, Google Sheets,
  Meta Lead Ads, …); ad-metrics adapters implement
  `App\Domain\Reporting\Contracts\AdMetricsSource` (Meta/Google live + mocks);
  creative-metrics adapters implement
  `App\Domain\Reporting\Contracts\CreativeMetricsSource` (per-ad / keyword /
  segment rows, registered in `AppServiceProvider::CREATIVE_METRICS_SOURCES`
  under the same source keys as the ad-metrics family). All are tiny
  fetch-and-map classes — persistence is not their job.
- **New lead sources are adapters**, not new tables. Add a class implementing
  `LeadSource`, register it in `AppServiceProvider::IMPORTERS`, and hand
  `IncomingLead` DTOs to `LeadIngestor`. New ad-metrics sources register in
  `AppServiceProvider::AD_METRICS_SOURCES` and yield `AdMetricsSnapshot` DTOs to
  `MetricsIngestor` (driven via `AdMetricsImporter`). That's the whole
  extension surface — recurring sources stay idempotent by setting a stable
  `external_id` so re-fetches skip rows already ingested.
- **Duplicate detection is centralized** in `DuplicateDetector`. Don't add
  ad-hoc duplicate queries elsewhere — extend the detector instead.
- **Visibility is enforced in `Lead::scopeVisibleTo()`.** Mutations also
  call `guardedLead()` in the Livewire components. Anywhere you query
  leads from a user context, go through `visibleTo()`.

## Compliance rails

- **`retention_until` is real.** Anything that writes leads must honor the
  configured default. Do not bypass `LeadIngestor`.
- **`lead_events` is the audit trail.** Use `AuditLogger`, not direct
  inserts.
- **No telemetry, no third-party calls** without a config switch and a
  README mention. lodgely is self-hosted-friendly first.

## Reserved seams (don't fill in prematurely)

- `app/Support/Tenancy/` — full multi-tenant scoping. `tenant_id` columns
  already exist everywhere and code resolves `Tenant::DEFAULT_ID`, but only the
  default tenant is wired. This is the one genuine "don't pull it forward yet"
  seam left.

> Historical note: `app/Domain/Reporting/` and `app/Domain/Ai/` used to be
> reserved seams. **They are now fully built** (see Feature surface above) — do
> not re-stub or treat them as empty. Extend them through their existing
> services/contracts.

## Style

- No CRM jargon (Deal, Pipeline, Stage, Quota, Forecast). Stick to
  Lead / Source / Status / Priority / Note.
- Server-rendered first. Reach for a Livewire component before reaching
  for JS state.
- Indexes on `tenant_id` + the filter column. Postgres handles the rest.
- Use enums (`LeadStatus`, `LeadPriority`, `UserRole`) — never raw strings
  in the domain layer.

## Hard-won gotchas

A short list of mistakes we made so the next maintainer doesn't repeat
them.

### Trait constants are accessed through the consuming class

PHP 8.2+ has trait constants, but a non-`final` trait constant **cannot**
be referenced through the trait name — `WithColumnPicker::AVAILABLE_COLUMNS`
throws a fatal `Cannot access constant …` at runtime, surfacing as a
500. Always go through the class that uses the trait:

```php
use App\Livewire\Inbox\InboxPage;

Rule::in(InboxPage::AVAILABLE_COLUMNS) // ✅
Rule::in(WithColumnPicker::AVAILABLE_COLUMNS) // ❌ fatal
```

### Inbox filter-card forms: native HTML, not Livewire dialogs

The five panels on the inbox filter card — **Sources, Saved views,
Custom columns, Filter options, Save current view** — all open as
inline expansion rows under the toolbar, with open state on the parent
`x-data` (one Alpine component for the whole card). All five use the
**same** `mt-3 pt-3 border-t border-slate-100 dark:border-slate-800`
rhythm. Don't reach for `<div x-data="{ open: false }">` per-panel
dropdowns or fixed bottom-sheets — we tried both and morph timing
inside this subtree killed the click bindings repeatedly.

The three panels that *write* state (Custom columns, Filter options,
Save current view) are plain `<form method="POST">` posting to
controllers in
`app/Http/Controllers/Inbox{ColumnPicker,FilterPicker,SavedFilter}Controller.php`
— **not** `wire:click` / `wire:model.live` actions. We rebuilt the
column picker four times with different Livewire approaches
(`wire:click` on chips → `@click="$wire.…"` → `wire:model.live` with
lifecycle hooks → checkbox-driven `has-[:checked]:` styling) and every
single variant silently dropped clicks for the user in production. The
form path always works.

The controller reopens the panel that just handled the submit via a
**one-shot `inbox.open-panel` session flash** (`'columns'` /
`'filters'` / `'saved-views'`), read once in the `@php` block at the
top of the filter card (`$openPanel = session('inbox.open-panel')`) —
**never a `?columns=1` / `?filters=1` / `?saved-views=1` query param.**
We shipped it as a query param first and it was wrong: nothing ever
clears that param (none of `columns`/`filters`/`saved-views` are
Livewire `#[Url]`-bound properties, so Livewire's own URL management
never touches them), so it sticks in the address bar forever and the
panel reopens on *every* future visit to that URL, not just the one
right after Apply — reported as "the columns picker is always open
now." If you add a sixth panel, reopen it the same way: flash
`inbox.open-panel`, redirect to a *plain* `/inbox` URL (or one built
from hidden-input filter state — see below), never a query flag the
panel itself checks.

Each write-panel's form also carries the current
search/status/priority/source/client/outreach/sort state as hidden
inputs, and the controller rebuilds the redirect query from those —
**every** write-controller needs this, not just the new ones: skip it
and applying that panel silently resets every active filter as a side
effect, since the redirect would otherwise land on a bare `/inbox`.
`InboxFilterPickerController` additionally drops the *value* of any
filter dropdown just unchecked in the same submit, so a hidden
dropdown can never stay invisibly active. The trait methods
(`togglePickedColumn`, `togglePickedFilter`, `saveFilter`, etc.) stay
around as public Livewire actions for tests and any future
programmatic caller, but the UI doesn't drive them.

All chip-level actions inside these panels are also native HTML — the
Saved-views chips are tiny `<form method="POST">` posts to
`InboxSavedFilterController@action` (one submit button per row for
`load` / `default` / `delete`), and the Sources chips are plain
`<a href="?source=…">` links that flip the URL param so Livewire's
`#[Url]` binding picks them up on the next request. We tried
`wire:click` on these too, with the same silent-drop result.

**Rule of thumb:** if you're adding anything *clickable* inside the
inbox filter card — toggles, chips, batch-edit forms — default to
native HTML form / link → Laravel controller → redirect back. Keep
Livewire for global toolbar widgets (search input, status / priority
/ source / sort selects, bulk-select row checkboxes) — those are
high above the morph boundary and still work fine.

### Don't rely on a single Tailwind utility class for critical spacing

The deployed CSS bundle is the output of a Tailwind JIT scan of the
source files *at build time*. If a class only just landed in the repo
and the production bundle hasn't been rebuilt since (or the user's
browser is serving a cached copy), the class won't exist in the CSS
shipped to the browser — the property silently has no effect.

We hit this with `gap-x-3` on the inbox toolbar: items rendered glued
together because the gap utility wasn't in the bundle yet.

For load-bearing spacing on a row that *has* to look right, layer the
spacing so the layout doesn't collapse if one utility is missing:

- Put `px-1.5 py-0.5` (or similar) on each item itself, so each item
  carries its own padding regardless of the parent gap.
- Use `gap-x-1` as an extra cushion rather than the only mechanism.
- For text-heavy toolbars, drop visible `·` or `|` separators
  between groups — they're load-bearing as long as the text wraps.

In short: padding on items is bulletproof; container gap is a nice-
to-have. Same idea applies to any utility class that's brand-new to
the project.

### wire:click inside modals can silently drop clicks too

The `wire:click` morph-drop problem isn't limited to the inbox filter
card. The "Generate" password button on the user edit modal
(`wire:click="generatePassword"`) silently failed — clicking produced
no visible result because the Livewire morph didn't update the input
value. Fixed by generating the password **client-side** with
`crypto.getRandomValues()` in an Alpine `x-on:click` handler, setting
the input value via `$refs`, and pushing the value to Livewire with
`$wire.set()`. The same pattern applies to any action button inside a
Livewire modal that needs to produce an immediate, visible DOM change:
prefer Alpine for the UI update, `$wire.set()` for the server sync.

### Livewire file uploads (`wire:model` on `<input type=file>`) hang too

The backups page (`/settings/backups`) restore flow used
`wire:model="restoreFile"` for the upload and `wire:submit` for the
destructive restore. In production the upload stuck on "Uploading…"
forever (the async `WithFileUploads` temp-upload round-trip never
settled) and the submit click was silently dropped — same morph-layer
failure as everything else above, just wearing a file-upload hat.

Fix follows the established rail: a plain multipart
`<form method="POST" enctype="multipart/form-data">` posting to
`App\Http\Controllers\BackupController` (create / delete / restore),
which redirects back with a one-shot flash. The Livewire component
still renders the page and its action methods stay around for tests,
but the UI no longer drives them. **Don't reintroduce `wire:model` for
file inputs here** — native multipart upload is the only thing that
reliably works.

## Every commit checklist

Before committing any change, always update these three files:

1. **`CHANGELOG.md`** — add an entry under `[Unreleased]` describing what
   changed (Added / Changed / Fixed / Removed). Use plain English, one bullet
   per logical change.
2. **`README.md`** — if the change adds, removes or alters a user-visible
   feature, update the relevant section (Features, Out of scope, Config
   reference, Roadmap, Architecture tree) so the README stays accurate.
3. **`composer.json` `"version"`** — bump using semver:
   - Patch (`0.x.y → 0.x.y+1`) for bug fixes and small internal changes.
   - Minor (`0.x.0 → 0.x+1.0`) for new features or behaviour changes.
   - Major reserved for breaking changes to the data schema or public API.

   The **footer version badge** (`v0.x.y` in the app footer) reads from
   `config('lodgely.version')` which pulls directly from `composer.json`
   at boot. The **README badge** (`shields.io` version badge at the top)
   is a static string — update it in the same commit so both stay in sync.

When a batch of [Unreleased] entries represents a coherent release (e.g. a
milestone or tag), promote `[Unreleased]` to `[x.y.z] · YYYY-MM-DD` in the
changelog.

## Runtime environment

The production install runs in **Docker**. Always prefix artisan commands with
`docker compose exec app` — never bare `php artisan`:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan lodgely:user:create --role=client
```

## Commands worth knowing

```bash
docker compose exec app php artisan migrate --seed                     # bootstrap a demo install
docker compose exec app php artisan lodgely:user:create --role=client  # add a scoped client
docker compose exec app php artisan lodgely:import:email-mock --count=5
docker compose exec app php artisan lodgely:import:meta-mock --count=6 # Meta Lead Ads demo data
docker compose exec app php artisan lodgely:leads:purge --dry-run      # GDPR cleanup, preview only

# Recurring source / reporting pulls (also wired into the scheduler in routes/console.php)
docker compose exec app php artisan lodgely:google-sheets:fetch        # pull due Google Sheet sources
docker compose exec app php artisan lodgely:meta-leads:fetch           # pull due Meta Lead Ads connections
docker compose exec app php artisan lodgely:openflow:fetch             # pull due OpenFlow sources
docker compose exec app php artisan lodgely:import:ad-metrics --days=7 # backfill ad spend/metrics
```

> The scheduler (`php artisan schedule:work` / cron) drives the recurring jobs —
> Google Sheets + Meta Lead Ads + OpenFlow fetches (hourly, each source decides
> if it's due), the daily 05:00 ad-metrics pull, report emails, and the GDPR purge.
> Without it, nothing recurring runs. Reporting also has a **"Fetch data now"**
> button so operators don't have to wait for the 05:00 run.
