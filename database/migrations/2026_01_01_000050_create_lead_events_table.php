<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight audit log scoped to lead lifecycle changes. Foundation for
 * GDPR-style "who changed what when" answers, but kept minimal in MVP:
 * we record event type, actor, and an optional small diff blob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // e.g. "lead.created", "lead.status_changed", "lead.priority_changed",
            //      "lead.note_added", "lead.imported", "lead.duplicate_flagged".
            $table->string('type', 48);
            $table->jsonb('payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
