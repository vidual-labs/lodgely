# Changelog

All notable changes to lodgely are documented here. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
semantic-ish versioning once a 1.0 is tagged.

## [Unreleased]

### Added

- **Meta Lead Ads fields on `leads` table** — ten new nullable columns cover the structural fields present on every Meta lead: `meta_lead_id` (Meta's own ID, for idempotent webhook ingestion), `ad_id` / `ad_name`, `adset_id` / `adset_name`, `campaign_id`, `form_id` / `form_name`, `platform` (`facebook` | `instagram`), and `is_organic`. Indexes added on `(tenant_id, meta_lead_id)`, `(tenant_id, ad_id)`, and `(tenant_id, form_id)`. Dynamic per-form custom question answers continue to flow through `raw_payload`.
- `IncomingLead` DTO extended with the same ten optional Meta fields so the future Meta Lead Ads importer adapter can pass them through to the ingestor without any further changes.

### Fixed

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
