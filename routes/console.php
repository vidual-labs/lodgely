<?php

use App\Console\Commands\DispatchScheduledReportEmails;
use App\Console\Commands\FetchGoogleSheets;
use App\Console\Commands\FetchMetaLeads;
use App\Console\Commands\ImportAdMetrics;
use App\Console\Commands\ImportEmailsImap;
use App\Console\Commands\ImportEmailsMock;
use App\Console\Commands\PurgeExpiredLeads;
use Illuminate\Support\Facades\Schedule;

// Daily mock email pull — useful for demos and dev.
Schedule::command(ImportEmailsMock::class)->dailyAt('06:00')->withoutOverlapping();

// Scheduled IMAP pull — only runs when a host is configured.
if (config('lodgely.importers.email.imap.host')) {
    Schedule::command(ImportEmailsImap::class)->everyFifteenMinutes()->withoutOverlapping();
}

// GDPR-friendly cleanup pass; only acts on leads with a retention_until in the past.
Schedule::command(PurgeExpiredLeads::class)->dailyAt('03:00');

// Daily ad metrics pull — fetches yesterday's aggregate spend data from all configured sources.
Schedule::command(ImportAdMetrics::class, ['--days=1'])->dailyAt('05:00')->withoutOverlapping();

// Hourly sweep that fires any due report-email schedules. Schedules carry their own
// hour-of-day and timezone, so hourly granularity is sufficient — minute is always 0.
Schedule::command(DispatchScheduledReportEmails::class)->hourly()->withoutOverlapping();

// Hourly pass over active Google Sheet sources; each source decides internally whether
// it is due (based on its own refresh_hours interval).
Schedule::command(FetchGoogleSheets::class)->hourly()->withoutOverlapping();

// Hourly pass over active Meta Lead Ads connections; each source decides internally
// whether it is due (based on its own refresh_hours interval).
Schedule::command(FetchMetaLeads::class)->hourly()->withoutOverlapping();
