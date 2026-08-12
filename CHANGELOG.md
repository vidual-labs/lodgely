# Changelog

All notable changes to lodgely are documented here. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
semantic-ish versioning once a 1.0 is tagged.

## [Unreleased]

### Added

- **Click-to-call and click-to-email links** on the phone/email shown in the
  lead side panel's Contact section — phone numbers are now `tel:` links and
  email addresses `mailto:` links, so reaching out no longer requires
  copying the number/address elsewhere first. Deliberately scoped to the
  side panel only, not the inbox table, to keep the dense table row's click
  target unambiguous (row click opens the panel; it doesn't compete with a
  dial/mail-app launch). Clicking one highlights (pulses) the matching
  "Called"/"Mailed" outreach pill for a few seconds as a nudge to confirm
  the outcome yourself once you know it — deliberately **not** an automatic
  status change, since the browser has no way to know whether a `tel:`
  click actually connected a call or a `mailto:` click resulted in a sent
  email, only that the dialer/mail app was opened.

### Security

- **Updated `laravel/framework`, `symfony/http-kernel`, `symfony/http-foundation`,
  `symfony/mailer`, `symfony/mime` and `guzzlehttp/guzzle` to patched
  versions** in response to automated dependency-vulnerability scans
  (CVE-2026-45075, CVE-2026-48736, CVE-2026-45068, CVE-2026-45067,
  CVE-2026-69246, GHSA-5vg9-5847-vvmq). None of these were direct
  `composer.json` requirements — they came in transitively via
  `laravel/framework` — so the existing version constraints already allowed
  the fixes; only `composer.lock` changed. Full test suite passes on the
  updated versions.

### Fixed

- **German "Called"/"Mailed" outreach labels no longer match the inbox
  overview's badge letters.** The inbox lead-list badges for outreach state
  are hardcoded single letters ("Q"/"C"/"M", not translated), but the German
  translations for "Called" and "Mailed" ("Angerufen"/"Angeschrieben") no
  longer start with those letters, so a German user couldn't visually
  connect a badge to its label/tooltip. Both now deliberately stay in
  English ("Called"/"Mailed") in `lang/de.json` so "Called" → "C" and
  "Mailed" → "M" still line up; "Qualified" already matched its "Q" badge in
  both languages and is unaffected.

- **Deleted leads from recurring sources (OpenFlow, Google Sheets, Meta Lead
  Ads) reappeared on the next scheduled fetch.** Idempotency on re-pulls is
  keyed on `external_id`, but the lookup excluded soft-deleted leads —
  intended to only dedupe against currently-visible leads, it also meant a
  lead an operator had deleted was invisible to that check. Every recurring
  source re-walks a window of recent submissions on each run (OpenFlow's is
  60 minutes past its last successful fetch), so within that window the
  "already ingested" check silently missed the deleted row and
  `LeadIngestor` recreated it as a brand-new lead — reported as OpenFlow test
  leads "constantly loading in again" after being bulk-deleted. The
  idempotency lookup (`DuplicateDetector::findByExternalId`) now matches
  soft-deleted leads too, so a deleted lead is recognized as already-ingested
  and skipped rather than resurrected.

- **Inbox filter-card panels (Custom columns, Filter options, Saved views)
  stayed open forever once opened, on every future visit to `/inbox`.**
  Reopening a panel after its "Apply"/"Save" form redirect was implemented
  with a sticky `?columns=1` / `?filters=1` / `?saved-views=1` query
  parameter. None of those are Livewire `#[Url]`-bound properties, so nothing
  ever cleared them — once a user submitted the Columns picker once, that
  parameter stayed in the URL bar (and any bookmarked/shared link) and the
  panel rendered open on every subsequent load, reported as "column select
  now seems open all the time." Replaced with a one-shot
  `inbox.open-panel` session flash: the redirect after Apply/Reset/Save sets
  it, the very next page render reads and consumes it, and every visit after
  that starts with every panel collapsed by default. The Columns picker's
  form also now carries the current search/status/priority/source/client/
  outreach/sort state as hidden inputs on redirect, the same way the Filter
  options form already did, so applying a column change no longer silently
  drops the active filters.

### Added

