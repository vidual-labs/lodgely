# lodgely Roadmap

lodgely's job in the lead pipeline is **lead evaluation**: intake from every
source, qualification, labels, assignment to clients and owners, outreach
state and reporting. It is a lead intake hub, not a CRM (see CLAUDE.md →
*Product north star*).

The visitor-facing side belongs to
[OpenFlow](https://github.com/vidual-labs/openflow): forms, routing, consent,
booking at submit. The split and the interface between the repos are defined
in the **shared section** at the end of this file, which is duplicated in
OpenFlow's `ROADMAP.md`. Completed history lives in
[docs/ROADMAP.md](docs/ROADMAP.md).

Target use: lead generation for SMEs and client projects in the DACH region,
self-hosted and GDPR-sensitive.

Phases are ordered by value for lead-gen conversion and data quality. The
interface contract comes first because most later items depend on it.
Effort: **S** ≈ days, **M** ≈ 1–2 weeks, **L** ≈ several weeks.

| Phase | Theme | lodgely items | OpenFlow items (see its ROADMAP.md) |
|---|---|---|---|
| 0 | Shipped: correctness & privacy batch | done in 0.55.0 | done in 0.41.0 |
| 1 | Contract & speed-to-lead | LG-1, LG-2, LG-3 | OF-1, OF-2, OF-3, OF-4, OF-13 |
| 2 | Conversion & data at the source | LG-6, LG-8 | OF-5, OF-6, OF-7, OF-9, OF-8 |
| 3 | Lead evaluation | LG-4, LG-5, LG-10, LG-7, LG-9 | — |
| 4 | GDPR data lifecycle | LG-11, LG-12, LG-13 | OF-10, OF-11 |
| 5 | Operator scale | LG-14 (deferred) | OF-12, OF-14, OF-15 |

---

## Phase 0 — Shipped in 0.55.0

- **The OpenFlow pull no longer skips new leads on non-UTC installs.**
  OpenFlow's offset-less UTC timestamps were read in `APP_TIMEZONE`. With
  `Europe/Berlin`, submissions made up to about an hour after the previous
  fetch were treated as already seen.
- **AI lead qualification no longer sends OpenFlow PII to the provider.**
  The prompt's `raw_payload` was filtered by key name only. OpenFlow answers
  are keyed by opaque field ids, so name, email, phone and IP went out
  unmasked.
- New OpenFlow sources pull **hourly** by default (was 24 h).
- "Delete all imports" now resets the pull cutoff, so the next fetch
  rebuilds the backlog as documented.
- Doc drift: retention default, soft-delete purge caveat, "no API token"
  comments, OpenFlow dedupe scope, Reporting README.

---

## Phase 1 — Contract & speed-to-lead

### LG-1 · OpenFlow push endpoint
**Repo:** lodgely · **Status:** missing (the generic webhook intake is token-in-URL only, with no HMAC and no idempotency, and it rejects OpenFlow's body with a 422) · **Effort:** M · **Depends on:** X-1, OF-3
**Why:** speed-to-lead. Today a lead waits up to an hour (and until 0.55.0
up to 24 h) before anyone can see it. A push delivers it in seconds (D2).

**Acceptance criteria**
- Each OpenFlow source can enable push. It then shows a URL and signing
  secret to paste into an OpenFlow "Contract v1" webhook.
- `POST /api/openflow/{source}` verifies the HMAC and a timestamp no older
  than 5 minutes. It ingests through `LeadIngestor` with the same mapper as
  the pull, idempotent on the scoped `external_id`, and returns 2xx on a
  duplicate.
- A push with a bad signature, stale timestamp or unknown schema version gets
  a 4xx and an entry on the source's error log. It never creates a lead.
- The hourly pull keeps running as reconciliation. A test proves push + pull
  of the same submission yields one lead.

### LG-2 · Consume contract v1 in the pull
**Repo:** lodgely · **Status:** partial (pull works on the legacy API: newest-first offset paging, time cutoff with 60-min overlap, id-keyed mapping, no metadata) · **Effort:** M · **Depends on:** X-1, OF-1, OF-2
**Why:** the pull is the safety net. It must be gap-free and must survive
form edits instead of silently dropping or misfiling leads.

**Acceptance criteria**
- Against an install whose `/api/v1/meta` lists v1: the pull uses the cursor
  endpoint, stores `next_cursor` per source and maps by field **key** (id
  fallback). Older installs keep the legacy path.
- When a mapped key or id is missing from the form, or a submission would be
  dropped as "invalid" (no name/email/phone), the source shows a visible
  warning with a count. The cursor is not advanced past unmapped rows until
  the operator acknowledges.
- The importer no longer offers `status` / `priority` as mapping targets
  (shared-section violation). Existing mappings to them are shown as
  deprecated and ignored once LG-5 ships.
- File answers arrive as metadata only (name/size/type). No base64 ever
  lands in `raw_payload`.

### LG-3 · Store the submission time
**Repo:** lodgely · **Status:** missing (`created_at` is the ingest time; OpenFlow's `created_at` is only in `raw_payload`) · **Effort:** S · **Depends on:** — (uses `submitted_at` from X-1 when available)
**Why:** reporting, SLAs ("answered within 1 h") and retention should run
on when the person enquired, not on when lodgely happened to fetch.

**Acceptance criteria**
- A new nullable `leads.submitted_at` is set by every importer that knows
  it (OpenFlow, Meta Lead Ads, Sheets with a mapped column) and backfilled
  for OpenFlow leads from `raw_payload`.
- Inbox sorting, reporting daily series and `retention_until` use
  `COALESCE(submitted_at, created_at)`.
- The lead panel shows both times when they differ by more than 5 minutes.

---

## Phase 2 — Conversion & data at the source

### LG-6 · Per-lead attribution
**Repo:** lodgely · **Status:** partial (Meta Lead Ads fill `campaign_id/ad_id/…`; Sheets UTMs land in custom answers; OpenFlow click ids stay in `raw_payload`; per-campaign reporting only counts Meta leads) · **Effort:** M · **Depends on:** X-1, OF-6
**Why:** operators and clients need to see which campaign produced a lead
for every source, not just Meta forms. That is the basis for CPL per campaign
and for LG-7.

**Acceptance criteria**
- Leads gain `utm_source/medium/campaign/term/content`, `gclid`,
  `gbraid/wbraid`, `fbc` and `landing_url`, filled by the OpenFlow importer
  (from the contract's `attribution`) and by the Sheets importer (instead of
  custom answers). Existing Sheets UTM custom answers are migrated.
- `CampaignRollup` counts leads per campaign by `campaign_id` **or** a
  matched `utm_campaign`, per a mapping the operator confirms once per
  campaign.
- The inbox can filter and show columns for source/medium/campaign. The lead
  panel's attribution block shows them.

### LG-8 · Partial leads
**Repo:** lodgely · **Status:** missing (`incomplete` status exists but is only ever set by hand) · **Effort:** S · **Depends on:** OF-8, LG-1 or LG-2
**Why:** opted-in partials are worth a call, but they must not inflate lead
counts, CPL or trigger quality conversions (D3).

**Acceptance criteria**
- `submission.partial` creates or updates a lead with status `incomplete`.
  `submission.completed` for the same submission id upgrades it in place
  (no duplicate) and re-runs qualification.
- Partial leads are excluded from KPIs, reporting totals and LG-7 uploads
  unless explicitly filtered in.
- Partial leads get their own short retention (default 30 days, config) and
  appear in the audit trail.

---

## Phase 3 — Lead evaluation

### LG-4 · Client model
**Repo:** lodgely · **Status:** partial (`client_name` free text on leads, users scoped by string via `user_lead_scopes`) · **Effort:** M · **Depends on:** —
**Why:** qualification rules, owners, reporting connectors and client type
all hang off "the client". A typo in a free-text name today silently hides
leads from that client.

**Acceptance criteria**
- A `clients` table (name, slug, client type, active) replaces free text
  everywhere a client is chosen. `leads.client_id` is backfilled from
  distinct `client_name` values (case-insensitive merge, operator review
  screen for near-duplicates).
- `Lead::scopeVisibleTo()` scopes by `client_id`. `client_name` remains a
  denormalised display column during one release for back-compat, and all
  authorization tests pass unchanged.
- Sources (OpenFlow, Sheets, Meta, webhook) pick a client from a list
  instead of typing a name.

### LG-5 · Rule-based qualification
**Repo:** lodgely · **Status:** missing (manual *Qualified* toggle; advisory AI; OpenFlow fields mappable onto status/priority) · **Effort:** L · **Depends on:** LG-4, OF-1 (stable keys), LG-2
**Why:** consistent, explainable qualification is the core of a lead intake
hub. It must live in exactly one place.

**Acceptance criteria**
- Operators define rules per client, optionally narrowed to a source/form:
  conditions on answers (by field key), attribution and contact
  completeness, each adding points. Thresholds map a score to `qualified`,
  `review` or `unqualified`. Rules are versioned, and each lead stores the
  rule version and the per-rule breakdown.
- Rules run on ingest and on "re-evaluate" (bulk too). The result sets the
  lead's single qualification state. The manual *Qualified* toggle becomes an
  audited override of that state, not a second flag.
- AI qualification stays advisory: it may *suggest* a state, and it is never
  applied without an operator click.
- Clients see the state and breakdown on their leads but cannot edit rules.

### LG-10 · New-lead notifications
**Repo:** lodgely · **Status:** missing · **Effort:** S · **Depends on:** LG-1 (to be fast), LG-5 (to filter)
**Why:** a fast first call converts. Pushing a lead into the inbox is only
half of it if nobody looks.

**Acceptance criteria**
- Per client: email (existing SMTP settings) on new leads, optionally only
  `qualified`, with a digest mode (hourly).
- The notification contains no more PII than the recipient could see in the
  inbox, and links to the lead.
- Sends are audited, and a failed send is retried by the queue worker.

### LG-7 · Quality conversions to ad platforms
**Repo:** lodgely · **Status:** missing (ad platforms only receive "every lead" from OpenFlow's own integrations) · **Effort:** L · **Depends on:** LG-5, LG-6, existing Google Ads / Meta connections
**Why:** optimising ad spend on *qualified* leads instead of all leads is
the biggest data-quality lever for paid campaigns (D5).

**Acceptance criteria**
- Per client and connector, the operator chooses which state(s) upload
  (qualified / successful). Google Ads uses offline conversion upload by
  `gclid`/`gbraid`/`wbraid`, Meta uses Conversions API `Lead` events with
  a lead-quality stage, reusing `/settings/ad-platforms` credentials.
- Uploads are idempotent per lead + stage, queued, retried and audited. Leads
  without a click id are skipped with a visible reason.
- Hashing and minimisation follow each platform's spec (SHA-256 email/phone
  only). Documented as a third-party call behind a config switch (CLAUDE.md
  compliance rail).

### LG-9 · Owner assignment
**Repo:** lodgely · **Status:** missing (routing only by client) · **Effort:** M · **Depends on:** LG-4
**Why:** in client teams, "who calls this lead" is the next question after
"is it qualified". Staying inside intake scope means assignment, not a
pipeline.

**Acceptance criteria**
- A lead can have one owner (a user with access to that client), set
  manually, in bulk, or by a simple per-client round-robin on ingest.
- "My leads" filter and saved view. The owner gets the LG-10 notification
  when set.
- Owner changes are audited. No deal stages or forecast fields are added.

---

## Phase 4 — GDPR data lifecycle

### LG-11 · Real erasure after retention
**Repo:** lodgely · **Status:** partial (`retention_until` + daily purge, but purge only soft-deletes; PII, notes, events, AI summaries stay) · **Effort:** M · **Depends on:** —
**Why:** a "purged" lead that still holds name, email and payload is not
deleted in the GDPR sense. This is independent of the contract and can be
pulled forward.

**Acceptance criteria**
- Soft-deleted leads older than a grace period (default 30 days, config) are
  hard-deleted, or anonymised when aggregate reporting must keep the row:
  PII columns nulled, `raw_payload` / `custom_answers` cleared, notes deleted,
  AI summaries about the lead deleted.
- `lead_events` keeps the event but its payload is scrubbed of PII values. A
  single "erased" event records who/when/why.
- A `--dry-run` preview and an audit summary are provided. A restored backup
  older than the erasure is documented as the remaining copy
  (backup retention).
- The idempotency lookup still prevents re-import after hard deletion (a
  tombstone of the scoped `external_id` hash).

### LG-12 · Data-subject export & erasure
**Repo:** lodgely · **Status:** missing · **Effort:** M · **Depends on:** LG-11, X-2, OF-10
**Why:** DACH clients get Art. 15/17 requests. One action should cover both
systems.

**Acceptance criteria**
- An operator searches by email/phone (normalised) across all clients and
  gets every matching lead, note and event.
- "Export" produces a machine-readable JSON + human-readable PDF/HTML bundle.
  "Erase" runs LG-11 immediately for those leads and calls OpenFlow's erasure
  API (X-2) for every linked OpenFlow source.
- One audit record holds both systems' results, including calon booking ids
  left for manual cancellation.

### LG-13 · Consent & lawful basis on leads
**Repo:** lodgely · **Status:** missing (no consent capture; `PRIVACY.md` lists it as not done) · **Effort:** S · **Depends on:** OF-9 (for OpenFlow leads)
**Why:** the operator must be able to show why they may contact a lead.

**Acceptance criteria**
- Leads store `lawful_basis` (consent / contract / legitimate interest) and,
  when known, `consent_given_at` + `consent_text_hash` from the OpenFlow
  contract.
- Manual entry and CSV import ask for the basis. The inbox can filter by it.
- Retention can differ by basis (config).

---

## Phase 5 — Operator scale

### LG-14 · Full multi-tenancy — deferred
**Repo:** lodgely · **Status:** partial (`tenant_id` everywhere, hard-coded `Tenant::DEFAULT_ID` in about 120 places, no resolver) · **Effort:** L · **Depends on:** LG-4
**Why deferred:** under D4 (one install per agency, clients scoped by
LG-4), per-client isolation is already covered by `visibleTo()` + the Client
model. Full tenancy only pays off for hosting several agencies on one
install.

**Acceptance criteria (if picked up)**
- A tenant resolver (subdomain or login), with every query and job resolving
  the current tenant instead of `DEFAULT_ID`.
- Cross-tenant access tests cover every Livewire page, controller and
  artisan command.

---

## Housekeeping (no phase)

- **Releases.** `CHANGELOG.md` has an `[Unreleased]` block covering
  0.22 → 0.54 while `composer.json` is 0.54.x. Promote it to dated release
  headings (CLAUDE.md already describes the step).
- **Generic webhook intake.** Add optional HMAC and an idempotency key
  (`external_id` header) to `/api/webhooks/{token}`, so non-OpenFlow senders
  get the same guarantees as LG-1.

---

## Shared: cross-repo interfaces & dependencies

> This section is **duplicated verbatim** in `vidual-labs/openflow/ROADMAP.md`
> and `vidual-labs/lodgely/ROADMAP.md`. Change it in both, in the same
> release pair.

### Who owns what

| Concern | Owner | Never in |
|---|---|---|
| Everything the visitor sees before or at submit: steps, validation, routing on individual answers (jump logic, conditional end screens, conditional redirects), consent capture, spam checks, calon booking at submit | **OpenFlow** | lodgely |
| Lead evaluation: scoring, qualification rules, labels, assignment to clients/owners, outreach state, reporting, ad-platform *quality* signals | **lodgely** | OpenFlow |
| Calendar availability + bookings | **calon** (external; known only through the interfaces below) | — |

**Rule of thumb.** A *single answer* deciding what the visitor sees next
(e.g. "budget < 5k → 'not a fit' end screen, no booking step") is **routing**
and lives in OpenFlow. A *combination of answers* producing a verdict about the
lead is **qualification** and lives only in lodgely. Qualification rules exist
in exactly one place.

**Current violations** (fixed by the items referenced):
- lodgely lets an OpenFlow field be mapped straight onto `status` / `priority`,
  so a form answer can set the evaluation → removed by **LG-5**.
- lodgely has two "qualified" notions (manual *Qualified* toggle, advisory AI
  qualification) → one qualification state owned by the rule engine in
  **LG-5**; AI stays advisory.
- The OpenFlow → lodgely interface is implicit and unversioned (no API
  version, offset-less timestamps, offset paging, webhook without submission
  id) → **X-1**.

### Decisions this roadmap builds on (confirmed 2026-10-06)

All five were confirmed on 2026-10-06 (OpenFlow 0.46.0 / lodgely 0.55.2).
Changing one is a roadmap change in both repos.

| # | Decision | Consequence |
|---|---|---|
| D1 | **No automatic feedback loop from lodgely into the session.** lodgely pulls/receives asynchronously, so its verdict can only drive *post-submit* actions (ad-platform conversions, notifications, operator actions). In-session gating (e.g. no booking for an obvious non-fit) is OpenFlow routing on single answers. | No lodgely → calon cancellation automation; cancelling a booking stays an operator action in calon. |
| D2 | **Push + pull.** A signed OpenFlow webhook delivers each submission to lodgely within seconds; the pull stays as hourly reconciliation (catches anything a push missed). | Webhook contract (X-1) and lodgely endpoint (LG-1) come first. |
| D3 | **Partial submissions only with explicit opt-in** at the contact step, short retention, imported by lodgely as `incomplete` and excluded from KPIs. | OF-8 depends on OF-9 (consent record). |
| D4 | **One OpenFlow + one lodgely per agency, many clients.** Clients log into lodgely only; OpenFlow stays operator-only. | OpenFlow needs team visibility (OF-12), not per-client tenancy; lodgely needs a real Client model (LG-4); full `tenant_id` multi-tenancy stays deferred. |
| D5 | **lodgely owns "qualified lead" conversions** to Google Ads / Meta. OpenFlow's own Google Ads / Meta CAPI integrations remain an optional "every lead" signal. | LG-7 depends on attribution travelling through the contract. |

### X-1 · OpenFlow submission contract v1

The one interface both repos build against. OpenFlow **produces** it (pull API
and webhook body are the same document); lodgely **consumes** it.

```jsonc
{
  "schema_version": 1,
  "event": "submission.created",        // | submission.partial | submission.completed | submission.deleted
  "submission_id": "uuid-v4",           // stable forever; the idempotency key
  "form": { "id": "uuid", "slug": "spring-launch", "title": "…", "revision": 12 },
  "submitted_at": "2026-09-29T10:00:00Z", // ISO 8601, always UTC with Z
  "status": "complete",                 // | partial
  "fields": [ { "key": "email", "id": "field_1727…", "type": "email", "label": "Your email" } ],
  "answers": { "email": "jane@example.com", "budget": "10–25k" }, // keyed by field *key*
  "attribution": {
    "utm_source": "google", "utm_medium": "cpc", "utm_campaign": "…", "utm_term": "…", "utm_content": "…",
    "gclid": "…", "gbraid": null, "wbraid": null, "fbc": "…", "fbp": "…",
    "landing_url": "https://…", "referrer": "https://…",
    "hidden": { "partner_id": "42" }   // operator-declared hidden fields
  },
  "consent": { "given": true, "text_hash": "sha256:…", "given_at": "2026-09-29T09:59:58Z" },
  "bookings": { "appointment": { "status": "booked", "booking_id": "…", "start": "2026-10-02T09:30:00+02:00" } },
  "files": [ { "field_key": "cv", "name": "cv.pdf", "size": 183422, "type": "application/pdf" } ]
}
```

- **IDs.** `submission_id` (UUID v4) is the only idempotency key.
  `form.id` never changes. Field **keys** are stable, human-readable and
  unique within a form (OF-1); the opaque field `id` is kept for back-compat
  only. lodgely maps by key and falls back to id.
- **Time.** Every timestamp is ISO 8601 UTC with `Z`. (Today OpenFlow emits
  offset-less SQLite strings. lodgely 0.55.0 parses them as UTC, which is the
  interim fix.)
- **Minimisation.** No IP, no user agent, no file contents in the contract.
  Files are listed as metadata only. IP/user agent stay in OpenFlow for the
  ad-platform integrations that need them.
- **Pull.** `GET /api/v1/forms/:id/submissions?cursor=<opaque>&limit=100`
  returns `{ schema_version, submissions: [...], next_cursor }`, ascending by
  `(submitted_at, submission_id)`. The cursor makes the walk resumable and
  gap-free (replaces today's newest-first offset paging with a 1-second tie
  problem).
- **Push.** `POST <lodgely endpoint>` with the same document.
  - Headers: `X-OpenFlow-Event`, `X-OpenFlow-Delivery` (uuid) and
    `X-OpenFlow-Signature: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">`.
  - Receivers reject signatures older than 5 minutes.
  - Delivery uses OpenFlow's existing retrying delivery queue.
- **Tokens.** Pull tokens are read-only and **scoped to named forms**
  (OF-4). Erasure uses a separate `erase` scope (X-2).
- **Versioning.**
  - Additive changes stay within v1. Consumers must ignore unknown keys.
  - A breaking change ships as v2, and v1 keeps being served for at least two
    OpenFlow minor releases.
  - `GET /api/v1/meta` returns `{ api_versions: [1], app_version }`, so
    lodgely can refuse an incompatible install with a clear message.
  - The legacy unversioned `/api/forms` and `/api/submissions` stay until
    lodgely no longer uses them.
- **Timing.**
  - Push: under 1 minute after submit (retries: 1/5/30/120 min).
  - Pull: hourly reconciliation.
  - A calon booking is already final in the payload (booked before storage;
    `pending` is resolved by OpenFlow's retry sweep and re-emitted as
    `submission.completed`).

### X-2 · Cross-system erasure & export (GDPR)

- A data-subject request is started **in lodgely** (the system the operator
  works in). lodgely finds every lead matching the person (normalized email /
  phone). It then:
  - exports or erases them locally;
  - calls OpenFlow `POST /api/v1/erasure` with a token scoped `erase` (by
    submission ids plus email/phone), receiving counts per form;
  - records one audit entry with both results.
- OpenFlow's side:
  - deletes submissions, delivery rows and backups' future copies;
  - emits `submission.deleted` for each, so any other consumer can follow;
  - cannot delete a calon booking. The erasure report lists `booking_id`s
    for the operator to cancel in calon.
- Retention runs independently in each system. OpenFlow can be set to delete
  a submission N days after lodgely has acknowledged it (OF-10), so the form
  builder isn't a second long-term lead store.

### Dependency map

```
X-1 contract ─┬─> OF-1 field keys ─┬─> OF-2 v1 pull API ──> LG-2 consume v1 (pull)
              │                    ├─> OF-3 signed webhook ─> LG-1 push endpoint
              │                    └─> OF-5 routing (conditions reference keys)
              ├─> OF-4 scoped tokens
              └─> OF-6 hidden fields/UTM ──> LG-6 attribution ──> LG-7 quality conversions
OF-9 consent record ──> OF-8 partials ──> LG-8 partial leads
LG-4 Client model ──> LG-5 qualification rules ──> LG-7, LG-10 notifications
X-2 erasure ──> OF-10 retention/erasure API + LG-12 DSAR
```

| Item | Repo | Blocks |
|---|---|---|
| X-1 contract v1 | both (spec) | OF-2, OF-3, OF-6, LG-1, LG-2, LG-6 |
| OF-1 stable field keys | OpenFlow | OF-2, OF-5, LG-2, LG-5 |
| OF-3 signed webhook v1 | OpenFlow | LG-1 |
| OF-6 hidden fields & UTM | OpenFlow | LG-6, LG-7 |
| OF-8 partial submissions | OpenFlow | LG-8 |
| OF-10 retention & erasure API | OpenFlow | LG-12 |
| LG-4 Client model | lodgely | LG-5, LG-9 |
| LG-5 qualification rules | lodgely | LG-7, LG-10 |
