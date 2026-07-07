<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a per-client connector be scoped to one brand within an ad account
 * that actually serves several (e.g. two businesses sharing one Google Ads
 * or Meta account). Matching is always by platform ID, never by the
 * customer-facing name — Page/asset names can be edited by whoever manages
 * the account, IDs are permanent. `*_name` columns are display-only, cached
 * the moment the operator resolves/saves the ID so the settings page never
 * has to make a live lookup just to render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->string('meta_page_id')->nullable()->after('meta_access_token_encrypted');
            $table->string('meta_page_name')->nullable()->after('meta_page_id');

            $table->string('google_business_name_asset_id')->nullable()->after('google_developer_token_encrypted');
            $table->string('google_business_name_asset_name')->nullable()->after('google_business_name_asset_id');

            // Operator-facing only — never sent to Meta/Google, never used for matching.
            $table->string('internal_label')->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('ad_platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meta_page_id',
                'meta_page_name',
                'google_business_name_asset_id',
                'google_business_name_asset_name',
                'internal_label',
            ]);
        });
    }
};
