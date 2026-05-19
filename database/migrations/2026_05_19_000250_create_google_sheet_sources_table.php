<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheet_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('spreadsheet_id');
            $table->string('sheet_range')->default('Sheet1');
            $table->boolean('has_header_row')->default(true);
            // {column_index_str: lead_field} e.g. {"0":"full_name","1":"email"}
            $table->jsonb('column_map')->nullable();
            $table->string('default_client_name')->nullable();
            $table->string('default_campaign_name')->nullable();
            $table->unsignedSmallInteger('refresh_hours')->default(24);
            $table->timestamp('last_fetched_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_sources');
    }
};
