<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attaches a cadence to a `client_report_emails` template. `next_run_at`
 * is the source of truth for the dispatcher; the day/hour/timezone
 * columns are how the operator authored the schedule. `day_of_month` is
 * capped at 28 by validation so we never trip month-end edge cases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_report_email_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_report_email_id')
                ->constrained('client_report_emails')
                ->cascadeOnDelete();
            $table->string('cadence', 16);                    // ReportEmailCadence value
            $table->unsignedTinyInteger('day_of_week')->nullable();   // 0-6 (Sun..Sat, Carbon default)
            $table->unsignedTinyInteger('day_of_month')->nullable();  // 1-28
            $table->unsignedTinyInteger('hour')->default(9);  // 0-23
            $table->string('timezone', 64)->default('UTC');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_run_at'], 'cres_due');
            $table->index('client_report_email_id', 'cres_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_report_email_schedules');
    }
};
