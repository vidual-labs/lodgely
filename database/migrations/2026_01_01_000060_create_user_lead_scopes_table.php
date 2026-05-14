<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user lead visibility scopes.
 *
 * Operators (agency / inhouse team) typically have NO rows here and see
 * everything. Client users have one or more rows, each binding them to a
 * client_name value — they can only see leads where leads.client_name
 * matches one of their scopes. Case-insensitive comparison is enforced
 * in the query scope, not at the DB layer, to keep this table simple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lead_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_name');
            $table->timestamps();

            $table->unique(['user_id', 'client_name']);
            $table->index('client_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lead_scopes');
    }
};
