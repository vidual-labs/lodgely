<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openflow_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->string('label');
            // OpenFlow install base URL, e.g. https://forms.example.com
            $table->string('base_url');
            // Login credentials — OpenFlow has no API token, only JWT-via-login.
            // The password is stored as Laravel-encrypted ciphertext.
            $table->string('email');
            $table->text('password_encrypted')->nullable();
            // Which OpenFlow form (UUID) to pull, plus its title for display.
            $table->string('form_id');
            $table->string('form_name')->nullable();
            // {openflow_field_id: lead_field_key} e.g. {"a1b2c3":"email","x9y8":"full_name"}
            $table->jsonb('field_map')->nullable();
            $table->string('default_client_name')->nullable();
            $table->string('default_campaign_name')->nullable();
            $table->unsignedSmallInteger('refresh_hours')->default(24);
            $table->timestamp('last_fetched_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openflow_sources');
    }
};
