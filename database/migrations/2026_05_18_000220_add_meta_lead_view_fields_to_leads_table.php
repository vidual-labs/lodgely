<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Meta Lead Ads "custom questions" — array of {question, answer}
            // pairs as they came back from the form. JSONB so each install
            // can host whatever shape the upstream form vendor used.
            $table->jsonb('custom_answers')->nullable()->after('raw_payload');

            // Client-driven outreach state. Nullable timestamps double as
            // both "did this happen?" and "when did it happen?" — toggling
            // off clears the timestamp.
            $table->timestamp('qualified_at')->nullable()->after('retention_until');
            $table->timestamp('called_at')->nullable()->after('qualified_at');
            $table->timestamp('mailed_at')->nullable()->after('called_at');

            $table->index(['tenant_id', 'qualified_at'], 'leads_tenant_qualified_at_index');
            $table->index(['tenant_id', 'called_at'], 'leads_tenant_called_at_index');
            $table->index(['tenant_id', 'mailed_at'], 'leads_tenant_mailed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_qualified_at_index');
            $table->dropIndex('leads_tenant_called_at_index');
            $table->dropIndex('leads_tenant_mailed_at_index');

            $table->dropColumn(['custom_answers', 'qualified_at', 'called_at', 'mailed_at']);
        });
    }
};
