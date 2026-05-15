# Changelog

All notable changes to lodgely are documented here. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
semantic-ish versioning once a 1.0 is tagged.

## [Unreleased]

### Added

- **Per-user preference persistence** — language and theme are now stored in the `users` table (`locale`, `ui_theme` columns). For authenticated users: the `SetLocale` middleware reads `users.locale` (falling back to session for guests); clicking a language option saves to DB via `POST /locale`; toggling Light/Dark fires a background `fetch` to `POST /user/theme` and saves to `users.ui_theme`. On the next page load the server injects the stored theme into the FOUC-prevention script, so the correct mode is applied before any CSS loads without relying solely on `localStorage`.

- **i18n / localization infrastructure**: JSON-based translations via Laravel's `__()` helper. All user-visible strings in every Blade view are now wrapped in `__()`. Language files ship for `en` (English) and `de` (German). A `SetLocale` middleware reads the locale from session on every request. A `POST /locale` route lets the language switcher in the topbar persist the choice server-side.
- **Language switcher** in the topbar: pill-shaped EN / DE toggle (same visual style as the dark mode switch). Works on both authenticated and guest pages.
- **Dark / Light mode pill switch**: replaced the single icon button with a labelled two-option pill (`Light · Dark`) that clearly shows the active mode and makes both choices a single click.

- **Dark mode** with OS-preference detection and manual toggle: a sun/moon button in the topbar persists the choice to `localStorage`. All pages, modals, side panels, tables, and form controls fully support `dark:` variants. The `@custom-variant dark` directive in Tailwind CSS v4 enables class-based toggling via `.dark` on `<html>`.
- **Modernized UI**: cards and panels now use `rounded-xl` / `rounded-2xl` and carry a subtle `shadow-sm`; the topbar is sticky with a `backdrop-blur` glass effect; the brand logo uses a gradient; KPI cards have a colored top-accent bar; buttons use `transition-colors` for smooth hover feedback; focus rings use the brand color accent.

### Changed

- Brand logo icon updated from flat `bg-slate-900` to `bg-gradient-to-br from-brand-500 to-brand-900` gradient throughout (topbar and login page).
- Login card now uses `rounded-2xl` with a depth shadow; sign-in button uses brand color in dark mode.
- All primary action buttons and filter inputs now focus with `brand-500` ring instead of plain slate.

### Added

- **Saved filters and per-user view defaults** (roadmap item 1):
  - Users can save any combination of search, status, priority, source, client, and sort as a named filter via a "Save view" button in the filter bar.
  - Saved filters appear as chips below the filter controls; clicking a chip instantly applies that filter set.
  - Each saved filter has a star (★) toggle to mark it as the user's default view.  The default is loaded automatically when the user visits `/inbox` with no explicit URL filter parameters.
  - Toggling the star on an already-default filter clears the default; setting a new default clears any previous one (one default per user at most).
  - Each chip also has a × delete button. All saved-filter operations are scoped to the authenticated user.
  - New `saved_filters` table (`user_id`, `tenant_id`, `name`, `filters` JSONB, `is_default`).
  - New `SavedFilter` model with a `HasMany` relationship wired from `User`.

- **Bulk actions** in the inbox (roadmap item 4):
  - Operators see a checkbox on each row and a "select all on page" header checkbox.
  - A bulk action bar appears above the table when one or more leads are selected, showing a count and two dropdowns — set status, set priority — each with an Apply button.
  - Bulk mutations respect `visibleTo()` visibility scoping and record a
    `lead.status_changed` / `lead.priority_changed` audit event per affected lead.
  - Selecting a different filter, sort, or page automatically clears the selection.
- **Dynamic source filter** — the Source dropdown in the inbox filter bar now
  queries distinct sources actually present in the visible lead set instead of
  using a hardcoded list, so `webhook` and `email_imap` sources appear when relevant.

### Changed

- All modal and panel overlay surfaces (`lead-panel`, `new-lead` modal, `users`
  modal, `webhooks` modal) now carry `role="dialog"`, `aria-modal="true"`, and
  `aria-labelledby` pointing at the panel title, improving screen-reader
  compatibility.
- Close buttons (✕) in all dialogs now have `aria-label="Close"`.
- The toast notification container in the app layout carries `role="status"`,
  `aria-live="polite"`, and `aria-atomic="true"` so screen readers announce
  confirmations (e.g. "Lead added.", "3 leads updated.").
- Submit buttons on the users, webhooks, and lead-panel note forms now dim and
  become disabled during the Livewire network request (`wire:loading`), preventing
  double-submit.

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
