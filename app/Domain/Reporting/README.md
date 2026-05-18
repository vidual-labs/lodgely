# Domain · Reporting (reserved, not in MVP)

This folder is a deliberate placeholder. The MVP does not implement
reporting features, but the architectural seam is reserved here so future
work lands in one obvious place.

Planned scope (post-MVP):

- ~~Adapters for Meta Ads and Google Ads, sparingly fetching aggregate
  campaign / source data only (no raw user-level data).~~ Done — see
  `app/Importers/Meta/MetaAdsSource` (Marketing API insights) and
  `app/Importers/Google/GoogleAdsSource` (REST `googleAds:search`).
  Mocks still ship alongside for demo installs; the active set is
  controlled by `LODGELY_AD_METRICS_SOURCES`.
- A small `reports` table (campaign, source, date, metrics) with retention
  shorter than operational lead data by default.
- Read-only Livewire views surfacing the rollups next to the inbox KPIs.

Compliance intent: reporting data is processed separately from
operational lead data. Adapters should pull aggregated metrics, never
personally identifying information.