- **Per-user filter-dropdown picker for the inbox toolbar, plus a new
  Outreach filter.** A "Filter options" toggle in the filter bar lets each
  user — client or operator — choose which of Status / Priority / Source /
  Outreach show up as toolbar dropdowns, the same "Custom columns" pattern
  already used for table columns. Prompted by a client who never filters by
  priority but does filter by outreach status. The new Outreach filter
  (Not contacted / Qualified / Called / Mailed) matches the qualified/called/
  mailed pills already shown on every lead. Persists to `users.inbox_filters`;
  defaults to Status/Priority/Source (today's fixed set), so nobody's inbox
  rearranges itself on upgrade. Unchecking a dropdown also clears any value it
  had applied, so a list can never stay invisibly filtered by a dropdown
  that's no longer shown. Saved views now capture the outreach filter too.

- **Clients can now edit status/priority, bulk-edit, add notes, and export
  CSV — all scoped to their own leads.** Previously a client's only
  self-service action was the outreach toggles (qualified/called/mailed);
  everything else routed through an operator. Clients can now:
  - Change a lead's status and priority from the lead panel, and bulk-apply
    both across a multi-select, the same way operators already could. Bulk
    *delete* stays operator-only — it's destructive, and "editing" doesn't
    extend to permanently removing data.
  - Add notes to a lead (previously view-only for clients).
  - Download their filtered inbox as CSV (`/inbox/export?format=csv`) —
    Excel opens it directly. The raw NDJSON export stays operator-only.

  Every action is scoped through the same `Lead::scopeVisibleTo()` a client's
  inbox already uses, so this is additive self-service, not a visibility
  change — a client still can never see or touch a lead outside their
  assigned `client_name` scopes.

  The lead panel also reorders the Outreach and Workflow (status/priority)
  sections to sit directly under Contact, ahead of the read-only
  attribution/message sections, so the actions a client can take are the
  first thing they see on opening a lead rather than easy to miss further
  down the panel.

- **Per-connector brand filtering for shared Meta/Google Ads accounts.** A
  client connector can now be scoped to one brand within an ad account that
  actually serves several — e.g. two businesses sharing one Google Ads or
  Meta account. Google connectors match by a Business Name asset's id
  (Performance Max / Demand Gen); Meta connectors match by the Facebook
  Page id each ad publishes as. Matching is always by the platform's
  permanent id, never the customer-facing name, since Page/asset text can
  be edited later by whoever manages the account. The connector edit page
  has a "Resolve & save" action per platform: typing an id and resolving it
  shows the current name back before saving, so an operator can confirm
  they've got the right one. Also added an optional, purely cosmetic
  "internal label" per connector (never sent to Meta/Google) so the
  connectors list stays easy to tell apart. Meta's page filter fetches at
  ad level and aggregates back up to campaign_id, since Meta only exposes
  the publishing Page per ad, not per campaign.

- **Multiple Meta/Google Ads connectors, assignable per client.** Previously
  an install could only configure one Meta account and one Google Ads
  account, shared by the whole tenant. `/settings/ad-platforms` now has a
  "Client connectors" section: an operator can add a dedicated connector
  (its own ad account, access token / OAuth) and assign it to a specific
  client by name. Ad spend and creative rows fetched from that connector are
  tagged with the client and shown to them alone on `/my-reports` and
  scheduled report emails, instead of the shared default connector's
  tenant-wide data. Managed via a native form → controller flow (like the
  backup page), not Livewire, per the established pattern for dynamic
  add/remove lists. The daily scheduled pull and "Fetch data now" button
  automatically loop over every enabled connector per platform.

### Changed

- **Report-email intros are markdown only — raw HTML in them is no longer
  rendered.** The intro is composed into the client's email body unescaped, so
  it is now rendered with raw-HTML input stripped and unsafe link schemes
  dropped by the markdown parser itself, instead of being post-processed with a
  regex. Operators who pasted raw HTML into an intro will see it disappear
  rather than render; every tag that previously survived has a markdown
  equivalent.

- **Internal: recurring lead-source fetching shares one implementation.** The
  three `lodgely:*:fetch` commands (Google Sheets, Meta Lead Ads, OpenFlow)
  were structural copies of each other differing only in model, Import key and
  a noun; they now share a `FetchesRecurringLeadSources` concern. The
  byte-identical `isDue()` / `forTenant()` on the three source models likewise
  moved to a `HasRecurringFetchSchedule` concern. This is what let the clock
  handling drift apart between them in the first place.

- **Internal: single home for the reporting client-scoping rules.** The
  identical `forClients()` scope on `AdSpendReport` and `AdCreativeReport` now
  comes from one shared concern; the campaign-attribution lookup that maps a
  client to their leads' campaign ids lives on `Lead` instead of being
  reimplemented in `CampaignRollup` and `ClientViewDataBuilder`; and the
  case-insensitive `client_name` comparison repeated across reporting and the
  inbox filter is now a `Lead` scope. Also deduplicated adapter resolution in
  `AdMetricsImporter` (which additionally asked the database for the active
  source keys three times per run instead of once) and the Meta lead-action
  counting shared by the campaign and creative adapters. No behaviour change.

### Fixed

- **German translations added for the Outreach section and 86 other
  client-facing strings that were silently falling back to English.**
  `lang/en.json` and `lang/de.json` are maintained as an exact matched pair —
  every user-facing string needs an entry in both — but "Outreach",
  "Qualified", "Called", "Mailed" and its helper caption had never been added
  to either file, so a German user saw raw English text with no visual cue
  it was untranslated (reported as "did not find it"). Audited every
  `__()` call reachable from the pages a client can actually open (inbox,
  lead panel, My reports, profile, nav) and found 82 more with the same gap
  — mostly report-view/saved-filter/column-picker strings introduced by
  earlier features — all added to both files now. A much larger set (~620)
  remains missing across operator-only settings/import pages; translating
  those accurately needs native-speaker review and is out of scope here, but
  is worth a dedicated pass.

- **Inbox sorts now have a tiebreaker, so old leads stop floating to the top.**
  Every sort applied a single ORDER BY column, and all of them except
  "Received" are low-cardinality — priority has three distinct values, status
  five. The order *within* a band was therefore left to the database, which in
  Postgres means heap order: oldest first. Sorting by priority surfaced the
  oldest leads in each band rather than the newest, and pagination was
  unstable, since ties could be ordered differently between the query for page
  one and the query for page two — the same lead could appear on both pages,
  or on neither. Sorts now break ties on `created_at` (newest first), then on
  `id`, which `created_at` alone cannot do because a CSV or API import writes
  many rows in the same second. "Select all on page" orders identically to the
  table, so it can no longer select a different set of rows than the operator
  is looking at.

- **Report-email intros could smuggle scripts into the client's inbox.** The
  intro is authored by an operator and rendered unescaped into the email body
  and the in-app preview. The sanitizer that was meant to restrict link
  schemes only matched *quoted* `href` values, so `<a href=javascript:…>`
  passed through untouched, and for a link whose scheme did pass it re-emitted
  the original tag verbatim — carrying any `onclick=` with it. Both survived
  `strip_tags()`, which filters tags but never attributes. Rendering now goes
  through the markdown parser's own `html_input`/`allow_unsafe_links`
  handling, with the tag whitelist kept as defence in depth.

- **A failed OpenFlow fetch silently dropped a window of leads.** OpenFlow's
  `last_fetched_at` was doing two jobs: throttling the hourly scheduler, and
  marking how far back the next pull should walk. The scheduler advances it on
  every attempt — including failures, deliberately, so a broken source isn't
  retried hourly — which moved the data cutoff past submissions nothing had
  ingested. The next run then treated them as already-seen and skipped them
  for good: on a source refreshing daily, one transient failure lost a day of
  leads. The high-water mark now lives on its own `last_successful_fetch_at`
  column that only a completed pull advances (backfilled on migrate, so
  existing installs don't re-walk their backlog).

- **GDPR purge no longer skips most of the leads it reports deleting.**
  `lodgely:leads:purge` walked expired leads with offset-based chunking while
  soft-deleting them, so every delete shifted the remaining rows up a page and
  the command silently jumped over roughly every second chunk. On a backlog of
  1200 expired leads it deleted 1000 and left 200 on disk past their retention
  date, while still reporting all 1200 as purged. It now pages by primary key,
  and the reported count is the number actually deleted.

- **Sorting the inbox by priority put High last.** "Priority" sorted on the
  stored enum string, which is alphabetical — high, low, medium — so
  descending order returned Medium, Low, High. Both directions now order by
  the priority's semantic weight, matching `LeadPriority::weight()`.

- **OpenFlow forms with numeric field ids no longer duplicate every mapped
  answer.** PHP converts numeric-string array keys to ints, which defeated the
  strict "already consumed" check, so a field mapped to Email or Name was
  written to its lead column *and* repeated as a custom answer on every
  imported lead. Only genuinely unmapped fields become custom answers now.

- **A half-configured client connector no longer double-counts ad spend.** A
  per-client Meta/Google connector inherited the ad account id / customer id
  from `.env`, so enabling one before filling in its own account fetched the
  default account a second time — once untagged, once tagged with the client —
  and every campaign in it was counted twice in tenant-wide rollups. Account
  identity is now the default connector's env fallback only; shared
  credentials (tokens, OAuth app, MCC login customer id) still fall back as
  before.

- **Deactivating a user now terminates their existing sessions.** `is_active`
  was only checked at login, so a deactivated user (or their remember-me
  cookie) kept full access until they happened to log out. A new
  `EnsureUserIsActive` middleware signs deactivated accounts out on their
  next request and returns them to the login screen with an explanatory
  message.

- **Changing a password now signs out every other session for that account.**
  Laravel's `AuthenticateSession` middleware is enabled for the web stack, so
  a password change — self-service on `/profile`, an operator edit on
  `/users`, or a reset link — invalidates all sessions still carrying the old
  password hash (the session performing the change stays signed in). A
  hijacked or forgotten session no longer survives a password rotation.

- **Inbox and user search treat `%`, `_` and `\` literally.** Search terms
  were interpolated into SQL `LIKE` patterns unescaped, so searching for
  `100%` matched anything containing `100` and `_` matched any character.
  Terms are now escaped (with an explicit `ESCAPE` clause so Postgres and
  SQLite agree) and match what the user actually typed.

- **IMAP importer no longer loses the message body on image-first
  multipart emails.** A nested subtree with no text (e.g. a
  `multipart/related` part holding only images) returned an empty result
  and short-circuited the search before a `text/plain` sibling later in the
  message was examined.

- **Deleting a backup with a malformed filename shows an error flash instead
  of a 500 page.**

- **Client-facing `/my-reports` no longer shows every client the same ad
  spend.** `ClientViewDataBuilder` queried `ad_spend_reports` scoped only to
  the tenant, so every client assigned to a reporting view saw identical ad
  metrics regardless of whose campaigns they actually owned. Ad spend is now
  scoped to the viewer: directly, for spend tagged with their client_name via
  a dedicated connector, and via the existing campaign-attribution heuristic
  for the shared default connector's data — mirroring how the operator
  `/reporting` page's client filter has always worked.

- **Creative performance overview on the reporting dashboard.** `/reporting`
  now shows a "Creative performance" section with up to four lean top-5
  tables: top ads and top age/gender segments from Meta, top keywords and top
  ads from Google Ads — each ranked by spend with impressions, clicks, spend,
  platform leads and CPL, and honoring the existing platform / client / date
  filters. Creative rows land in a new `ad_creative_reports` table via
  `CreativeMetricsSource` adapters (live Meta Marketing API + Google Ads GAQL,
  plus deterministic demo mocks) that run inside the same daily scheduled pull
  and "Fetch data now" button as the campaign metrics, keyed by the same
  Settings → Ad platforms toggles. Aggregate metrics only — no PII; the demo
  mock rows are purged the moment the matching live platform is connected, and
  "Clear ad-metrics data" wipes creative rows too.

### Security

- **CSV/NDJSON lead export now neutralizes formula injection.** Lead fields
  (name, message, client/campaign names, …) are attacker-controlled — they
  arrive via webhook, CSV import or email intake — and `/inbox/export` wrote
  them straight into export cells. A value like `=cmd|' /C calc'!A1` would
  execute as a formula when an operator opened the export in Excel/LibreOffice.
  Cells starting with `=`, `+`, `-`, `@`, tab or CR are now prefixed with a
  leading `'` before being written.
- **Report-email intro links restricted to http(s)/mailto.** The Markdown
  intro on scheduled client report emails allowed an `<a href="...">` of any
  scheme to survive into the sent HTML (e.g. `javascript:`), since the
  existing tag whitelist didn't validate attribute values. Links with any
  other scheme are now stripped down to plain `<a>` text before sending.
- **New-user passwords now require 12 characters minimum** (`/users`), up
  from 8, matching the self-service password-reset minimum already enforced
  elsewhere.
- **Added a `Strict-Transport-Security` header** on HTTPS responses (existing
  `SecurityHeaders` middleware), so browsers still get HSTS even when the
  reverse proxy in front of lodgely doesn't set it itself.

### Fixed

- **OpenFlow external_id scoped per source, for safe multi-form imports.**
  You could already add multiple OpenFlow sources for one client
  (`/imports/openflow` → "Add OpenFlow source") — the connector was built
  list/CRUD from the start, and the hourly `lodgely:openflow:fetch` scheduler
  already pulls every active source. What wasn't safe: OpenFlow submission ids
  are only unique within a single form's own sequence (often small integers),
  so two sources — a second form on the same install, or the same form_id on a
  second install — could produce colliding raw ids and have the second
  source's leads silently dropped as "already imported" duplicates of the
  first. `external_id` is now scoped to the source's install + form
  (`OpenflowLeadSource::scopedExternalId()`), so dedup is correctly
  partitioned per source. A new `lodgely:openflow:rescope-ids` command
  backfills existing OpenFlow leads to the new scoped format (idempotent,
  supports `--dry-run`) — run it once after upgrading if you already have
  OpenFlow leads imported, to avoid a burst of duplicates on the next
  scheduled fetch.

### Added

- **Per-client filter on the operator reporting page.** `/reporting` now carries a
  pill toggle row — `(All clients) (Client A) (Client B) …` — next to the Platform
  and Range filters, letting an operator with several clients view one client's
  numbers in isolation. Lead figures (Lodgely Leads KPI, leads-by-source, the lead
  trend line) scope by `client_name`; ad-spend figures (spend, clicks, impressions,
  platform leads, charts and the "By campaign" table) scope to the campaigns that
  the selected client's leads carry, so spend and leads stay consistent. The
  selection is URL-bound (`?client=…`) so it survives reload and is shareable. Shown
  to operators only; the client `/my-reports` view is unchanged. Note: a campaign
  with spend but no leads yet can't be attributed to a client, so its spend appears
  only under "All clients".

- **OpenFlow API-token authentication.** An OpenFlow source can now authenticate
  with a **read-only API token** (created in OpenFlow under Settings → API
  Tokens) instead of storing a login password — the recommended path. The token
  is encrypted at rest and sent as a Bearer token, so lodgely never holds an
  OpenFlow password and the token can be revoked independently. Email/password
  login remains as a fallback, and existing password-based sources keep working
  unchanged. The connect form now offers an "API token (recommended)" field with
  the login as a secondary option.

- **OpenFlow recurring lead source.** A new connector at `/imports/openflow`
  pulls submissions from a self-hosted [OpenFlow](https://github.com/vidual-labs/openflow)
  form straight into a lodgely client. Each source stores the OpenFlow base URL,
  a login email and an encrypted password (OpenFlow exposes no API token, so the
  connector signs in to mint a JWT), the form to pull, and an operator-defined
  field mapping (OpenFlow field → lead field; unmapped fields are kept as
  custom answers). Leads are assigned to a specific client via the source's
  **Client** name, so a client only ever sees their own. Pulls are idempotent on
  the OpenFlow submission id and run hourly via the scheduler (each source
  decides if it is due), with a **Fetch** button for an immediate run. New
  artisan command `lodgely:openflow:fetch`. The adapter follows the existing
  `LeadSource` rail — no new tables in the lead path, everything flows through
  `LeadIngestor`.

### Fixed

- **An unrecognized Status/Priority value from a source no longer breaks the
  whole import.** If a mapped sheet/CSV column fed a value that isn't one of
  lodgely's statuses (`new/reviewed/incomplete/duplicate/forwarded`) or
  priorities (`low/medium/high`) — e.g. a "Status" column holding `CREATED` —
  the enum cast threw `"CREATED" is not a valid backing value for enum
  LeadStatus` on save and the entire fetch failed. `LeadIngestor` now coerces
  incoming status/priority to a known value case-insensitively and falls back to
  the default (New / Medium) for anything else; the original value is still kept
  in `raw_payload`. This is the single chokepoint all importers use, so CSV,
  Meta, Manual and Google Sheets are all protected.
- **Google Sheets "Fetch" button now actually fetches.** The per-source
  **Fetch** button on `/imports/google-sheets` was a Livewire `wire:click`,
  which silently dropped clicks in production (the same morph-drop gotcha that
  already forced the delete buttons onto native forms) — so clicking "Fetch"
  did nothing: no import, no error, no toast, which read as "Google Sheets
  import is completely broken." It is now a plain `<form method="POST">` posting
  to a controller that always runs the import (no `isDue` gate, so it also
  recovers a source the hourly scheduler is holding off after an earlier
  failure) and redirects back with a clear result. The result message now spells
  out the silent-success cases too: *the sheet returned no rows* (check the
  range / that the connected account can read the spreadsheet), *rows had no
  name/email/phone* (check the column mapping), or *all rows were already
  imported*, instead of an unhelpful "0 imported".
- **Failed recurring imports are no longer silent.** A scheduled Google Sheets
  or Meta Lead Ads fetch that threw (for example an expired Google OAuth refresh
  token — these expire after 7 days while the OAuth app is in "Testing" mode)
  was swallowed: the import row was left a misleading `0 / 0 / 0 / 0`, no reason
  was recorded, and `last_fetched_at` was never advanced — so the scheduler
  re-ran the broken source on *every* hourly tick, piling up identical empty
  imports while the inbox quietly stopped receiving new leads. The failure
  reason is now stored on the import (new `imports.error` column) and shown as a
  red **Failed — <reason>** row under "Recent imports" on both import pages, and
  the source's clock advances on every attempt so a broken source respects its
  refresh interval instead of hammering hourly. Use the **Fetch** button to
  retry immediately once the cause is fixed.
- **Inbox now labels Google Sheets and Meta Lead Ads sources.** The source
  filter dropdown showed the raw `google_sheets` / `meta_leads` keys for leads
  from those importers; they now render as "Google Sheets" and "Meta Lead Ads".
- **Stopped fake demo leads appearing every day at 06:00.** The scheduled mock
  email pull (which generates synthetic leads like `alex.bennett@sample.org`)
  ran unconditionally on every install. It is now opt-in via the new
  `LODGELY_EMAIL_MOCK_SCHEDULE` flag (default `false`), matching how the IMAP
  pull is already gated. The manual `lodgely:import:email-mock` command and the
  in-app "pull now" button are unaffected.
- **Fixed a 500 on the operator reporting page whenever ad-spend data
  existed.** The trend-chart loop referenced the `$kpis` array inside a
  closure without capturing it (`use (...)`), so once any spend row was
  present the page threw `Undefined variable $kpis`. The currency is now
  captured into the closure. Added a regression test that renders the live
  reporting component with data.
- **Reporting now displays the ad account's currency instead of always "$".**
  Spend, cost-per-lead, CPC and CPM on the operator reporting page, the client
  "My reports" view and the report emails read the currency stored on each
  ad-spend row (e.g. a Meta account billed in EUR now renders as `€1,234.56`),
  falling back to the configured Meta currency when there's no data yet. Unknown
  currency codes render as the ISO code (e.g. `AED 1,234.00`) rather than a bare
  number.
- **Connecting a live ad platform now purges leftover demo mock rows.** Earlier
  the demo `meta_mock` / `google_mock` rows were only suppressed from *future*
  imports — any already-imported demo campaigns lingered in reporting next to the
  real data (correct campaign name, fabricated numbers). The next import after a
  platform goes live now deletes the stale `mock`-tagged rows for that platform
  automatically.
- **"Fetch data now" backfills 30 days instead of 7.** The on-demand fetch only
  pulled the last week, so the reporting page's 30-day and 90-day ranges stayed
  near-empty after connecting a platform. It now backfills 30 days by default
  (configurable via `LODGELY_AD_METRICS_BACKFILL_DAYS`).
- **Reporting no longer mixes demo mock data into live reports.** The demo
  `meta_mock` / `google_mock` ad-metrics adapters were running *additively*
  alongside the real ones, so once an operator connected Meta their reporting
  showed three fabricated "Lodgely – …" Meta campaigns plus two fabricated
  "Search – …" Google campaigns next to the genuine data — even though Google was
  never connected. The mock adapters are now suppressed the moment any real ad
  platform is connected through Settings → Ad platforms, so reporting reflects
  live data only. Fresh / demo installs that haven't connected anything keep the
  mocks. (Existing fabricated rows can be removed with "Clear ad-metrics data",
  then re-pulled with "Fetch data now".)

### Changed

- **README compressed; detail externalized into `docs/`.** The README had
  grown to 600+ lines as features piled up. The full Features write-up,
  Architecture tree + AI/Meta field detail, the complete env-var
  Configuration reference, Privacy & GDPR notes, and the Roadmap's
  "Completed" history now live in `docs/FEATURES.md`,
  `docs/ARCHITECTURE.md`, `docs/CONFIGURATION.md`, `docs/PRIVACY.md` and
  `docs/ROADMAP.md` respectively. The README keeps the condensed version of
  each section plus a link to the corresponding doc, so it stays a fast
  orientation read instead of the full reference.

### Added

- **Meta Lead Ads import over the API (no Google Sheets in between).** Once Meta
  is connected under Settings → Ad platforms, a new **Imports → Meta Lead Ads
  (API)** page lets operators pull leads straight from the Meta Lead Ads Graph
  API. Configure one or more connections by Facebook Page ID (every active lead
  form on the page) or pin a single Form ID; a "Load forms" button validates the
  token and lists the page's forms. Standard Meta fields map onto the core lead
  columns (name, email, phone) and every other answer is preserved as a
  custom answer; the Meta lead id is the stable `external_id`, so re-fetching the
  same window is idempotent. Each connection refreshes on its own interval
  (hourly scheduler sweep, `lodgely:meta-leads:fetch`), or fetch on demand from
  the page. Reuses the existing Meta access token — it must carry the
  `leads_retrieval` permission plus access to the page that owns the forms. The
  nav entry only appears once Meta is connected.
- **"Fetch data now" button on the Reporting page.** Ad metrics are pulled
  automatically once a day (05:00, yesterday's figures), so a freshly connected
  platform shows an empty report until the next scheduled run. Operators can now
  trigger an immediate pull of the last 7 days from the Reporting page (native
  form → controller → redirect, per the Livewire-morph rails) — both in the
  header toolbar and the empty state — instead of waiting for the cron. Source
  resolution and the day-by-day fetch loop are shared with the scheduled command
  via a new `AdMetricsImporter` service, and a flaky platform no longer aborts
  the whole run (per-source/day errors are collected and surfaced).
- **Modern trend charts on the Reporting dashboard.** The operator `/reporting`
  page now renders a row of TradingView-style daily charts (total spend, clicks,
  impressions, platform leads, lodgely leads) above the campaign table: a smooth
  area/line with a gradient fill and an interactive hover crosshair + tooltip.
  They are dependency-free inline SVG with a sprinkle of Alpine for the hover —
  no chart library, no build step. The client `/my-reports` per-metric charts
  were upgraded to the same component, so operators and clients see the same
  modern visuals.
- **"Clear ad-metrics data" button on the Reporting page.** The mock ad-spend
  rows a demo install ships with live in `ad_spend_reports`, which carries no
  per-import tag — so until now there was no way to delete them from the UI.
  Operators get an explicit, confirm-gated purge (native form → controller →
  redirect, per the Livewire-morph rails) that wipes every ad-metrics row for
  the tenant. Leads are untouched; mock sources repopulate on the next import.

### Changed

- **Unloading demo data now also clears the mock ad-spend rows** behind
  Reporting, so "Unload demo data" really does leave a clean slate. This is
  skipped automatically once a live Meta or Google Ads connection is configured,
  so real spend data is never deleted — clear that from the Reporting page
  instead. The demo-data page shows the mock ad-metrics row count alongside the
  lead/user counts.
- **Client report emails are now mobile-responsive.** Added a `<style>` media
  query to the email head: on phones the KPI cards stack one-per-row (instead of
  a cramped fixed three-across grid) and the monthly metrics table scrolls /
  shrinks rather than overflowing. The layout still falls back to the inline
  styles in clients that strip `<style>`.

- **Outbound email (SMTP) is now configurable in the UI under Settings →
  Email.** Operators can point lodgely at their mail server — host, port,
  encryption (STARTTLS / SSL / none), username, password, and the From
  address/name — without editing `.env`. The password is stored encrypted at
  rest and the saved settings override the `MAIL_*` env config at runtime, for
  both web requests (password resets) and the queue worker (reporting emails).
  A "Send test email" button sends a real message synchronously so SMTP errors
  (bad password, blocked port) surface immediately. This addresses the common
  "reporting emails don't arrive" case, which is usually the default
  `MAIL_MAILER=log` driver writing mail to the log instead of sending it. The
  `.env` `MAIL_*` vars remain supported as a fallback when the UI toggle is off.

- **Post-restore notice that integration credentials may need re-entry.**
  Google Sheets / Google Ads / Meta / AI secrets are stored encrypted with
  the install's `APP_KEY`, so a backup restored onto a *different* server
  (or after `APP_KEY` rotation) can't decrypt them — they read as empty and
  the integrations look disconnected. After a UI restore the login screen now
  shows an amber heads-up to re-enter and re-verify those credentials under
  Settings, the restore card warns about it up front, and the README and
  command-line restore note it too.

### Changed

- **A restore is no longer reported as failed when `pg_restore` only skips
  ignorable statements.** `pg_restore` runs in continue-on-error mode: it
  restores everything it can and exits non-zero with "errors ignored on
  restore: N" (almost always `DROP`s of objects that did not exist yet under
  `--clean`). That previously surfaced as a scary "Restore failed: …" even
  though the data was fully restored. Restore now treats that case as success
  and reports the skipped-statement count instead; genuine fatal failures
  (bad connection, unreadable archive) still error out as before.

### Fixed

- **Generated backup archives are now gitignored.** Backup `.zip`s written at
  runtime to `storage/app/private/backups/` were not covered by `.gitignore`
  (unlike the `imports/` directory next to it), so they showed up as untracked
  files. Added the ignore rule and a `.gitkeep`, mirroring the imports pattern.

- **Backup restore crashed in `pg_restore` with "too many command-line
  arguments".** The shared Postgres helper appended the database name as a
  trailing positional argument, which is correct for `pg_dump` but wrong for
  `pg_restore` — its only positional is the input archive, so the trailing
  database name was rejected. The target database is now passed with `-d`
  for both tools, leaving the dump file as `pg_restore`'s single positional.
  Restoring an uploaded archive now completes instead of erroring out.

- **Backup restore was completely non-functional.** On the
  `/settings/backups` page, selecting a `.zip` archive hung on "Uploading…"
  forever, and clicking **Restore and overwrite database** (after typing
  `RESTORE`) did nothing. Both halves were driven by Livewire — an async
  `wire:model` file upload plus a `wire:submit` action — which silently
  drop in production, the same morph-layer failure already documented for
  the inbox filter card. Create / delete / restore now post to a native
  `BackupController` (plain multipart `<form>` → controller → redirect),
  so uploads and the destructive restore actually run. Restore still
  signs the operator out and bounces them to the login screen on success.

- **README Quick start:** added a warning that `DB_PASSWORD` must be set in
  `.env` before the first `docker compose up`. PostgreSQL only reads the
  password on initial volume creation; changing it afterwards leaves the old
  hash baked in and causes `password authentication failed`. The fix
  (`docker compose down -v`) is now documented inline.

### Added

- **"Delete all imports" on the Google Sheets page.** A single control in the
  "Recent imports" header force-deletes every Google Sheets import and the leads
  they created — a one-click way to clear a duplicate backlog. The next idempotent
  fetch rebuilds one clean copy per sheet row.

- **Idempotent Google Sheets imports.** Each fetched row now carries a stable
  content fingerprint (`leads.external_id`). When the scheduled fetch re-reads a
  sheet, rows it has already ingested are recognized and skipped instead of being
  re-created. The import summary and the "Recent imports" table on
  `/imports/google-sheets` now show a **Skipped** count alongside Imported /
  Duplicate / Invalid.

- **`lodgely:google-sheets:dedupe` cleanup command.** Backfills the row
  fingerprint on Google Sheets leads imported before idempotency existed, then
  soft-deletes the duplicate copies left behind by past full re-fetches, keeping
  the earliest of each. Supports `--dry-run` to preview.

- **Scheduler container in the Docker stack.** `docker compose up` now starts
  a `scheduler` service running `php artisan schedule:work`. Previously no
  container (and no documented cron) ever invoked the Laravel scheduler, so
  none of the recurring jobs — hourly Google Sheets fetch, IMAP pull, daily
  ad-metrics import, report-email dispatch, GDPR purge — actually ran on a
  standard install. Sheets configured with a 24 h refresh interval now really
  do refresh every 24 h. The no-Docker quick start documents the
  `schedule:work` / cron equivalent.

- **Google Sheets troubleshooting help.** `/settings/google-sheets` now has a
  "Connection keeps breaking?" panel listing the common causes of a dying
  connection (OAuth app left in Testing → Google expires refresh tokens after
  7 days; `APP_KEY` rotation invalidating the encrypted stored credentials;
  rotated client secret; revoked account access).

### Changed

- **Topbar decluttered: new Settings dropdown (operator).** Users, Webhooks,
  Backups and Demo data moved from individual top-level items into a single
  "Settings" dropdown, matching the Imports / Reporting / AI pattern. The
  mobile nav group formerly labelled "Workspace" is now "Settings" with the
  same order.

- **Favicon and `img/logo.svg` now match the brand artwork.** The dot-staircase
  in both SVGs descended left→right (tallest column on the left), mirroring
  the README/topbar PNG which ascends toward the wordmark. Both icons now
  ascend left→right like the authoritative PNG.

- **Inbox column picker polish.** The toolbar toggle is now labelled
  "Columns" (the panel manages all visible columns, not only custom-question
  ones); the "(n / max)" counters update live as chips are toggled and turn
  amber with a warning when the selection exceeds the cap (the server
  previously truncated silently); "Reset to defaults" moved flush-left so it
  can no longer be misclicked next to "Apply".

- **Roomier pagination.** Page-number and arrow buttons in both pagination
  views (Livewire + standard) gained consistent, slightly larger padding for
  better touch targets, and the Livewire variant now guarantees a gap between
  the "Showing X to Y" summary and the pager (inline margin, stale-CSS-proof).

- **Google Sheets setup guide corrected.** Step 2 used to recommend leaving
  the OAuth consent screen in Testing — the exact configuration that makes
  Google expire the refresh token every 7 days. It now instructs publishing
  the app to Production and explains why.

- **Backup & recovery (`/settings/backups`).** Operators can create a full
  database backup (a `.zip` containing a `pg_dump` archive plus a manifest),
  download it to a local machine, delete old ones, or restore the database
  from a previously downloaded archive — all from the UI, with a typed
  ("RESTORE") confirmation since restoring overwrites every table and signs
  the operator out. The same operations are available as artisan commands
  (`lodgely:backup:create [--keep=N]`, `lodgely:backup:restore <path>`) for
  cron jobs or scripted server migrations. New `App\Support\Backup\BackupManager`
  service centralizes the `pg_dump`/`pg_restore` handling; archives live under
  `storage/app/private/backups/`.

- **Favicon.** Added a square SVG favicon (`public/favicon.svg`) derived from the lodgely dot-staircase icon. Linked in both the app and guest layouts.

- **Ad platform connection UI (`/settings/ad-platforms`).** Operators can now
  connect Meta Ads and Google Ads entirely from the admin UI — no `.env`
  editing. Credentials are stored encrypted at rest, secret fields are
  write-only ("leave blank to keep"), and each platform has a "Test
  connection" button that pulls yesterday's metrics and reports the result.
  Google Ads adds a one-click **"Connect Google Ads" OAuth flow** that
  captures the refresh token automatically (mirrors the Google Sheets
  handshake), plus a copy-paste redirect URI and step-by-step setup guides.
  Per-platform **Enable** toggles switch the live adapters on/off for the
  daily pull. Existing `LODGELY_*` env vars still work as a fallback.

- **More ad KPIs in reporting views.** Custom reporting views can now
  include **CPC** (cost per click), **CPM** (cost per thousand
  impressions), and **Conversion rate** (platform leads ÷ clicks). All
  three are derived from existing ad-spend data — no new ingestion — and
  flow through the client report table, KPI strip, report emails, and AI
  summaries automatically.
- **Live / Hidden toggle for reporting views.** Operators can take a view
  offline without unassigning its clients. Views default to Live (so
  assigning a client still makes the view visible); a "Hide" / "Set live"
  action and status badge on `/reporting/views` flip the state. Hidden
  views disappear from clients' "My reports" and pause their scheduled
  report emails.
- **Time-series charts on client reports.** `/my-reports` now renders a
  compact monthly bar chart per selected metric above the table,
  dependency-free (inline SVG, server-rendered).
- **Bulk delete action.** Operators can now select multiple leads and
  delete them in one step via a red "Delete" button in the bulk action
  bar. Each deletion is audited individually. Includes a browser
  confirmation dialog.
- **Sortable column headers.** The Received, Name, Status, and Priority
  columns now show clickable up/down arrow indicators. Clicking cycles
  through ascending, descending, and default sort. Active sort direction
  is highlighted in brand colour.
- **Select-all checkbox indeterminate state.** The header checkbox now
  shows a dash (indeterminate) when some but not all leads on the page
  are selected, giving clearer visual feedback.

### Fixed

- **"Delete" on a Recent Google Sheets import now works.** The button used a
  `wire:click` action whose click was silently dropped (the documented Livewire
  morph-drop in this app) — the confirm dialog appeared but nothing was deleted.
  It is now a native `<form>` POST to a controller, matching the pattern used for
  the inbox filter-card actions.

- **`lodgely:google-sheets:dedupe` no longer skips leads it can't trace to a
  sheet.** It previously ignored any lead whose spreadsheet couldn't be resolved
  (e.g. the sheet source was deleted/recreated), leaving the backlog in place.
  Deduplication now groups on the lead's stored row content, so every lead is
  considered; the importer-compatible fingerprint is still backfilled on
  survivors wherever the spreadsheet resolves.

- **Google Sheets fetches no longer pile up duplicate leads.** The importer
  re-reads the whole sheet on every scheduled run; previously each run re-created
  every existing row as a fresh `duplicate`-status lead, so the inbox filled with
  dozens of copies. Re-fetches are now idempotent (see Added) — already-seen rows
  are skipped. Run `lodgely:google-sheets:dedupe` once to clear the existing
  backlog.

- **Pagination styling.** Fixed three issues with the desktop pagination
  control: the active page number and the prev/next arrows rendered as
  near-white/invisible in light mode — the active page now uses the
  app's core `bg-slate-900` fill (guaranteed in the CSS bundle, matching
  the primary button) instead of the custom `bg-brand-600`, and arrows
  use a darker `text-slate-600` so they read clearly. Trimmed the
  oversized inner padding on the page buttons (`py-1.5` → `py-1`,
  `text-sm` → `text-xs`). Removed the inline `padding:4px` override on
  the inbox bar and standardised every pagination wrapper to `px-4 py-3`
  so the box has consistent breathing room on the outside.
- **Pagination padding.** The pagination bar below the inbox table now
  has proper left/right padding (`px-4 py-3`) so it no longer looks
  cramped against the table edges.
- **Google Sheets reimport after deleting imports.** `deleteImport()`
  now force-deletes leads (hard delete) instead of soft-deleting them,
  so reimporting the same sheet creates genuinely fresh leads without
  ghost duplicate matches. The audit trail in `lead_events` is preserved
  independently.

### Fixed

- **"Generate" password button works on user edit modal.** The
  `wire:click="generatePassword"` handler silently dropped clicks —
  the same Livewire morph issue that hit the inbox filter card.
  Replaced with an Alpine `x-on:click` handler that generates the
  password client-side (`crypto.getRandomValues`), sets the input
  value via `$refs`, and pushes to Livewire via `$wire.set()`. The
  warning text now shows instantly via Alpine `x-show` instead of
  waiting for a server round-trip.
- **Footer and README version badges updated to 0.27.0.** Both were
  stale (`composer.json` said 0.26.1, README badge said 0.22.4).
  Added a note to `CLAUDE.md` reminding maintainers that the footer
  reads from `composer.json` and the README badge is a static string
  — both must be bumped in the same commit.

### Added

- **Each expanded panel has a "Close" button.** Sources, Saved views,
  Custom columns, and Save current view panels all show a small header
  row with the panel name and a right-aligned "Close" button. Users
  no longer need to find the original toggle label in the toolbar
  (which is hard to spot once the panel is open, especially on mobile).
- **Demo data management page** at `/settings/demo-data` (operator-only,
  also linked from the topbar as "Demo data"). One-click button to
  populate the inbox with the canonical demo dataset — ~60 neutral
  leads, 12 Meta Lead Ads leads across two demo clients, a known
  duplicate pair, the two scoped demo client logins
  (`client.northwind@example.com` / `client.acme@example.com`,
  password `password`) and the demo operator if missing. A second
  button wipes the lot again. Demo leads are tagged by attaching them
  to a dedicated `imports` row with `source = 'demo_seed'`, so unload
  is a single scoped delete that never touches real CSV / webhook /
  IMAP imports. The currently signed-in user is never deleted, so an
  operator who logged in as a demo account can still safely click
  "Unload demo data". Lifted out of `DatabaseSeeder` into a reusable
  `App\Domain\Demo\DemoDataManager` service so the UI and the seeder
  call the same code path.
- **CLAUDE.md note: don't rely on a single Tailwind utility for
  critical spacing.** Documents the `gap-x-3` failure we just hit
  (deployed CSS bundle was stale; brand-new utility classes weren't
  in it) and the layered-spacing pattern (padding on items + gap +
  visible separators) so the next maintainer doesn't have to
  rediscover why their first-time-used utility silently no-ops in
  production.

### Fixed

- **Toolbar items no longer touch each other.** "484 leadsShow:
  SourcesSaved views…" — the `gap-x-3` utility wasn't applying in
  the deployed CSS bundle (likely a stale build from before that
  class was first used). Put the spacing on each item itself
  (`px-1.5 py-0.5`) so the layout doesn't depend solely on a fresh
  Tailwind compile, and dropped visible `·` separators back between
  the count / Show group / Clear so the rhythm reads even when text
  wraps to a second row.

### Changed

- **Saved-views chips work via HTML forms now too.** The three
  per-chip actions (load / star-as-default / delete) used to be
  `wire:click` buttons — same silent-drop failure as the column
  picker. Rewrote them as a tiny three-button `<form method="POST">`
  per chip, dispatched on `action=load|default|delete` by the new
  `InboxSavedFilterController@action`. Delete now has a JS
  `confirm()` since one accidental tap deletes the view.
- **Sources chips are clickable filter links.** Each chip is an
  `<a href="?source=…">` that flips the URL `source` param —
  Livewire's `#[Url]` binding on `$source` picks it up on the
  next request and the table filters. Clicking the currently-
  active source chip clears the filter. Active chip carries a
  filled-in style so the state is obvious.
- **Custom-columns chips share the saved-views chip style.** Same
  `rounded-full border border-slate-200 px-2.5 py-0.5` shape, just
  with `has-[:checked]:` driving the on/off background instead of
  the chip type. Picker now visually matches Sources and Saved
  views side-by-side.

### Changed

- **All four inbox filter-card panels open inline with the same
  rhythm.** Sources, Saved views, Custom columns, and Save current
  view now all share the parent `x-data` Alpine scope and the same
  `mt-3 pt-3 border-t` styling — no more per-panel dropdowns or
  fixed bottom-sheets. The right-hand toolbar group dropped its
  `·` / `|` separators (they wrapped awkwardly on mobile) and uses
  `gap-x-3 gap-y-2` for a comfortable mobile rhythm.
- **"Save current view" is now a native HTML form, like Custom
  columns.** Posts to a new `InboxSavedFilterController` with hidden
  inputs carrying the current filter URL state. On success, redirects
  to `/inbox?[filters]&saved-views=1` so the user lands back on their
  view with the new chip visible in the Saved-views list. The old
  Livewire save-dialog dropdown was the last hold-out using the dropdown
  pattern that proved unreliable on the inbox subtree.

### Added

- **`CLAUDE.md` "Hard-won gotchas" section.** Documents the PHP 8.2+
  trait-constant access rule (always go through the consuming class)
  and the inbox filter-card form pattern (native HTML form →
  controller, not Livewire dialog), so the next maintainer doesn't
  rebuild the column picker a sixth time.

### Fixed

- **Apply button no longer 500s.** `InboxColumnPickerController` accessed
  `WithColumnPicker::AVAILABLE_COLUMNS` directly on the trait, which PHP
  8.2+ forbids for non-`final` trait constants — the request threw
  `Cannot access constant AVAILABLE_COLUMNS of trait …` before
  validation even ran. Routed the lookup through the consuming
  class instead (`InboxPage::AVAILABLE_COLUMNS`), which is the
  guaranteed-supported path.

### Changed

- **Column picker is now a plain HTML `<form method="POST">` with an
  Apply button.** Every Livewire-driven variant — `wire:click` on
  chips, `@click="$wire.…"`, `wire:model.live` on hidden checkboxes
  with lifecycle hooks — silently dropped clicks for the user in
  production across four rebuild rounds. Rebuilt as a native form
  that POSTs to a new `InboxColumnPickerController@update`:
  - Chips remain `<label>`-wrapped checkboxes with the same CSS-
    driven `has-[:checked]:` / `peer-checked:` instant visual flip,
    but they're now `<input name="columns[]" value="…">` /
    `<input name="questions[]" value="…">` — pure HTML.
  - Two submit buttons: **Apply** writes the form's checkbox state to
    `users.inbox_columns`; **Reset to defaults** clears the JSONB
    column so role-aware defaults take over.
  - The controller redirects to `/inbox?columns=1` so the picker
    re-opens on reload and the user sees their picks reflected in the
    table immediately.
  - A short "Saved." note appears in the picker after a successful
    submit (flash session).

  Trade-off: one full page reload per Apply. Fine — the picker is a
  low-frequency action and everything else in the inbox stays fully
  Livewire-reactive.

- **Column picker is now native HTML checkboxes + `wire:model.live`.**
  Three rounds of `wire:click` / `@click="$wire.…"` chip refactors all
  failed to register clicks on the inline picker in production. Rebuilt
  the picker around the canonical Livewire binding pattern:
  - Each chip is a `<label>` wrapping a hidden
    `<input type="checkbox" wire:model.live="pickedColumns" value="…">`.
    Clicking the chip toggles the checkbox — pure HTML, works without
    JavaScript.
  - The chip's visible state flips instantly via Tailwind
    `has-[:checked]:` / `peer-checked:` variants — no waiting on a
    server round-trip for visual feedback.
  - `wire:model.live` sends the new array to the server on every
    toggle; the new `updatedPickedColumns` / `updatedPickedQuestions`
    Livewire lifecycle hooks cap-enforce and persist in one place.
  - Chips at the 8-column / 5-question cap render `disabled`, so the
    cap is visually obvious before the user clicks.
  - `togglePickedColumn` / `togglePickedQuestion` are retained as
    public Livewire actions for tests / programmatic callers.
  No more `wire:click`, no Alpine `@click="$wire.…"`, no
  `wire:ignore.self`, no fixed-position panels. The checkbox DOM
  state is now the source of truth.

### Changed

- **Custom columns is now an inline expansion row, not a dropdown.** The
  dropdown approach kept fighting Livewire morphs — chip clicks either
  closed the panel (Alpine open state reset by morph) or didn't visibly
  do anything (Done required a separate step). Replaced with the same
  pattern Sources / Saved views already use: a toggle button in the
  toolbar that expands a row below the filter bar. Open state lives in
  the parent `x-data` (`columnsOpen`) so it survives morphs, and every
  chip toggle auto-persists to `users.inbox_columns` — no Done button,
  no Cancel, no backdrop. Reset stays as an explicit action.
- **Toolbar separator dots / pipes hide on mobile.** When the right-hand
  group wraps onto its own row on `<sm`, the `·` and `|` separators
  stranded at line boundaries looked broken. Marked them
  `hidden sm:inline` so mobile just relies on gap spacing.

### Fixed

- **Custom-columns dropdown opens on mobile (round three).** After the move
  to Livewire-only state in b72e138, `wire:click="$toggle('showColumnPicker')"`
  on the trigger button stopped opening the panel on mobile — tapping the
  button did nothing. Moved the open/close state back into Alpine
  (`x-data="{ open: false }"`) so the trigger toggles instantly without a
  server round-trip, and added `wire:ignore.self` to the wrapper so chip
  clicks inside the panel can't reset Alpine state and snap the dropdown
  shut. Chips, Reset, and Done still use `wire:click` for the Livewire
  action; Done / Cancel close the panel via Alpine.
- **Toolbar wraps onto its own row on mobile.** The right-hand "lead count ·
  Show: · Custom columns · Save current view · Clear" group had `shrink-0`
  + `ml-auto` + `justify-end`, which forced it onto a single line that
  overflowed the viewport on `<sm`. Dropped those classes on mobile
  (`w-full sm:w-auto sm:ml-auto sm:justify-end`) and switched to
  `gap-x-2 gap-y-1` so when items wrap they stack with a tighter vertical
  rhythm. Desktop layout is unchanged.
- **Custom-columns dropdown opens on mobile.** The previous patch anchored
  the panel to `<div class="relative">` wrapping only the trigger text, so
  on `<sm` screens `left-0 right-0 top-full` sized the panel to the
  ~60px-wide trigger and it effectively didn't appear. Switched the panel
  to `fixed left-2 right-2 bottom-2` on mobile (bottom-sheet, viewport-
  anchored), keeping the anchored `absolute right-0 top-full` dropdown on
  `≥sm`. Added a mobile backdrop (`sm:hidden fixed inset-0 bg-slate-900/30`)
  that calls `closeColumnPicker` on tap so users have a tap-to-dismiss
  affordance without relying on Alpine.
- **"Columns" → "Custom columns".** Trigger label renamed at the user's
  request; also gave the bare-text button a tap-target buffer
  (`-mx-1 px-1.5 py-1`) so it's easier to hit on touch.
- **Columns dropdown actually toggles columns again.** The recent refactor
  to an Alpine `x-data="{ open: false }"` dropdown wrapped the chips in a
  scope where `$wire` was never bound (console showed
  `Alpine Expression Error: $wire is not defined`), which also stranded
  `wire:click` on the chip buttons — clicking them did nothing and "Done"
  threw. Rebuilt the picker as a Livewire-only dropdown anchored under the
  trigger: open state is `$showColumnPicker` (toggled via
  `wire:click="$toggle('showColumnPicker')"`), the panel renders behind
  `@if($showColumnPicker)`, and every chip / Reset / Cancel / Done is pure
  `wire:click`. No more Alpine in the picker, so a stale JS bundle with a
  second Alpine instance can't break it.

### Changed

- **Columns dropdown is mobile-friendly.** On `<sm` screens the panel goes
  full-width under the toolbar (`left-0 right-0 top-full`); on `≥sm` it
  anchors to the right edge of the trigger and caps at `min(420px,
  calc(100vw - 2rem))`. Added `max-h-[70vh] overflow-y-auto` so a long
  custom-question list scrolls instead of overflowing the viewport.
- **Topbar logo nudged down to baseline-align with nav.** The transparent
  PNG has uneven vertical whitespace around the "lodgely" wordmark, so
  centring the image in the header bar left the text sitting visibly above
  "Inbox / Imports / …". Added `margin-top: 0.5rem` to the `<img>` so the
  text inside the mark lines up with the nav row.
- **Form fields get more breathing room (round two).** Native `<input>` /
  `<select>` padding bumped to `0.75rem / 1rem`, `<textarea>` to
  `0.875rem / 1rem` — the previous bump still felt cramped against the box
  edges in the Workflow / Notes panel.
- **Brand logo swapped to the transparent PNG.** `public/img/logo.png` is now
  the 1080×540 RGBA mark so the gradient renders cleanly on any background
  (login card, topbar, dark mode).
- **Form padding applied as utility classes too.** Lead panel selects
  (Status / Priority) and the "Add a short note…" textarea, plus all auth
  form inputs (login / forgot / reset), now carry `py-3 px-4` inline so the
  padding survives any CSS rebuild / cache state — the global element-level
  rule in `app.css` stays as a safety net for everywhere else.
- **Logo URLs are cache-busted.** `?v=filemtime(...)` appended on every
  `<img>` for `img/logo.png` so swapping the asset doesn't get masked by a
  stale browser cache.
- **Auth forms use the transparent logo treatment everywhere.** The forgot-
  password and reset-password screens were still using the old
  `h-14 rounded-xl shadow-lg` chrome; bumped to `h-24 w-auto` to match the
  login screen.

### Fixed

- **Pagination is dark in dark mode.** Livewire 3 ships its own paginator
  view at `livewire::tailwind`; the existing custom view lived under
  `vendor/pagination/` which only overrides Laravel's framework view and was
  silently ignored by Livewire. Published the same Tailwind+dark template
  to `resources/views/vendor/livewire/tailwind.blade.php` (with `wire:click`
  page navigation) so it actually wins.

### Removed

- **Stale mock custom-answers wiped on seed.** `DatabaseSeeder` now nullifies
  any leftover `custom_answers` rows from earlier runs of the now-removed
  `CUSTOM_QUESTIONS` factory pool. This stops "What is your budget?",
  "Preferred contact method?", "When would you like to start?" and friends
  from surfacing in the Columns dropdown's custom-question list on dev DBs
  that were seeded before the pool was removed. Re-run `php artisan db:seed`
  (no need to `migrate:fresh`) to clean up. Updated stale comments and the
  `lodgely:import:meta-mock` command description accordingly.

### Changed

- **Inbox toolbar: Columns and Save view are real dropdown menus.** Both buttons
  now open an Alpine.js dropdown anchored to the trigger (positioned below, right-
  aligned), rather than expanding a full-width panel below the toolbar. Click
  outside or press `Escape` closes them. "Save view" is renamed to "Save current
  view" and gets a clearer form (named field, "Set as my default view" checkbox,
  `Enter` submits). The dropdown auto-closes after a successful save via a new
  `inbox-saved-filter-stored` Livewire event (validation errors keep it open).

### Fixed

- **Inbox toolbar: Columns / Save view / Clear were not clickable.** Livewire's
  DOM morpher was losing track of these buttons when sibling elements toggled
  (Save view appearing/disappearing with the save dialog, Sources/Saved-views
  appearing/disappearing with data). Added `wire:key` to each `wire:click`
  button and switched the conditional `class` strings to Blade's `@class`
  directive so the morpher reconciles reliably.
- **Inbox toolbar spacing collapsed.** Replaced unreliable `gap-x-2 gap-y-1`
  with `gap-2` and added `px-0.5` padding to the major `|` dividers so toolbar
  items always have breathing room.

### Changed

- **Inbox filter bar: "Show:" group label.** Sources, Saved views, and Columns
  are now grouped under a `Show:` prefix in the toolbar action row, making their
  purpose obvious. Columns is always visible (was conditionally hidden by a stray
  double-separator bug when no sources/saved filters existed). Save view and Clear
  remain separate after a `|` divider. Active toggles go bold so you can see what's
  open at a glance.
- **Inbox filter bar: Sources and Saved toggles moved inline.** Both are now
  plain text buttons in the toolbar's right-side action row (alongside Columns /
  Save view / Clear), removing the separate sub-row toggle headers entirely.
  Clicking the name in the toolbar expands/collapses the content panel below.
