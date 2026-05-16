<?php

namespace Tests\Feature;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use App\Livewire\Ai\DraftsPage;
use App\Models\AiEvent;
use App\Models\AiSummary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DraftsPageTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        config()->set('lodgely.ai.enabled', true);
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function pendingSummary(int $userId): AiSummary
    {
        return AiSummary::create([
            'tenant_id'    => Tenant::DEFAULT_ID,
            'kind'         => AiSummaryKind::ReportView->value,
            'prompt'       => 'p',
            'response'     => '## Summary\n…',
            'status'       => AiSummaryStatus::Pending->value,
            'requested_by' => $userId,
        ]);
    }

    public function test_drafts_route_404s_when_ai_disabled(): void
    {
        $op = $this->operator();
        config()->set('lodgely.ai.enabled', false);

        $this->actingAs($op)->get('/ai/drafts')->assertNotFound();
    }

    public function test_client_cannot_open_drafts_page(): void
    {
        $this->operator();
        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $this->actingAs($client)->get('/ai/drafts')->assertForbidden();
    }

    public function test_approve_moves_status_and_records_audit(): void
    {
        $op = $this->operator();
        $s  = $this->pendingSummary($op->id);

        Livewire::actingAs($op)
            ->test(DraftsPage::class)
            ->call('approve', $s->id);

        $s->refresh();
        $this->assertSame(AiSummaryStatus::Approved, $s->status);
        $this->assertSame($op->id, $s->operator_id);
        $this->assertNotNull($s->approved_at);

        $this->assertSame(1, AiEvent::where('ai_summary_id', $s->id)->where('type', 'ai.summary.approved')->count());
    }

    public function test_share_requires_approved_status(): void
    {
        $op = $this->operator();
        $s  = $this->pendingSummary($op->id);

        Livewire::actingAs($op)
            ->test(DraftsPage::class)
            ->call('share', $s->id);

        $s->refresh();
        $this->assertSame(AiSummaryStatus::Pending, $s->status);
        $this->assertNull($s->shared_at);

        // Approve, then share.
        Livewire::actingAs($op)->test(DraftsPage::class)->call('approve', $s->id);
        Livewire::actingAs($op)->test(DraftsPage::class)->call('share', $s->id);

        $s->refresh();
        $this->assertSame(AiSummaryStatus::Shared, $s->status);
        $this->assertNotNull($s->shared_at);
    }

    public function test_reject_records_reason_in_audit(): void
    {
        $op = $this->operator();
        $s  = $this->pendingSummary($op->id);

        Livewire::actingAs($op)
            ->test(DraftsPage::class)
            ->call('select', $s->id)
            ->set('rejectReason', 'not quite right')
            ->call('reject', $s->id);

        $s->refresh();
        $this->assertSame(AiSummaryStatus::Rejected, $s->status);

        $event = AiEvent::where('ai_summary_id', $s->id)->where('type', 'ai.summary.rejected')->first();
        $this->assertNotNull($event);
        $this->assertSame('not quite right', $event->payload['reason'] ?? null);
    }
}
