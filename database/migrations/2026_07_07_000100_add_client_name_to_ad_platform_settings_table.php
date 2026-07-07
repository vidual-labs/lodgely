<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a tenant configure more than one Meta / Google Ads connector and
 * assign each to a client (matching the `client_name` string convention
 * already used by `leads.client_name` / `user_lead_scopes.client_name`).
 * `client_name IS NULL` stays the existing single "default" connector so
 * current single-connection installs are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('tenant_id');
        });

        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->dropUnique('ad_platform_settings_tenant_unique');
            $table->unique(['tenant_id', 'client_name'], 'ad_platform_settings_tenant_client_unique');
        });

        // A composite unique constraint treats NULLs as distinct in Postgres,
        // so it alone would allow more than one "default" (client_name NULL)
        // row per tenant. Guard that separately with a partial index.
        DB::statement(
            'CREATE UNIQUE INDEX ad_platform_settings_default_unique '.
            'ON ad_platform_settings (tenant_id) WHERE client_name IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ad_platform_settings_default_unique');

        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->dropUnique('ad_platform_settings_tenant_client_unique');
        });

        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });

        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->unique('tenant_id', 'ad_platform_settings_tenant_unique');
        });
    }
};
