<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits OpenFlow's fetch clock in two.
 *
 * `last_fetched_at` was doing two unrelated jobs: throttling the hourly
 * scheduler (isDue()) *and* acting as the high-water mark that bounds how far
 * back a pull walks. The scheduler advances it on every attempt — including
 * failures, deliberately, so a broken source doesn't get retried every hour —
 * which silently moved the data cutoff past submissions that were never
 * actually ingested.
 *
 * `last_successful_fetch_at` now carries the data cutoff, and only a
 * completed pull advances it. Backfilled from `last_fetched_at` so existing
 * installs don't re-walk their whole backlog on the next run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('openflow_sources', function (Blueprint $table) {
            $table->timestamp('last_successful_fetch_at')->nullable()->after('last_fetched_at');
        });

        DB::table('openflow_sources')->update([
            'last_successful_fetch_at' => DB::raw('last_fetched_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('openflow_sources', function (Blueprint $table) {
            $table->dropColumn('last_successful_fetch_at');
        });
    }
};
