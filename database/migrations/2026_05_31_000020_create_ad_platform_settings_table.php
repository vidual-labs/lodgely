<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant Meta Ads + Google Ads API credentials, configured entirely
 * through the /settings/ad-platforms UI so operators never have to edit
 * .env. Secret columns hold Laravel-encrypted ciphertext. Env vars stay
 * supported as a fallback (see AdPlatformSetting::effective* getters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_platform_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Meta (Facebook/Instagram) Marketing API.
            $table->boolean('meta_enabled')->default(false);
            $table->string('meta_ad_account_id')->default('');
            $table->string('meta_currency', 8)->default('USD');
            $table->string('meta_api_version', 16)->default('v21.0');
            $table->text('meta_access_token_encrypted')->nullable();

            // Google Ads REST API.
            $table->boolean('google_enabled')->default(false);
            $table->string('google_customer_id')->default('');
            $table->string('google_login_customer_id')->default('');
            $table->string('google_api_version', 16)->default('v18');
            $table->string('google_client_id')->default('');
            $table->text('google_client_secret_encrypted')->nullable();
            $table->text('google_refresh_token_encrypted')->nullable();
            $table->text('google_developer_token_encrypted')->nullable();

            $table->timestamps();

            $table->unique('tenant_id', 'ad_platform_settings_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_platform_settings');
    }
};
