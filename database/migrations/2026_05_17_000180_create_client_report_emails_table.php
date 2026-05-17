<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-authored "report email" templates. Each row defines which
 * sections render, which ClientReportingView (if any) supplies the
 * metrics, and which Client users receive the email when dispatched.
 *
 * The template itself does not own a schedule — a separate
 * `client_report_email_schedules` row attaches a cadence so a template
 * can be sent ad-hoc, or one-off, or weekly, or monthly, without
 * duplicating the section/recipient setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_report_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('client_reporting_view_id')
                ->nullable()
                ->constrained('client_reporting_views')
                ->nullOnDelete();
            $table->text('intro_markdown')->nullable();
            $table->boolean('include_kpi_strip')->default(true);
            $table->boolean('include_metrics_table')->default(true);
            $table->boolean('include_ai_summary')->default(false);
            $table->unsignedSmallInteger('period_months')->default(1);
            $table->string('subject_template', 200)->default('Your {{period}} report');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'cre_tenant_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_report_emails');
    }
};
