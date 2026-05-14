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

## Commands worth knowing

```bash
php artisan migrate --seed                     # bootstrap a demo install
php artisan lodgely:user:create --role=client  # add a scoped client
php artisan lodgely:import:email-mock --count=5
php artisan lodgely:leads:purge --dry-run      # GDPR cleanup, preview only
```
