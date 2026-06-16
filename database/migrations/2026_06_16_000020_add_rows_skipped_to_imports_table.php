<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Rows that were already present (recognized via external_id) and so
            // were not re-created. Keeps the import summary honest for idempotent
            // recurring sources like Google Sheets.
            $table->unsignedInteger('rows_skipped')->default(0)->after('rows_invalid');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('rows_skipped');
        });
    }
};
