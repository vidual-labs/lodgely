# Domain · Reporting

**Built** — no longer a reserved seam. Ad-spend + creative metrics ingestion
(`MetricsIngestor`, `AdMetricsSource` / `CreativeMetricsSource` adapters),
campaign rollups (`CampaignRollup`), client reporting views and scheduled
report emails all live here; see `docs/FEATURES.md` and the root
`ROADMAP.md` for what's next (per-lead attribution for non-Meta sources).

Original scope, kept for history:

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