- **Pagination dark mode fixed.** Published a custom Tailwind paginator view
  (`resources/views/vendor/pagination/tailwind.blade.php`) using the app's
  `slate-*` color tokens and `dark:` variants so page numbers, prev/next buttons,
  and the active-page indicator all render correctly in dark mode.
- **Inbox filter bar: Sources and Saved views are now collapsible.** Both rows
  are hidden by default behind Alpine.js toggle buttons showing the item count
  (e.g. "Sources (3) ▾", "Saved views (2) ▾"). No server roundtrip.
- **Inbox page: leaner UI.** KPI stat cards are now hidden by default behind a
  "Show stats" toggle (Alpine.js, no server roundtrip). The standalone "Leads by
  source" panel is merged into the filter bar as a compact source-pill sub-row.
  The filter bar is condensed to a single compact toolbar row with
  placeholder-as-label selects instead of a two-row labeled grid. The sort
  control moves inline. An active-filter count badge, an inline lead count, and a
  "Clear" button that highlights when filters are active are added to the toolbar.

### Removed

- **`LeadFactory::CUSTOM_QUESTIONS`.** Removed the mock custom-question pool and
  its usage in the `meta()` factory state. Seeded Meta leads no longer carry
  synthetic form answers; the rendering path continues to work for real ingested
  leads.

