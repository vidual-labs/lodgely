<?php

namespace Tests\Feature;

use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Domain\Ai\Providers\OpenAiCompatibleProvider;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeLlmProvider;
use Tests\TestCase;

class GenerateAiSummaryJobTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrap(): User
    {
        config()->set('lodgely.ai.enabled', true);

        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['report_view' => true, 'lead_qualification' => true];
        $row->save();

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function makeSummary(int $userId): AiSummary
    {
        return AiSummary::create([
            'tenant_id'    => Tenant::DEFAULT_ID,
            'kind'         => 'report_view',
            'prompt'       => "[SYSTEM]\nYou are a test.\n\n[USER]\nGo.",
            'status'       => AiSummaryStatus::Pending->value,
            'provider'     => 'openai_compatible',
            'requested_by' => $userId,
        ]);
    }

    public function test_job_writes_response_and_keeps_status_pending(): void
    {
        $user = $this->bootstrap();

        $fake = new FakeLlmProvider();
        $this->app->instance(OpenAiCompatibleProvider::class, $fake);

        $summary = $this->makeSummary($user->id);

        (new GenerateAiSummary($summary->id))->handle(
            app(\App\Domain\Ai\Services\AiSummarizer::class),
            app(\App\Support\Audit\AiAuditLogger::class),
        );

        $summary->refresh();
        $this->assertNotNull($summary->response);
        $this->assertSame('fake-model', $summary->model);
        $this->assertSame('fake', $summary->provider);
        $this->assertSame(AiSummaryStatus::Pending, $summary->status);
        $this->assertNotNull($summary->token_usage);
    }

    public function test_job_marks_failed_when_provider_throws(): void
    {
        $user = $this->bootstrap();

        $fake = new FakeLlmProvider();
        $fake->shouldFail = true;
        $fake->failMessage = 'boom';
        $this->app->instance(OpenAiCompatibleProvider::class, $fake);

        $summary = $this->makeSummary($user->id);

        try {
            (new GenerateAiSummary($summary->id))->handle(
                app(\App\Domain\Ai\Services\AiSummarizer::class),
                app(\App\Support\Audit\AiAuditLogger::class),
            );
            $this->fail('Expected exception');
        } catch (\Throwable $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $summary->refresh();
        $this->assertSame(AiSummaryStatus::Failed, $summary->status);
        $this->assertStringContainsString('boom', (string) $summary->error);
    }

    public function test_job_no_ops_when_global_kill_switch_off(): void
    {
        $user = $this->bootstrap();
        config()->set('lodgely.ai.enabled', false);

        $summary = $this->makeSummary($user->id);

        (new GenerateAiSummary($summary->id))->handle(
            app(\App\Domain\Ai\Services\AiSummarizer::class),
            app(\App\Support\Audit\AiAuditLogger::class),
        );

        $summary->refresh();
        $this->assertSame(AiSummaryStatus::Failed, $summary->status);
        $this->assertNull($summary->response);
    }
}
