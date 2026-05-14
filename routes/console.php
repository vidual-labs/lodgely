<?php

use App\Console\Commands\ImportEmailsMock;
use App\Console\Commands\PurgeExpiredLeads;
use Illuminate\Support\Facades\Schedule;

// Daily mock email pull — disabled by default, useful for demos and dev.
Schedule::command(ImportEmailsMock::class)->dailyAt('06:00')->withoutOverlapping();

// GDPR-friendly cleanup pass; only acts on leads with a retention_until in the past.
Schedule::command(PurgeExpiredLeads::class)->dailyAt('03:00');
