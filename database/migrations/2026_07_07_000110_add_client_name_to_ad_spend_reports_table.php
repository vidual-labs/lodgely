<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags each ad-spend snapshot with the client connector it was fetched
 * from (NULL = the shared/default connector), so reporting can scope
 * spend to a single client once an operator connects a dedicated
 * Meta/Google account for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_spend_reports', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('tenant_id');
        });

        Schema::table('ad_spend_reports', function (Blueprint $table) {
            $table->dropUnique('asr_unique_snapshot');
            $table->unique(
                ['tenant_id', 'client_name', 'platform', 'date', 'campaign_id'],
                'asr_unique_snapshot'
            );
            $table->index(['tenant_id', 'client_name'], 'asr_tenant_client');
        });
    }

    public function down(): void
    {
        Schema::table('ad_spend_reports', function (Blueprint $table) {
            $table->dropIndex('asr_tenant_client');
            $table->dropUnique('asr_unique_snapshot');
            $table->unique(['tenant_id', 'platform', 'date', 'campaign_id'], 'asr_unique_snapshot');
        });

        Schema::table('ad_spend_reports', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }
};
