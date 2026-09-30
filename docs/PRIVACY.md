# Privacy & GDPR notes for self-hosters

lodgely is built with privacy-by-design defaults, but **you, the operator,
are the data controller** for everything you put in it. The product gives
you the tools; the policies are yours.

- **Data minimization.** Only the lead fields in the schema are stored.
  Raw CSV rows / mock email bodies are kept in `raw_payload` for audit but
  are never displayed in summary views.
- **Retention.** Every lead has a `retention_until` column, set when the
  lead is ingested from `LODGELY_DEFAULT_RETENTION_DAYS` (empty in code =
  keep forever; `.env.example` ships `365`). The `lodgely:leads:purge`
  command runs daily and **soft-deletes** leads past their date. Be aware
  that a soft-deleted lead still holds its name, email, phone, message,
  custom answers and `raw_payload`, and its notes and audit events stay too
  — purge hides the lead, it does not erase it yet. Real erasure (hard
  delete / anonymisation after a grace period) is on the
  [roadmap](../ROADMAP.md).
- **Soft deletes** on `leads` and `lead_notes` mean an accidental delete is
  reversible until you hard-delete in the DB.
- **Audit trail.** `lead_events` records create/update/note actions with
  actor and timestamp.
- **Access scoping.** Client users see only their own `client_name`'s
  leads, enforced both in queries and in mutations.
- **Phone / email normalization** is for duplicate detection only; the
  original values remain visible to operators.
- **No telemetry, no external calls** out of the box. lodgely does not
  phone home.
- **HTTPS.** Use the Caddyfile's TLS-on-real-hostname mode for production.

What this product does **not** do for you (yet, on purpose):
consent capture, data-subject access reports, automatic right-to-erasure
workflow, lawful-basis tagging. Those belong to a future compliance module
and are listed in the [roadmap](../ROADMAP.md).
