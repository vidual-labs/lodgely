<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant AI provider configuration. One row per tenant; the global
 * kill-switch lives in config (LODGELY_AI_ENABLED) independently of
 * tenant-level `enabled`.
 *
 * `api_key_encrypted` is the ciphertext produced by Crypt::encryptString();
 * it is decrypted only inside the provider adapter, never exposed to the
 * UI or audit payloads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 32)->nullable();          // matches AppServiceProvider::LLM_PROVIDERS key
            $table->string('base_url', 255)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->string('model', 120)->nullable();
            $table->text('house_style')->nullable();             // free-text guidance from the admin
            $table->jsonb('kinds_enabled')->nullable();          // {report_view: bool, lead_qualification: bool}
            $table->boolean('lead_data_consent')->default(false);// must be true before any lead kind can fire
            $table->decimal('temperature', 3, 2)->nullable();
            $table->timestamps();

            $table->unique('tenant_id', 'ai_settings_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
