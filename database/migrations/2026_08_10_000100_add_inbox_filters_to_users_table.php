<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user picked *filter dropdown* set for the inbox toolbar — which
            // of Status / Priority / Source / Outreach show up, distinct from
            // inbox_columns (which table columns render). Shape: ["status",
            // "priority", "outreach"]. Null = use the default set (see
            // WithFilterPicker).
            $table->jsonb('inbox_filters')->nullable()->after('inbox_columns');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('inbox_filters');
        });
    }
};
