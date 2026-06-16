<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Stable per-row identifier supplied by recurring importers (e.g. the
            // Google Sheets content fingerprint). Lets the ingestor recognize a
            // row it has already seen and skip it instead of creating a duplicate.
            // Intentionally NOT a unique constraint: idempotency is enforced in the
            // application so soft-deleted rows never block a legitimate re-insert.
            $table->string('external_id')->nullable()->after('source');

            $table->index(['tenant_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
