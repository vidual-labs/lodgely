# Configuration reference

Full environment variable reference behind the
[README's quick-start table](../README.md#quick-start-docker). Most of these
have an equivalent in-app Settings page — env vars exist as a fallback for
headless / scripted installs.

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_NAME` | Display name in titles/headers | `lodgely` |
| `APP_URL`  | Public URL of the install | `http://localhost:8080` |
| `LODGELY_BRAND_NAME` / `LODGELY_BRAND_TAGLINE` | Optional white-label-ish strings (still under the lodgely identity) | `lodgely` / `Lead intake, unified.` |
| `LODGELY_CSV_MAX_ROWS` | Hard cap on rows ingested per CSV | `10000` |
| `LODGELY_EMAIL_IMPORT_DRIVER` | `mock` or `imap` | `mock` |
| `LODGELY_EMAIL_MOCK_SCHEDULE` | Enable the daily 06:00 scheduled pull of fake demo leads (manual command/UI still work either way) | `false` |
| `LODGELY_IMAP_HOST` | IMAP server hostname (activates real email backend) | — |
| `LODGELY_IMAP_PORT` | IMAP port | `993` |
| `LODGELY_IMAP_ENCRYPTION` | `ssl`, `tls`, or `notls` | `ssl` |
| `LODGELY_IMAP_USERNAME` / `LODGELY_IMAP_PASSWORD` | Mailbox credentials | — |
| `LODGELY_IMAP_MAILBOX` | Folder to poll | `INBOX` |
| `LODGELY_IMAP_MAX_MESSAGES` | Max unseen messages per pull | `50` |
| `LODGELY_DEFAULT_RETENTION_DAYS` | Default lead retention, empty = retain | `365` |
| `TRUSTED_PROXIES` | Proxy address/CIDR (comma-separated) trusted for `X-Forwarded-*` headers. The default trusts every proxy, which makes the reported client IP whatever the caller sends — set it to your reverse proxy's range on an internet-facing install. Login and password-reset throttling is keyed on the submitted email as well as the IP, so it does not depend on this being correct. | `*` |
| `LODGELY_BACKUP_PASSPHRASE` | Encrypts the database dump inside **new** backup archives (AES-256-GCM, PBKDF2-SHA256). A backup otherwise holds every lead's name, email, phone and message body in cleartext. Archives created before this was set still restore normally — the manifest records which shape each archive is. Store the passphrase off this server; an encrypted archive cannot be restored without it. | — (off) |
| `LODGELY_BACKUP_KEEP` | How many backup archives to keep on disk after each new one. Empty keeps every archive forever. The archive just created is never pruned. | — (keep all) |
| `LODGELY_AI_ENABLED` | Master kill-switch for the AI module. When `false`, all AI routes 404, buttons are hidden, and jobs no-op. Per-tenant config at `/settings/ai` only matters when this is true. | `false` |
| `LODGELY_AI_MAX_CALLS_PER_DAY` | Maximum completed AI generations per tenant per day. `0` disables the cap. | `100` |
| `LODGELY_AI_TIMEOUT` | HTTP timeout (seconds) for a single LLM provider call. | `60` |
| `LODGELY_AD_METRICS_SOURCES` | Comma-separated list of ad source adapters to activate. Available: `meta_mock`, `google_mock`, `meta`, `google`. The live `meta` / `google` adapters are normally switched on via the **Enable** toggles in Settings → Ad platforms (which are added to this list at runtime); set them here only if you prefer env-based activation. The `*_mock` demo adapters are automatically dropped once any real platform is connected through the UI, so live reporting never mixes in fabricated demo campaigns. | `meta_mock,google_mock` |
| `LODGELY_AD_METRICS_HTTP_TIMEOUT` | HTTP timeout (seconds) for outbound ad platform API calls. | `30` |
| `LODGELY_AD_METRICS_BACKFILL_DAYS` | How many days the reporting page's **Fetch data now** button backfills in one go (the daily scheduler only pulls yesterday). Each day is a separate API call per source, so large windows make the synchronous fetch slow. | `30` |
| `LODGELY_META_ADS_ACCESS_TOKEN` | Meta Marketing API long-lived (system-user) access token. Optional — prefer Settings → Ad platforms; used only as a fallback when not set there. | — |
| `LODGELY_META_ADS_ACCOUNT_ID` | Meta ad account id, with or without the `act_` prefix. | — |
| `LODGELY_META_ADS_API_VERSION` | Graph API version. | `v21.0` |
| `LODGELY_META_ADS_CURRENCY` | Currency code matching the ad account currency (lodgely stores cents + this code). | `USD` |
| `LODGELY_GOOGLE_ADS_CLIENT_ID` / `LODGELY_GOOGLE_ADS_CLIENT_SECRET` / `LODGELY_GOOGLE_ADS_REFRESH_TOKEN` | OAuth web-application credentials. Optional — prefer Settings → Ad platforms (the "Connect Google Ads" button captures the refresh token for you); used only as a fallback. | — |
| `LODGELY_GOOGLE_ADS_DEVELOPER_TOKEN` | Approved Google Ads API developer token. | — |
| `LODGELY_GOOGLE_ADS_LOGIN_CUSTOMER_ID` | Manager (MCC) account id. Set only when the OAuth user authenticates via a manager account. | — |
| `LODGELY_GOOGLE_ADS_CUSTOMER_ID` | Target Google Ads account id (digits only or hyphenated). | — |
| `LODGELY_GOOGLE_ADS_API_VERSION` | Google Ads REST API version. | `v18` |
| `LODGELY_GOOGLE_SHEETS_CLIENT_ID` / `LODGELY_GOOGLE_SHEETS_CLIENT_SECRET` / `LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN` | Legacy env-based fallback for Google Sheets OAuth credentials. Prefer the in-app settings page at `/settings/google-sheets` — credentials entered there are stored encrypted in the DB and take precedence over these env vars. | — |
| `LODGELY_GOOGLE_SHEETS_HTTP_TIMEOUT` | HTTP timeout (seconds) for outbound calls to Google Sheets / OAuth endpoints. | `30` |
| `LODGELY_OPENFLOW_HTTP_TIMEOUT` | HTTP timeout (seconds) for outbound calls to an OpenFlow install (login + form/submission fetches). Per-source base URL and login are configured in-app at `/imports/openflow`. | `30` |
| `MAIL_MAILER` | Outbound mail transport: `log` (writes to the log, does not send), `smtp`, etc. Optional — prefer **Settings → Email**; settings saved there are stored encrypted and override these at runtime. Used as a fallback when the UI toggle is off. | `log` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP server + credentials (when `MAIL_MAILER=smtp`). Prefer Settings → Email. | — |
| `MAIL_SCHEME` | `smtp` (STARTTLS, port 587) or `smtps` (implicit TLS, port 465). | — |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Default From identity on outgoing mail. | `no-reply@lodgely.local` / `${APP_NAME}` |
| `DB_*` | Postgres credentials | see `.env.example` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | All default to `database` | — |
