<?php

namespace Tests\Feature;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Exceptions\AiDisabledException;
use App\Domain\Ai\Services\AiSummarizer;
use App\Models\AiSetting;
use App\Models\AiSummary;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadQualificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrap(bool $leadConsent = true): array
    {
        config()->set('lodgely.ai.enabled', true);

        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $op = User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['report_view' => false, 'lead_qualification' => true];
        $row->lead_data_consent = $leadConsent;
        $row->save();

        $lead = Lead::create([
            'tenant_id'   => Tenant::DEFAULT_ID,
            'source'      => 'manual',
            'client_name' => 'Acme',
            'full_name'   => 'Jane Doe',
            'email'       => 'jane.doe@example.com',
            'phone'       => '+49 30 1234567',
            'message'     => 'Looking for a quote on 50 widgets',
            'status'      => 'new',
            'priority'    => 'medium',
        ]);

        return [$op, $lead];
    }

    public function test_lead_qualification_requires_consent(): void
    {
        [$op, $lead] = $this->bootstrap(leadConsent: false);

        $this->expectException(AiDisabledException::class);
        app(AiSummarizer::class)->requestLeadQualification($lead, $op);
    }

    public function test_pseudonymized_prompt_does_not_contain_raw_pii(): void
    {
        Queue::fake();
        [$op, $lead] = $this->bootstrap();

        $summary = app(AiSummarizer::class)->requestLeadQualification($lead, $op);

        $this->assertStringNotContainsString('Jane Doe',          $summary->prompt);
        $this->assertStringNotContainsString('jane.doe@',          $summary->prompt);
        $this->assertStringNotContainsString('1234567',            $summary->prompt);
        $this->assertStringContainsString('Lead #'.$lead->id,      $summary->prompt);
        $this->assertSame(AiSummaryKind::LeadQualification, $summary->kind);
        $this->assertSame(Lead::class, $summary->subject_type);
        $this->assertSame($lead->id, $summary->subject_id);
    }

    public function test_job_is_dispatched_on_request(): void
    {
        Queue::fake();
        [$op, $lead] = $this->bootstrap();

        app(AiSummarizer::class)->requestLeadQualification($lead, $op);

        Queue::assertPushed(\App\Jobs\GenerateAiSummary::class);
    }
}
