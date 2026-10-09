<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic AI lead ranking.
 *
 * leads:
 *   priority_source  → 'ai' | 'user' | null — who last wrote `priority`
 *   ai_priority      → the priority the ranker last proposed (kept after a
 *                      human override so the panel can show "AI suggested X")
 *   ai_reason        → one-sentence justification from the model
 *   ai_tags          → short labels the model attached (JSON list of strings)
 *   ai_ranked_at     → when the ranker last applied a result
 *
 * ai_settings:
 *   lead_ranking_profile → operator-wide "ideal customer" text for the prompt
 *   ranking_batch_size   → how many unranked leads each hourly run dispatches
 *
 * client_ai_profiles: one "ideal customer" text per client_name, edited by
 * the client user (own scopes) or an operator (all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('priority_source', 8)->nullable()->after('priority');
            $table->string('ai_priority', 8)->nullable()->after('priority_source');
            $table->text('ai_reason')->nullable()->after('ai_priority');
            $table->jsonb('ai_tags')->nullable()->after('ai_reason');
            $table->timestamp('ai_ranked_at')->nullable()->after('ai_tags');

            $table->index(['tenant_id', 'ai_ranked_at'], 'leads_tenant_ai_ranked');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->text('lead_ranking_profile')->nullable()->after('house_style');
            $table->unsignedSmallInteger('ranking_batch_size')->default(25)->after('lead_ranking_profile');
        });

        Schema::create('client_ai_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('client_name', 160);
            $table->text('profile');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_name'], 'client_ai_profiles_tenant_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ai_profiles');

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['lead_ranking_profile', 'ranking_batch_size']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_ai_ranked');
            $table->dropColumn(['priority_source', 'ai_priority', 'ai_reason', 'ai_tags', 'ai_ranked_at']);
        });
    }
};