---

## [0.21.0] · 2026-05-19

### Added

- **Google Sheets column mapping: `lead_id`, `form_id`, `created_time` fields.**
  Operators can now map sheet columns to the external lead ID (`lead_id` →
  `meta_lead_id`), form ID (`form_id`), and creation timestamp (`created_time`,
  stored in `custom_answers`).
- **Named custom-answer columns.** Selecting "Custom answer (named key)…" in the
  Google Sheets column-mapping dropdown reveals a key-name text field. The value
  is stored as `custom_answer:<key>` in `column_map` and surfaces under that key
  in the lead's `custom_answers` JSON, with the sheet column header used as the
  question label in the inbox.
- **Delete import.** Each row in the Google Sheets "Recent imports" table now
  has a Delete button that removes the Import record and cascades to delete all
  leads it created (confirmed with an exact lead count before proceeding).

### Changed

- **Inbox "Received" column is now pickable.** The first column (lead
  `created_at`) was previously a fixed anchor; it's now listed alongside the
  other columns in the column picker. Operators who track a different date in
  custom answers (e.g. a mapped `created_time`) can hide Received and surface
  that custom column instead. Total column cap raised 7 → 8 to accommodate
  Received as a default pick.
- **Inbox custom-question column limit raised 3 → 5.** Operators can now pin
  up to five custom-answer columns in the inbox table alongside the standard
  fields.

### Fixed

- **Google Sheets custom answers now appear in the inbox.** `GoogleSheetsLeadSource`
  now emits `custom_answers` as a list of `{question, answer}` objects (matching
  the Meta Lead Ads convention) instead of a flat key-value map. The sheet column
  header becomes the question label when present; named custom-answer keys fall
  back to a humanised version of the key (e.g. `event_size` → "Event size"). This
  makes UTM tags, `question_01–04`, `is_quality`, `is_converted`, `created_time`
  and operator-named custom answers show up in the inbox column picker under
  "Custom form questions" and in the lead-detail panel.
- **"Custom answer (named key)…" key input now appears immediately** when the
  field dropdown is switched to that option. Switched from Alpine `x-show`
  (which didn't react to `wire:model` without `.live`) to `wire:model.live`
  + Blade `@if`, matching the pattern used elsewhere in the codebase.

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
