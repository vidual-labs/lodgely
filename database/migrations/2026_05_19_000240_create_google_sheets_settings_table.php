<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheets_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 255)->default('');
            $table->text('client_secret_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamps();

            $table->unique('tenant_id', 'google_sheets_settings_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_settings');
    }
};
