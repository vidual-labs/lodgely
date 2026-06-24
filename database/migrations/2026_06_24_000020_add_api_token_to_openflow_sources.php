<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('openflow_sources', function (Blueprint $table) {
            // Preferred auth: a read-only OpenFlow API token (encrypted at rest).
            // When set, login email/password are not needed.
            $table->text('api_token_encrypted')->nullable()->after('password_encrypted');
        });

        // Email is only required for the password-login fallback, so it becomes
        // optional once token auth exists.
        Schema::table('openflow_sources', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('openflow_sources', function (Blueprint $table) {
            $table->dropColumn('api_token_encrypted');
        });
    }
};
