<?php

/*
|--------------------------------------------------------------------------
| lodgely product configuration
|--------------------------------------------------------------------------
|
| Centralized, opinionated knobs for the lodgely product. Anything that
| might reasonably be tweaked per deployment lives here, so the rest of
| the codebase can read settings via config('lodgely.*') instead of
| reading env() directly.
|
*/

return [

    // Read once from composer.json so the package version stays the single
    // source of truth (the every-commit checklist already bumps it there).
    'version' => (function () {
        $composer = base_path('composer.json');
        if (! is_file($composer)) {
            return 'dev';
        }
        $data = json_decode((string) file_get_contents($composer), true);

        return $data['version'] ?? 'dev';
    })(),

    'brand' => [
        'name' => env('LODGELY_BRAND_NAME', 'lodgely'),
        'tagline' => env('LODGELY_BRAND_TAGLINE', 'Lead intake, unified.'),
        // Hardcoded on purpose: lodgely is GPL-3.0, and the footer link to the
        // upstream source repository is part of preserving attribution. Forks may
        // edit this value in their own copy, but it must not be a deploy-time toggle.
        'github_url' => 'https://github.com/vidual-labs/lodgely',
    ],

    'importers' => [
        'csv' => [
            'max_rows' => (int) env('LODGELY_CSV_MAX_ROWS', 10000),
        ],
        'google_sheets' => [
            // OAuth installed-application credentials for the Google Sheets API.
            // Operators run the in-app authorize flow at /settings/google-sheets/connect
            // to exchange a one-time code for a long-lived refresh token, then
            // paste it back into LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN.
            'client_id' => env('LODGELY_GOOGLE_SHEETS_CLIENT_ID', ''),
            'client_secret' => env('LODGELY_GOOGLE_SHEETS_CLIENT_SECRET', ''),
            'refresh_token' => env('LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN', ''),
            // OAuth scopes requested during the authorize flow. Read-only is
            // enough for both ad-hoc data fetches and the planned leads source.
            'scopes' => [
                'https://www.googleapis.com/auth/spreadsheets.readonly',
            ],
            // HTTP timeout (seconds) for outbound calls to Google APIs.
            'http_timeout_sec' => (int) env('LODGELY_GOOGLE_SHEETS_HTTP_TIMEOUT', 30),
        ],
        'email' => [
            // 'mock' generates simulated leads; 'imap' connects to a real mailbox.
            'driver' => env('LODGELY_EMAIL_IMPORT_DRIVER', 'mock'),
            'imap' => [
                'host' => env('LODGELY_IMAP_HOST', ''),
                'port' => (int) env('LODGELY_IMAP_PORT', 993),
                'encryption' => env('LODGELY_IMAP_ENCRYPTION', 'ssl'), // ssl | tls | notls
                'validate_cert' => (bool) env('LODGELY_IMAP_VALIDATE_CERT', true),
                'username' => env('LODGELY_IMAP_USERNAME', ''),
                'password' => env('LODGELY_IMAP_PASSWORD', ''),
                'mailbox' => env('LODGELY_IMAP_MAILBOX', 'INBOX'),
                'max_messages' => (int) env('LODGELY_IMAP_MAX_MESSAGES', 50),
                'default_client_name' => env('LODGELY_IMAP_DEFAULT_CLIENT', ''),
                'default_campaign_name' => env('LODGELY_IMAP_DEFAULT_CAMPAIGN', ''),
            ],
        ],
    ],

    'compliance' => [
        // Default retention window in days for newly created leads.
        // null = retain until manually deleted. The cleanup command is opt-in.
        'default_retention_days' => env('LODGELY_DEFAULT_RETENTION_DAYS') !== null && env('LODGELY_DEFAULT_RETENTION_DAYS') !== ''
            ? (int) env('LODGELY_DEFAULT_RETENTION_DAYS')
            : null,
    ],

    'pagination' => [
        'per_page' => 25,
    ],

    'reporting' => [
        // Comma-separated list of ad metrics source keys to run on schedule
        // and via `php artisan lodgely:import:ad-metrics`.
        // Available keys: meta_mock, google_mock, meta, google.
        // Default keeps the mock adapters active for demo installs; swap to
        // 'meta,google' once API credentials below are filled in.
        'sources' => explode(',', env('LODGELY_AD_METRICS_SOURCES', 'meta_mock,google_mock')),

        // HTTP timeout (seconds) for outbound calls to ad platform APIs.
        'http_timeout_sec' => (int) env('LODGELY_AD_METRICS_HTTP_TIMEOUT', 30),

        // Meta (Facebook/Instagram) Marketing API credentials. Only consulted
        // when the `meta` key is in the `sources` list above.
        'meta' => [
            'access_token' => env('LODGELY_META_ADS_ACCESS_TOKEN', ''),
            'ad_account_id' => env('LODGELY_META_ADS_ACCOUNT_ID', ''),
            'api_version' => env('LODGELY_META_ADS_API_VERSION', 'v21.0'),
            // Meta returns spend in account currency; we store cents and a
            // currency code, so set this to whatever the ad account is set to.
            'currency' => env('LODGELY_META_ADS_CURRENCY', 'USD'),
        ],

        // Google Ads REST API credentials. Only consulted when the `google`
        // key is in the `sources` list above. Requires an approved developer
        // token plus an OAuth installed-app refresh token.
        'google' => [
            'client_id' => env('LODGELY_GOOGLE_ADS_CLIENT_ID', ''),
            'client_secret' => env('LODGELY_GOOGLE_ADS_CLIENT_SECRET', ''),
            'refresh_token' => env('LODGELY_GOOGLE_ADS_REFRESH_TOKEN', ''),
            'developer_token' => env('LODGELY_GOOGLE_ADS_DEVELOPER_TOKEN', ''),
            // Required when the OAuth user authenticates via an MCC/manager
            // account; leave blank for direct (single-customer) auth.
            'login_customer_id' => env('LODGELY_GOOGLE_ADS_LOGIN_CUSTOMER_ID', ''),
            'customer_id' => env('LODGELY_GOOGLE_ADS_CUSTOMER_ID', ''),
            'api_version' => env('LODGELY_GOOGLE_ADS_API_VERSION', 'v18'),
        ],
    ],

    'ai' => [
        // Master kill-switch. When false, all AI routes 404, buttons are hidden,
        // and queued jobs no-op. Per-tenant `enabled` toggles only matter when
        // this is true.
        'enabled' => (bool) env('LODGELY_AI_ENABLED', false),

        // Maximum number of *completed* AI generations per tenant per day.
        // Set to 0 to disable the cap.
        'max_calls_per_day' => (int) env('LODGELY_AI_MAX_CALLS_PER_DAY', 100),

        // HTTP timeout for a single provider call, in seconds.
        'request_timeout_sec' => (int) env('LODGELY_AI_TIMEOUT', 60),

        // Per-provider defaults, used when an admin leaves the base URL or
        // model blank on the AI settings page.
        'defaults' => [
            'openai_compatible' => [
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
            ],
            'ollama' => [
                'base_url' => 'http://localhost:11434',
                'model' => 'llama3.1',
            ],
        ],
    ],
];
