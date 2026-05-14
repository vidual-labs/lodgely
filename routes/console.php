<?php

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
