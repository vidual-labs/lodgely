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

    'brand' => [
        'name'    => env('LODGELY_BRAND_NAME', 'lodgely'),
        'tagline' => env('LODGELY_BRAND_TAGLINE', 'Lead intake, unified.'),
    ],

    'importers' => [
        'csv' => [
            'max_rows' => (int) env('LODGELY_CSV_MAX_ROWS', 10000),
        ],
        'email' => [
            // 'mock' generates simulated leads; 'imap' connects to a real mailbox.
            'driver' => env('LODGELY_EMAIL_IMPORT_DRIVER', 'mock'),
            'imap' => [
                'host'                  => env('LODGELY_IMAP_HOST', ''),
                'port'                  => (int) env('LODGELY_IMAP_PORT', 993),
                'encryption'            => env('LODGELY_IMAP_ENCRYPTION', 'ssl'), // ssl | tls | notls
                'validate_cert'         => (bool) env('LODGELY_IMAP_VALIDATE_CERT', true),
                'username'              => env('LODGELY_IMAP_USERNAME', ''),
                'password'              => env('LODGELY_IMAP_PASSWORD', ''),
                'mailbox'               => env('LODGELY_IMAP_MAILBOX', 'INBOX'),
                'max_messages'          => (int) env('LODGELY_IMAP_MAX_MESSAGES', 50),
                'default_client_name'   => env('LODGELY_IMAP_DEFAULT_CLIENT', ''),
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
        // Comma-separated list of ad metrics source keys to run on schedule.
        // Available: meta_mock, google_mock. Replace with real adapters when API keys are configured.
        'sources' => explode(',', env('LODGELY_AD_METRICS_SOURCES', 'meta_mock,google_mock')),
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
                'model'    => 'gpt-4o-mini',
            ],
            'ollama' => [
                'base_url' => 'http://localhost:11434',
                'model'    => 'llama3.1',
            ],
        ],
    ],
];
