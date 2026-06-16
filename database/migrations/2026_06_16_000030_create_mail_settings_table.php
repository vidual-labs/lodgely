<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant outbound mail (SMTP) configuration. One row per tenant; lets an
 * operator point lodgely at their mail server from the UI instead of editing
 * .env, so reporting emails and password resets actually leave the box.
 *
 * `password_encrypted` is the ciphertext produced by Crypt::encryptString();
 * it is decrypted only when building the live mail config, never exposed to
 * the UI. When `enabled` is false the row is inert and the .env/config mail
 * settings stay authoritative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);          // use these settings over .env?
            $table->string('mailer', 16)->default('smtp');       // smtp | log
            $table->string('host', 255)->nullable();
            $table->integer('port')->nullable();
            $table->string('encryption', 8)->nullable();         // tls | ssl | none
            $table->string('username', 255)->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->timestamps();

            $table->unique('tenant_id', 'mail_settings_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
