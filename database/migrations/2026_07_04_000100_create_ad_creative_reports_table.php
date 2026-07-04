<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_creative_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->date('date');
            // What the row breaks performance down by: 'ad', 'keyword' or 'segment'.
            $table->string('dimension', 16);
            // Stable per-platform identity of the ad / keyword / segment, used
            // for idempotent re-fetches (Meta ad id, Google ad_group~criterion
            // id, "age|gender" segment key, …).
            $table->string('external_id', 128);
            $table->string('label', 255);
            $table->string('campaign_id', 64)->nullable();
            $table->string('campaign_name', 255)->nullable();
            $table->bigInteger('impressions')->unsigned()->default(0);
            $table->bigInteger('clicks')->unsigned()->default(0);
            $table->bigInteger('spend_cents')->unsigned()->default(0);
            $table->char('currency', 3)->default('USD');
            $table->bigInteger('platform_leads')->unsigned()->default(0);
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'date', 'dimension', 'external_id'], 'acr_unique_snapshot');
            $table->index(['tenant_id', 'date', 'platform', 'dimension'], 'acr_tenant_date_platform_dim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_creative_reports');
    }
};
