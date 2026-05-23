# Working notes for Claude / future maintainers

This file is intentionally short. It's meant to keep future contributors —
human or AI — from wandering off the architectural rails set in the MVP.

## Product north star

lodgely is a **lead intake hub**, not a CRM. If a feature request feels like
it belongs in HubSpot, Pipedrive or Salesforce, it almost certainly does
*not* belong in lodgely. Push back, or land it in a downstream tool.

The two real personas:

- **Operator** — agency person or inhouse marketer who needs the leads sorted.
- **Client** — small business owner who just wants to *see* their leads
  in a clean read-only-ish view.

## Architecture rails

- **Modular monolith.** No microservices, no SPA, no live websockets in MVP.
- **Domain code lives in `app/Domain/Leads/`.** Importers live in
  `app/Importers/`. UI lives in `app/Livewire/` and `resources/views/`.
- **New sources are adapters**, not new tables. Add a class implementing
  `App\Importers\Contracts\LeadSource`, register it in
  `AppServiceProvider::IMPORTERS`, and hand `IncomingLead` DTOs to
  `LeadIngestor`. That's the whole extension surface.
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

- `app/Domain/Reporting/` — Meta Ads / Google Ads ingestion, campaign rollups.
- `app/Domain/Ai/` — summaries / quality scoring on reporting data.
- `app/Support/Tenancy/` — full multi-tenant scoping. `tenant_id` columns
  already exist but only the default tenant is wired.

These exist as empty folders on purpose. They mark the architectural
direction without pulling the work forward.

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

The four panels on the inbox filter card — **Sources, Saved views,
Custom columns, Save current view** — all open as inline expansion rows
under the toolbar, with open state on the parent `x-data` (one Alpine
component for the whole card). All four use the **same** `mt-3 pt-3
border-t border-slate-100 dark:border-slate-800` rhythm. Don't reach
for `<div x-data="{ open: false }">` per-panel dropdowns or fixed
bottom-sheets — we tried both and morph timing inside this subtree
killed the click bindings repeatedly.

Both panels that *write* state (Custom columns, Save current view) are
plain `<form method="POST">` posting to controllers in
`app/Http/Controllers/Inbox{ColumnPicker,SavedFilter}Controller.php` —
**not** `wire:click` / `wire:model.live` actions. We rebuilt the
column picker four times with different Livewire approaches
(`wire:click` on chips → `@click="$wire.…"` → `wire:model.live` with
lifecycle hooks → checkbox-driven `has-[:checked]:` styling) and every
single variant silently dropped clicks for the user in production. The
form path always works.

The controllers redirect to `/inbox?columns=1` or `/inbox?save=1` with
the user's current filter URL params preserved, so the panel re-opens
on reload with the new state visible. The trait methods
(`togglePickedColumn`, `saveFilter`, etc.) stay around as public
Livewire actions for tests and any future programmatic caller, but the
UI doesn't drive them.

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
```
