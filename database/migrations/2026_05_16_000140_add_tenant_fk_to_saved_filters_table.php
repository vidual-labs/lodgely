<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original saved_filters table declared tenant_id as a plain
 * unsignedBigInteger, inconsistent with every other tenant-scoped table.
 * Replace it with a proper foreign key. All existing rows already point at
 * the default tenant (column default = 1) so the conversion is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_filters', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('saved_filters', function (Blueprint $table) {
            $table->foreignId('tenant_id')->default(1)->after('user_id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_filters', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['user_id', 'tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('saved_filters', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(1)->after('user_id');
            $table->index(['user_id', 'tenant_id']);
        });
    }
};
