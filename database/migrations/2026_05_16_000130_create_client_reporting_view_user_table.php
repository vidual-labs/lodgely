<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reporting_view_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_reporting_view_id')
                ->constrained('client_reporting_views')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_reporting_view_id', 'user_id'], 'crvu_unique');
            $table->index('user_id', 'crvu_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reporting_view_user');
    }
};
