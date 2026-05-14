<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();

            // --- identity / routing ----------------------------------------
            // The acquisition channel ("csv", "email_mock", "manual", future: "webhook", "meta_ads", ...).
            $table->string('source', 32);

            // Business label for the lead's recipient. For agencies: the agency's client.
            // For inhouse teams: a brand / location / product line. Free text on purpose.
            $table->string('client_name')->nullable();

            // Campaign / funnel / form name. Free text; kept normalized but not enforced.
            $table->string('campaign_name')->nullable();

            // --- contact ---------------------------------------------------
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Normalized helpers used for fast duplicate detection. Always written
            // by the LeadNormalizer service; never set directly by callers.
            $table->string('email_normalized')->nullable();
            $table->string('phone_normalized')->nullable();

            // --- payload ---------------------------------------------------
            $table->text('message')->nullable();
            $table->jsonb('raw_payload')->nullable();      // original source data, audit-friendly

            // --- workflow --------------------------------------------------
            $table->string('status', 16)->default('new');         // new|reviewed|incomplete|duplicate|forwarded
            $table->string('priority', 8)->default('medium');     // low|medium|high

            $table->boolean('duplicate_flag')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('leads')->nullOnDelete();

            // --- compliance ------------------------------------------------
            // Cleanup command honors this. Null = retain indefinitely (operator choice).
            $table->timestamp('retention_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes per product brief.
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'source']);
            $table->index(['tenant_id', 'client_name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority']);
            $table->index('email_normalized');
            $table->index('phone_normalized');
            $table->index('retention_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
