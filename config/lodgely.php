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
            // 'mock' generates simulated incoming emails; 'imap' is planned post-MVP.
            'driver' => env('LODGELY_EMAIL_IMPORT_DRIVER', 'mock'),
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
];
