<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user picked column set for the inbox table. Shape:
            //   { "columns": ["name","email","phone",...], "questions": ["Event size",...] }
            // Null = use role-based default (see WithColumnPicker).
            $table->jsonb('inbox_columns')->nullable()->after('ui_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('inbox_columns');
        });
    }
};
