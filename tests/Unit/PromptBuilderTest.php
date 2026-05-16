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
}
