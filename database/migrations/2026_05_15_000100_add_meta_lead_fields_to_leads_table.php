<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('meta_lead_id')->nullable()->after('import_id');
            $table->string('ad_id', 64)->nullable()->after('meta_lead_id');
            $table->string('ad_name')->nullable()->after('ad_id');
            $table->string('adset_id', 64)->nullable()->after('ad_name');
            $table->string('adset_name')->nullable()->after('adset_id');
            $table->string('campaign_id', 64)->nullable()->after('adset_name');
            $table->string('form_id', 64)->nullable()->after('campaign_id');
            $table->string('form_name')->nullable()->after('form_id');
            $table->string('platform', 16)->nullable()->after('form_name');
            $table->boolean('is_organic')->nullable()->after('platform');

            $table->index(['tenant_id', 'meta_lead_id']);
            $table->index(['tenant_id', 'ad_id']);
            $table->index(['tenant_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'meta_lead_id']);
            $table->dropIndex(['tenant_id', 'ad_id']);
            $table->dropIndex(['tenant_id', 'form_id']);

            $table->dropColumn([
                'meta_lead_id', 'ad_id', 'ad_name',
                'adset_id', 'adset_name', 'campaign_id',
                'form_id', 'form_name', 'platform', 'is_organic',
            ]);
        });
    }
};
