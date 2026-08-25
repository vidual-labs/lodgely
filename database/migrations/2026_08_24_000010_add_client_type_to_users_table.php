<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable preset key (see App\Domain\Leads\Enums\ClientType).
            // Null = today's B2B "Lead" wording, so this ships with no
            // visible change until an operator opts a client in.
            $table->string('client_type')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('client_type');
        });
    }
};
