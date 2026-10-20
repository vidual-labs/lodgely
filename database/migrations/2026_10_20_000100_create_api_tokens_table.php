<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only API tokens for other tools (vigilo first, LG-15). Only a SHA-256
 * hash of the token is stored; the plaintext is shown once at creation. The
 * prefix is the first few characters, kept so operators can tell tokens
 * apart without the secret.
 *
 * The leads index backs the lead-metadata change feed, which pages through
 * one tenant's leads ordered by (updated_at, id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 16);
            $table->json('scopes');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index(['tenant_id', 'updated_at', 'id'], 'leads_tenant_updated_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_updated_at_id_index');
        });

        Schema::dropIfExists('api_tokens');
    }
};
