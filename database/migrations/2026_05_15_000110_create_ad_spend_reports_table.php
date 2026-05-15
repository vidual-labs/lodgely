<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_spend_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->date('date');
            $table->string('campaign_id', 64);
            $table->string('campaign_name', 255)->nullable();
            $table->bigInteger('impressions')->unsigned()->default(0);
            $table->bigInteger('clicks')->unsigned()->default(0);
            $table->bigInteger('spend_cents')->unsigned()->default(0);
            $table->char('currency', 3)->default('USD');
            $table->bigInteger('reach')->unsigned()->nullable();
            $table->bigInteger('platform_leads')->unsigned()->default(0);
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'date', 'campaign_id'], 'asr_unique_snapshot');
            $table->index(['tenant_id', 'date', 'platform'], 'asr_tenant_date_platform');
            $table->index(['tenant_id', 'campaign_id'], 'asr_tenant_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spend_reports');
    }
};
