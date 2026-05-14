<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub table for future multi-tenancy. The MVP runs as a single tenant
 * (id = 1) but every domain table carries tenant_id so that adding row-level
 * scoping later is a no-op data-wise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
