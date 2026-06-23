<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Failure reason for a fetch that threw before completing. Lets the
            // import UI show "Failed — <reason>" instead of a silent 0/0/0/0 row,
            // so a broken recurring source (e.g. an expired OAuth refresh token)
            // is actually visible to the operator. Null = the run did not error.
            $table->text('error')->nullable()->after('rows_skipped');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
