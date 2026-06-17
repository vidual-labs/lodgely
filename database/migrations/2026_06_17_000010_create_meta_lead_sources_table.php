<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->cascadeOnDelete();
            $table->string('label');
            // The Facebook Page that owns the lead gen forms. Required unless a
            // specific form_id is pinned below.
            $table->string('page_id')->nullable();
            // Optional: pin a single lead gen form. When null, every active form
            // on the page is pulled.
            $table->string('form_id')->nullable();
            $table->string('form_name')->nullable();
            $table->string('default_client_name')->nullable();
            $table->string('default_campaign_name')->nullable();
            // How far back each fetch looks (days). Bounds the Graph API call so
            // first contact doesn't drag the entire form history.
            $table->unsignedSmallInteger('lookback_days')->default(30);
            $table->unsignedSmallInteger('refresh_hours')->default(24);
            $table->timestamp('last_fetched_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_lead_sources');
    }
};
