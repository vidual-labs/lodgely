<?php

namespace Tests\Feature;

use App\Domain\Ai\DTOs\LlmResponse;
use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Providers\OpenAiCompatibleProvider;
use App\Domain\Ai\Services\LeadRanker;
use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\PrioritySource;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\ClientAiProfile;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeLlmProvider;
use Tests\TestCase;

/**
 * Automatic lead ranking end to end: request → (sync queue) job → model →
 * parse → written onto the lead. The queue is `sync` in tests, so a
 * request() applies immediately.
 */
class LeadRankingFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeLlmProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeLlmProvider();
        $this->fake->cannedResponse = new LlmResponse(
            text: '{"priority":"high","reason":"Asks for a quote for a 25-person dinner.","tags":["quote request","budget stated"]}',
            model: 'fake-model',
            tokenUsage: ['prompt' => 10, 'completion' => 5, 'total' => 15],
        );
        $this->app->instance(OpenAiCompatibleProvider::class, $this->fake);
    }

    private function bootstrap(bool $rankingOn = true, bool $consent = true): User
    {
        config()->set('lodgely.ai.enabled', true);

        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['report_view' => false, 'lead_qualification' => false, 'lead_ranking' => $rankingOn];
        $row->lead_data_consent = $consent;
        $row->lead_ranking_profile = 'Dinners for 20-30 people in Leipzig or Halle.';
        $row->save();

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id'   => Tenant::DEFAULT_ID,
            'source'      => 'manual',
            'client_name' => 'Acme',
            'full_name'   => 'Jane Doe',
            'email'       => 'jane.doe@example.com',
            'phone'       => '+49 30 1234567',
            'message'     => 'We would like a quote for a dinner for 25 people in Leipzig, 45 EUR per head.',
            'status'      => 'new',
            'priority'    => 'medium',
        ], $overrides));
    }

    public function test_request_applies_priority_reason_tags_and_marks_summary_applied(): void
    {
        $this->bootstrap();
        ClientAiProfile::put(Tenant::DEFAULT_ID, 'acme', 'We only cater vegan menus.', null);
        $lead = $this->lead();

        $summary = app(LeadRanker::class)->request($lead);

        $summary->refresh();
        $lead->refresh();

        $this->assertSame(AiSummaryStatus::Applied, $summary->status);
        $this->assertSame(AiSummaryKind::LeadRanking, $summary->kind);
        $this->assertSame(LeadPriority::High, $lead->priority);
        $this->assertSame(PrioritySource::Ai, $lead->priority_source);
        $this->assertSame(LeadPriority::High, $lead->ai_priority);
        $this->assertSame('Asks for a quote for a 25-person dinner.', $lead->ai_reason);
        $this->assertSame(['quote request', 'budget stated'], $lead->ai_tags);
        $this->assertNotNull($lead->ai_ranked_at);
        $this->assertTrue($lead->isAiRanked());

        // Prompt: pseudonymized, carries both profiles (client matched case-insensitively).
        $this->assertStringNotContainsString('Jane Doe', $summary->prompt);
        $this->assertStringNotContainsString('jane.doe@', $summary->prompt);
        $this->assertStringContainsString('Lead #'.$lead->id, $summary->prompt);
        $this->assertStringContainsString('Leipzig or Halle', $summary->prompt);
        $this->assertStringContainsString('vegan menus', $summary->prompt);
        $this->assertStringContainsString('Client ranking profile for "Acme"', $summary->prompt);

        // Audit trail on the lead.
        $types = LeadEvent::where('lead_id', $lead->id)->pluck('type')->all();
        $this->assertContains('lead.ai_ranked', $types);
        $this->assertContains('lead.priority_changed', $types);
        $changed = LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.priority_changed')->first();
        $this->assertSame(['from' => 'medium', 'to' => 'high', 'automatic' => true, 'ai' => true], $changed->payload);

        $this->assertCount(1, $this->fake->calls);
        $this->assertSame(0.0, $this->fake->calls[0]['request']->temperature);
    }

    public function test_parse_failure_marks_summary_failed_and_leaves_lead_untouched(): void
    {
        $this->bootstrap();
        $this->fake->cannedResponse = new LlmResponse(text: 'I would rather not rank this.', model: 'fake-model');
        $lead = $this->lead();

        $summary = app(LeadRanker::class)->request($lead)->refresh();
        $lead->refresh();

        $this->assertSame(AiSummaryStatus::Failed, $summary->status);
        $this->assertStringContainsString('Could not parse ranking', (string) $summary->error);
        $this->assertSame('I would rather not rank this.', $summary->response);
        $this->assertSame(LeadPriority::Medium, $lead->priority);
        $this->assertNull($lead->priority_source);
        $this->assertNull($lead->ai_ranked_at);
    }

    public function test_requires_ranking_kind_and_consent(): void
    {
        $this->bootstrap(rankingOn: false);
        $lead = $this->lead();

        try {
            app(LeadRanker::class)->request($lead);
            $this->fail('Expected AiDisabledException');
        } catch (AiDisabledException) {
        }

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->kinds_enabled = ['lead_ranking' => true];
        $row->lead_data_consent = false;
        $row->save();

        $this->expectException(AiDisabledException::class);
        app(LeadRanker::class)->request($lead);
    }

    public function test_kill_switch_off_mid_run_marks_failed_without_throwing(): void
    {
        $this->bootstrap();
        $lead = $this->lead();

        $summary = AiSummary::create([
            'tenant_id'    => Tenant::DEFAULT_ID,
            'kind'         => AiSummaryKind::LeadRanking->value,
            'subject_type' => Lead::class,
            'subject_id'   => $lead->id,
            'prompt'       => "[SYSTEM]\nx\n\n[USER]\ny",
            'status'       => AiSummaryStatus::Pending->value,
        ]);

        config()->set('lodgely.ai.enabled', false);
        app(LeadRanker::class)->execute($summary);

        $this->assertSame(AiSummaryStatus::Failed, $summary->fresh()->status);
        $this->assertSame(LeadPriority::Medium, $lead->fresh()->priority);
        $this->assertCount(0, $this->fake->calls);
    }

    public function test_deleted_lead_marks_summary_failed(): void
    {
        $this->bootstrap();
        $lead = $this->lead();

        $summary = AiSummary::create([
            'tenant_id'    => Tenant::DEFAULT_ID,
            'kind'         => AiSummaryKind::LeadRanking->value,
            'subject_type' => Lead::class,
            'subject_id'   => $lead->id,
            'prompt'       => "[SYSTEM]\nx\n\n[USER]\ny",
            'status'       => AiSummaryStatus::Pending->value,
        ]);
        $lead->delete();

        app(LeadRanker::class)->execute($summary);

        $this->assertSame(AiSummaryStatus::Failed, $summary->fresh()->status);
        $this->assertStringContainsString('no longer exists', (string) $summary->fresh()->error);
    }

    public function test_human_prioritised_lead_is_refused_unless_forced(): void
    {
        $op = $this->bootstrap();
        $lead = $this->lead(['priority_source' => PrioritySource::User->value, 'priority' => 'low']);

        try {
            app(LeadRanker::class)->request($lead);
            $this->fail('Expected AiDisabledException');
        } catch (AiDisabledException) {
        }

        app(LeadRanker::class)->request($lead, $op, force: true);

        $this->assertSame(LeadPriority::High, $lead->fresh()->priority);
        $this->assertSame(PrioritySource::Ai, $lead->fresh()->priority_source);
    }
}
