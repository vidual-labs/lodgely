<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafted AI output awaiting operator review. One row per generation
 * attempt. `prompt` and `response` are kept verbatim — the prompt is what
 * actually went to the model (post-pseudonymization for lead kinds) so
 * the operator can audit what was disclosed.
 *
 * `subject_type` / `subject_id` is a polymorphic reference to the
 * thing being summarized (a ClientReportingView for report_view, a Lead
 * for lead_qualification). Both are nullable so future aggregate kinds
 * (digests) can omit them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);                         // AiSummaryKind value
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('prompt');
            $table->text('response')->nullable();
            $table->string('model', 120)->nullable();
            $table->string('provider', 32)->nullable();
            $table->jsonb('token_usage')->nullable();
            $table->string('status', 16);                       // AiSummaryStatus value
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'status'], 'ai_summaries_tenant_kind_status');
            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'ai_summaries_tenant_subject');
            $table->index(['tenant_id', 'created_at'], 'ai_summaries_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
