<?php

namespace Tests\Unit;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Services\PromptBuilder;
use App\Models\AiSetting;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    private function settings(?string $houseStyle = null): AiSetting
    {
        $s = new AiSetting();
        $s->house_style = $houseStyle;
        $s->temperature = null;

        return $s;
    }

    public function test_report_view_prompt_includes_required_sections_in_system(): void
    {
        $req = (new PromptBuilder())->build(
            AiSummaryKind::ReportView,
            $this->settings(),
            ['view' => ['name' => 'Demo']],
        );

        $this->assertStringContainsString('analyst inside lodgely', $req->system);
        $this->assertStringContainsString('## Summary', $req->system);
        $this->assertStringContainsString('## Evaluation', $req->system);
        $this->assertStringContainsString('## Suggested follow-ups', $req->system);
        $this->assertStringContainsString('Reporting view data:', $req->user);
        $this->assertStringContainsString('"Demo"', $req->user);
    }

    public function test_lead_qualification_prompt_includes_required_sections(): void
    {
        $req = (new PromptBuilder())->build(
            AiSummaryKind::LeadQualification,
            $this->settings(),
            ['lead_ref' => 'Lead #1'],
        );

        $this->assertStringContainsString('## Recommended priority', $req->system);
        $this->assertStringContainsString('## Reasoning', $req->system);
        $this->assertStringContainsString('## Suggested next action', $req->system);
        $this->assertStringContainsString('Pseudonymized lead data:', $req->user);
        $this->assertStringContainsString('"Lead #1"', $req->user);
    }

    public function test_house_style_is_passed_through_to_system_prompt(): void
    {
        $req = (new PromptBuilder())->build(
            AiSummaryKind::ReportView,
            $this->settings('Always call out cost-per-lead spikes above 20%.'),
            [],
        );

        $this->assertStringContainsString('Always call out cost-per-lead spikes above 20%.', $req->system);
    }

    public function test_empty_house_style_is_omitted_cleanly(): void
    {
        $req = (new PromptBuilder())->build(
            AiSummaryKind::ReportView,
            $this->settings(''),
            [],
        );

        $this->assertStringNotContainsString('House style', $req->system);
    }

    public function test_lead_ranking_prompt_contains_task_and_json_contract(): void
    {
        $req = (new PromptBuilder())->build(
            AiSummaryKind::LeadRanking,
            $this->settings(),
            ['lead_ref' => 'Lead #7'],
        );

        $this->assertStringContainsString('analyst inside lodgely', $req->system);
        $this->assertStringContainsString('rank ONE pseudonymized lead', $req->system);
        $this->assertStringContainsString('{"priority":"low|medium|high"', $req->system);
        $this->assertStringContainsString(PromptBuilder::RANKING_NO_PROFILE, $req->system);
        $this->assertStringContainsString('Pseudonymized lead data:', $req->user);
        $this->assertStringContainsString('"Lead #7"', $req->user);
        $this->assertSame(0.0, $req->temperature);
        $this->assertSame(PromptBuilder::RANKING_MAX_TOKENS, $req->maxTokens);
    }

    public function test_lead_ranking_prompt_includes_operator_then_client_profile_and_omits_house_style(): void
    {
        $settings = $this->settings('Always call out cost-per-lead spikes.');
        $settings->lead_ranking_profile = 'Dinners for 20-30 people, 40 EUR per head, Leipzig or Halle.';

        $req = (new PromptBuilder())->build(
            AiSummaryKind::LeadRanking,
            $settings,
            ['lead_ref' => 'Lead #1'],
            'We only do vegan menus.',
            'Green Table',
        );

        $operatorAt = strpos($req->system, 'Operator ranking profile');
        $clientAt   = strpos($req->system, 'Client ranking profile for "Green Table"');

        $this->assertNotFalse($operatorAt);
        $this->assertNotFalse($clientAt);
        $this->assertGreaterThan($operatorAt, $clientAt);
        $this->assertStringContainsString('Leipzig or Halle', $req->system);
        $this->assertStringContainsString('vegan menus', $req->system);
        $this->assertStringNotContainsString('House style', $req->system);
        $this->assertStringNotContainsString('cost-per-lead spikes', $req->system);
        $this->assertStringNotContainsString(PromptBuilder::RANKING_NO_PROFILE, $req->system);
    }
}
