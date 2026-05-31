<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_reporting_views', function (Blueprint $table) {
            // Whether the view is visible to its assigned client users.
            // Defaults to true so assigning a client still makes the view live;
            // operators can flip it to false to take the view offline without
            // unassigning anyone.
            $table->boolean('is_live')->default(true)->after('columns');
        });
    }

    public function down(): void
    {
        Schema::table('client_reporting_views', function (Blueprint $table) {
            $table->dropColumn('is_live');
        });
    }
};
