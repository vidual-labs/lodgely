# Roadmap

## Up next

1. **Stronger compliance tooling** — lawful-basis tagging, DSAR export,
   one-click subject erasure.
2. **Multi-tenancy** — `tenant_id` exists everywhere; wire the full
   tenant-resolution stack so a single install can host many isolated
   workspaces.

## Completed

- ~~**Meta Lead Ads lead source (API)**~~ ✓ Done in v0.38.0 — `/imports/meta-leads` page where operators pull leads straight from the Meta Lead Ads Graph API (by Page ID or pinned Form ID), reusing the Meta connection from Settings → Ad platforms. Per-connection look-back / refresh interval, "Load forms" validation, "Fetch now", idempotent re-fetches keyed on the Meta lead id, and a `lodgely:meta-leads:fetch` hourly cron.
- ~~**Google Sheets lead source**~~ ✓ Done in v0.20.0 — `/imports/google-sheets` page where operators configure multiple sheets as recurring lead sources with per-column field mapping, per-sheet refresh interval, "Fetch now" button, and a `lodgely:google-sheets:fetch` hourly cron. OAuth credentials managed at `/settings/google-sheets` (done in v0.19.0).
- ~~**Password recovery + per-user profile page**~~ ✓ Done in v0.14.0 — public `/forgot-password` flow with rate-limited reset emails, operator "Reset link" action on the `/users` table, and a `/profile` page that lets every role manage their name, email, password, language and theme.
- ~~**Custom client report emails**~~ ✓ Done in v0.12.0 — `/reporting/emails` for composing modular templates (intro, KPI strip, monthly table, latest approved AI summary), send-now / one-off / weekly / monthly schedules, audited `client_report_email_sends` history, `lodgely:report-emails:dispatch` hourly cron.
- ~~**Custom client reporting views**~~ ✓ Done in v0.10.0 — `/reporting/views` for operators to define named views and assign per-client column sets, `/my-reports` per-client monthly time-series.
- ~~**AI summaries & lead qualification**~~ ✓ Done in v0.11.0 — `/settings/ai` for provider config (OpenAI-compatible or Ollama), `/ai/drafts` for operator review, report-view summaries and pseudonymized lead qualification with approve-then-share workflow.
- ~~**Reporting module**~~ ✓ Done in v0.9.0 — `/reporting` page with Meta + Google Ads mock adapters, `ad_spend_reports` table, campaign rollup, KPI cards.
- ~~**Bulk actions** in the inbox (mass-forward, mass-status).~~ ✓ Done in v0.7.0.
- ~~**Saved filters** and per-user view defaults.~~ ✓ Done in v0.7.0.
- ~~**Dark / Light mode** with OS-preference detection and manual toggle.~~ ✓ Done in v0.7.0.
- ~~**i18n** — English and German, per-user language preference persisted in DB.~~ ✓ Done in v0.7.0.

For line-by-line detail on every change since, see [CHANGELOG.md](../CHANGELOG.md).
