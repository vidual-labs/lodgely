<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_report_email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_report_email_id')
                ->constrained('client_report_emails')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_report_email_id', 'user_id'], 'crer_unique');
            $table->index('user_id', 'crer_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_report_email_recipients');
    }
};
