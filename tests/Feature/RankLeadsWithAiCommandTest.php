<?php

namespace Tests\Feature;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Leads\Enums\PrioritySource;
use App\Jobs\RankLeadWithAi;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RankLeadsWithAiCommandTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrap(bool $on = true, int $batch = 25): void
    {
        config()->set('lodgely.ai.enabled', true);
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = $on;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['lead_ranking' => $on];
        $row->lead_data_consent = $on;
        $row->ranking_batch_size = $batch;
        $row->save();
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::factory()->create(array_merge([
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => 'Acme',
            'priority' => 'medium',
            'priority_source' => null,
            'ai_ranked_at' => null,
            'duplicate_flag' => false,
        ], $overrides));
    }

    public function test_only_candidates_are_queued(): void
    {
        Queue::fake();
        $this->bootstrap();

        $fresh      = $this->lead();
        $userSet    = $this->lead(['priority_source' => PrioritySource::User->value]);
        $ranked     = $this->lead(['ai_ranked_at' => now(), 'priority_source' => PrioritySource::Ai->value]);
        $duplicate  = $this->lead(['duplicate_flag' => true]);
        $recentTry  = $this->lead();
        AiSummary::create([
            'tenant_id' => Tenant::DEFAULT_ID, 'kind' => AiSummaryKind::LeadRanking->value,
            'subject_type' => Lead::class, 'subject_id' => $recentTry->id,
            'prompt' => 'p', 'status' => AiSummaryStatus::Failed->value,
        ]);
        $oldTry = $this->lead();
        $old = AiSummary::create([
            'tenant_id' => Tenant::DEFAULT_ID, 'kind' => AiSummaryKind::LeadRanking->value,
            'subject_type' => Lead::class, 'subject_id' => $oldTry->id,
            'prompt' => 'p', 'status' => AiSummaryStatus::Failed->value,
        ]);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('lodgely:ai:rank-leads')
            ->expectsOutputToContain('Queued 2 lead(s)')
            ->assertSuccessful();

        $queuedFor = AiSummary::where('status', AiSummaryStatus::Pending->value)->pluck('subject_id')->all();
        sort($queuedFor);
        $this->assertSame([$fresh->id, $oldTry->id], $queuedFor);
        Queue::assertPushed(RankLeadWithAi::class, 2);
        $this->assertSame(0, AiSummary::where('subject_id', $userSet->id)->count());
        $this->assertSame(0, AiSummary::where('subject_id', $ranked->id)->count());
        $this->assertSame(0, AiSummary::where('subject_id', $duplicate->id)->count());
    }

    public function test_batch_size_limit_option_and_daily_cap_bound_the_run(): void
    {
        Queue::fake();
        $this->bootstrap(batch: 2);
        foreach (range(1, 5) as $i) {
            $this->lead();
        }

        $this->artisan('lodgely:ai:rank-leads')->expectsOutputToContain('Queued 2 lead(s)')->assertSuccessful();
        $this->artisan('lodgely:ai:rank-leads', ['--limit' => 1])->expectsOutputToContain('Queued 1 lead(s)')->assertSuccessful();

        // Two of today's rows already have a response → with a cap of 3 only one call is left.
        config()->set('lodgely.ai.max_calls_per_day', 3);
        AiSummary::query()->limit(2)->get()->each(fn (AiSummary $s) => $s->forceFill(['response' => '{}'])->save());

        $this->artisan('lodgely:ai:rank-leads')->expectsOutputToContain('Queued 1 lead(s)')->assertSuccessful();

        AiSummary::query()->get()->each(fn (AiSummary $s) => $s->forceFill(['response' => '{}'])->save());
        $this->artisan('lodgely:ai:rank-leads')->expectsOutputToContain('cap reached')->assertSuccessful();

        Queue::assertPushed(RankLeadWithAi::class, 4);
    }

    public function test_quiet_no_op_when_gates_are_off_and_dry_run_dispatches_nothing(): void
    {
        Queue::fake();
        $this->bootstrap(on: false);
        $this->lead();

        $this->artisan('lodgely:ai:rank-leads')->expectsOutputToContain('Skipped')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->bootstrap(on: true);
        $this->artisan('lodgely:ai:rank-leads', ['--dry-run' => true])->expectsOutputToContain('Would rank lead(s)')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertSame(0, AiSummary::count());
    }
}
