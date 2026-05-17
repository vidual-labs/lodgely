<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per dispatch attempt. This is the audit trail: who sent what,
 * to whom, covering which period, with which AI summary (if any) and
 * whether the queue job succeeded. Operators see the recent history on
 * the report-emails page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_report_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->foreignId('client_report_email_id')
                ->constrained('client_report_emails')
                ->cascadeOnDelete();
            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('client_report_email_schedules')
                ->nullOnDelete();
            $table->foreignId('triggered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->jsonb('recipient_user_ids');
            $table->foreignId('ai_summary_id')
                ->nullable()
                ->constrained('ai_summaries')
                ->nullOnDelete();
            $table->string('status', 16);                     // ReportEmailSendStatus value
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['client_report_email_id', 'created_at'], 'cres2_email_created');
            $table->index(['tenant_id', 'status'], 'cres2_tenant_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_report_email_sends');
    }
};
