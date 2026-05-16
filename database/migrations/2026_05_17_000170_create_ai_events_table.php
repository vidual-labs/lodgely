<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for AI summary lifecycle. Sibling of `lead_events` — kept
 * separate because ai_summaries are not always attached to a lead, and
 * we don't want to retrofit a polymorphic `subject_type` onto the
 * heavily-used lead_events table.
 *
 * payload is jsonb; AiAuditLogger redacts api_key / authorization /
 * bearer-shaped keys before writing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_summary_id')->nullable()->constrained('ai_summaries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 48);
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'ai_events_tenant_created');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_events');
    }
};
