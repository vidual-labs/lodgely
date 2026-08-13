<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Enums\LeadStatus;
use App\Livewire\Inbox\InboxPage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The lead panel is the one screen clients live in, so its layout rules are
 * worth pinning: what stays on screen, what collapses, and what the activity
 * log says about *when* something happened.
 */
class LeadPanelLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function panelHtml(User $user, Lead $lead): string
    {
        return Livewire::actingAs($user)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id)
            ->html();
    }

    public function test_activity_shows_relative_time_while_fresh_and_an_exact_date_once_over_a_day_old(): void
    {
        Carbon::setTestNow('2026-08-13 12:00:00');

        $op = $this->operator();
        $lead = Lead::factory()->create(['status' => LeadStatus::OfferSent->value]);

        LeadEvent::create([
            'lead_id' => $lead->id, 'user_id' => $op->id,
            'type' => 'lead.note_added', 'payload' => [], 'created_at' => now()->subMinutes(10),
        ]);
        LeadEvent::create([
            'lead_id' => $lead->id, 'user_id' => $op->id,
            'type' => 'lead.outreach_toggled', 'payload' => [], 'created_at' => now()->subDays(3),
        ]);

        $html = $this->panelHtml($op, $lead);

        $this->assertStringContainsString('10 minutes ago', $html);
        $this->assertStringContainsString('2026-08-10 12:00', $html, 'a three-day-old event must show its date, not "3 days ago"');
        $this->assertStringNotContainsString('3 days ago', $html);

        Carbon::setTestNow();
    }

    public function test_only_the_five_most_recent_events_stay_on_screen(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['status' => LeadStatus::New->value]);

        foreach (range(1, 9) as $i) {
            LeadEvent::create([
                'lead_id' => $lead->id, 'user_id' => $op->id,
                'type' => 'lead.status_changed', 'payload' => [], 'created_at' => now()->subHours($i),
            ]);
        }

        // 9 events + the automatic "reviewed on open" one = 10, so 5 collapse.
        $this->assertStringContainsString('Show 5 more', $this->panelHtml($op, $lead));
    }

    public function test_the_header_carries_the_current_status_so_it_survives_scrolling(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['status' => LeadStatus::Declined->value]);

        $html = $this->panelHtml($op, $lead);

        // Everything between the panel title and the first body section is the
        // header — the part that stays put while the body scrolls. (The label
        // also appears earlier in the page, in the toolbar's status filter, so
        // a plain assertStringContainsString would prove nothing.)
        $header = substr(
            $html,
            strpos($html, 'lead-panel-title'),
            strpos($html, 'Contact</h3>') - strpos($html, 'lead-panel-title'),
        );

        $this->assertStringContainsString(LeadStatus::Declined->label(), $header);
    }

    public function test_intake_statuses_collapse_behind_a_disclosure(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['status' => LeadStatus::OfferSent->value]);

        $html = $this->panelHtml($op, $lead);
        $intakeSummary = strpos($html, 'Intake</summary>');

        $this->assertNotFalse($intakeSummary);
        $this->assertLessThan(
            strpos($html, "setStatus({$lead->id}, 'new')"),
            $intakeSummary,
            'New/Reviewed/Incomplete/Duplicate are not a daily click — they belong inside the disclosure',
        );
        $this->assertGreaterThan(
            strpos($html, "setStatus({$lead->id}, 'offer_sent')"),
            $intakeSummary,
            'outcome statuses stay on screen',
        );
    }

    public function test_a_current_intake_status_stays_visible_instead_of_hiding_in_the_disclosure(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['status' => LeadStatus::Incomplete->value]);

        $html = $this->panelHtml($op, $lead);

        $this->assertLessThan(
            strpos($html, 'Intake</summary>'),
            strpos($html, "setStatus({$lead->id}, 'incomplete')"),
            'the pill showing where the lead actually stands must never be the collapsed one',
        );
    }

    public function test_ad_attribution_collapses_but_is_still_rendered(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create([
            'source' => 'meta_ads',
            'ad_name' => 'Spring promo — video',
            'form_name' => 'Contact form',
        ]);

        $html = $this->panelHtml($op, $lead);

        $this->assertStringContainsString('Ad source</summary>', $html);
        $this->assertStringContainsString('Spring promo — video', $html);
    }
}
