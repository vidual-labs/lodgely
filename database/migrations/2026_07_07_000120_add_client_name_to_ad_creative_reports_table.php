<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creative-level companion to the ad_spend_reports client_name migration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_creative_reports', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('tenant_id');
        });

        Schema::table('ad_creative_reports', function (Blueprint $table) {
            $table->dropUnique('acr_unique_snapshot');
            $table->unique(
                ['tenant_id', 'client_name', 'platform', 'date', 'dimension', 'external_id'],
                'acr_unique_snapshot'
            );
            $table->index(['tenant_id', 'client_name'], 'acr_tenant_client');
        });
    }

    public function down(): void
    {
        Schema::table('ad_creative_reports', function (Blueprint $table) {
            $table->dropIndex('acr_tenant_client');
            $table->dropUnique('acr_unique_snapshot');
            $table->unique(
                ['tenant_id', 'platform', 'date', 'dimension', 'external_id'],
                'acr_unique_snapshot'
            );
        });

        Schema::table('ad_creative_reports', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }
};
