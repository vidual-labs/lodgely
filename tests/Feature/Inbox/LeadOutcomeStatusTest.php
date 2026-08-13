<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Enums\LeadNoteSnippet;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadIngestor;
use App\Importers\Contracts\IncomingLead;
use App\Livewire\Inbox\InboxPage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clients call or mail a lead, send an offer, and then it lands, gets declined
 * or goes quiet. These pin the outcome half of {@see LeadStatus} plus the
 * one-click note phrases that feed it.
 */
class LeadOutcomeStatusTest extends TestCase
{
    use RefreshDatabase;

    private function clientFor(string $clientName): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        UserLeadScope::create([
            'user_id' => $client->id,
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => $clientName,
        ]);

        return $client;
    }

    public function test_client_can_move_their_own_lead_through_the_outcome_statuses(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        $component = Livewire::actingAs($client)->test(InboxPage::class);

        foreach ([LeadStatus::OfferSent, LeadStatus::NoReply, LeadStatus::Declined, LeadStatus::Successful] as $status) {
            $component->call('setStatus', $lead->id, $status->value);
            $this->assertSame($status, $lead->fresh()->status);
        }

        $this->assertSame(
            4,
            LeadEvent::where('type', 'lead.status_changed')->count(),
            'every outcome change belongs in the audit trail',
        );
    }

    public function test_status_filter_narrows_to_a_single_outcome(): void
    {
        $client = $this->clientFor('Acme');

        $pending = Lead::factory()->create([
            'client_name' => 'Acme', 'full_name' => 'Pending Pat',
            'status' => LeadStatus::OfferSent->value,
        ]);
        $declined = Lead::factory()->create([
            'client_name' => 'Acme', 'full_name' => 'Declined Dana',
            'status' => LeadStatus::Declined->value,
        ]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->set('status', LeadStatus::OfferSent->value)
            ->assertSee($pending->full_name)
            ->assertDontSee($declined->full_name);
    }

    public function test_lead_panel_offers_the_outcome_pills_and_the_quick_note_phrases(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        $panel = Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id);

        $panel->assertSee(LeadStatus::OfferSent->label())
            ->assertSee(LeadStatus::Declined->label())
            ->assertSee(LeadStatus::NoReply->label())
            ->assertSee(LeadStatus::Successful->label())
            ->assertSee(LeadNoteSnippet::Mailed->text())
            ->assertSee(LeadNoteSnippet::DeclinedPrice->text())
            ->assertSee(LeadNoteSnippet::SuccessfulBooked->text());

        // The pills post through setStatus(), not a <select> — the panel is the
        // one place a client can actually reach these.
        $panel->assertSee("setStatus({$lead->id}, '".LeadStatus::OfferSent->value."')", false);
    }

    public function test_every_note_snippet_carries_text_and_only_real_statuses(): void
    {
        foreach (LeadNoteSnippet::cases() as $snippet) {
            $this->assertNotSame('', trim($snippet->text()), "{$snippet->value} has no text");

            $suggested = $snippet->suggestedStatus();
            if ($suggested !== null) {
                $this->assertSame(
                    LeadStatus::GROUP_OUTCOME,
                    $suggested->group(),
                    "{$snippet->value} suggests an intake status — snippets describe outcomes",
                );
            }
        }
    }

    /**
     * The status pill already logs and filters Declined/Successful precisely —
     * a chip that just restates the outcome ("Declined offer") adds nothing the
     * status change didn't already say. So there is deliberately no bare
     * "Declined"/"Successful" phrase; only reason-specific ones.
     */
    public function test_no_note_phrase_merely_restates_a_bare_outcome(): void
    {
        foreach (LeadNoteSnippet::cases() as $snippet) {
            $this->assertNotSame(LeadStatus::Declined->label(), $snippet->text());
            $this->assertNotSame(LeadStatus::Successful->label(), $snippet->text());
            $this->assertNotSame(LeadStatus::OfferSent->label(), $snippet->text());
            $this->assertNotSame(LeadStatus::NoReply->label(), $snippet->text());
        }
    }

    public function test_every_declined_or_successful_reason_nudges_the_matching_status(): void
    {
        $this->assertSame(LeadStatus::Declined, LeadNoteSnippet::DeclinedPrice->suggestedStatus());
        $this->assertSame(LeadStatus::Declined, LeadNoteSnippet::DeclinedCompetitor->suggestedStatus());
        $this->assertSame(LeadStatus::Declined, LeadNoteSnippet::DeclinedTiming->suggestedStatus());
        $this->assertSame(LeadStatus::Successful, LeadNoteSnippet::SuccessfulBooked->suggestedStatus());
        $this->assertSame(LeadStatus::Successful, LeadNoteSnippet::SuccessfulSigned->suggestedStatus());

        // The contact-log phrases aren't outcomes — nothing to nudge.
        $this->assertNull(LeadNoteSnippet::CalledNoAnswer->suggestedStatus());
        $this->assertNull(LeadNoteSnippet::CalledSpoke->suggestedStatus());
        $this->assertNull(LeadNoteSnippet::Mailed->suggestedStatus());
        $this->assertNull(LeadNoteSnippet::SentDetails->suggestedStatus());
    }

    public function test_status_options_are_split_into_intake_and_outcome_without_losing_a_case(): void
    {
        $grouped = LeadStatus::grouped();

        $this->assertSame([LeadStatus::GROUP_INTAKE, LeadStatus::GROUP_OUTCOME], array_column($grouped, 'key'));

        $values = array_merge(...array_map(
            static fn (array $g) => array_column($g['options'], 'value'),
            $grouped,
        ));

        $this->assertEqualsCanonicalizing(
            array_column(LeadStatus::cases(), 'value'),
            $values,
            'grouped() must cover every status exactly once',
        );
    }

    public function test_an_importer_can_deliver_an_outcome_status(): void
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $lead = app(LeadIngestor::class)->ingest((new IncomingLead(
            source: 'csv',
            clientName: 'Acme',
            fullName: 'Otto Offer',
            email: 'otto@example.com',
            status: 'offer_sent',
        ))->toIngestPayload());

        $this->assertSame(LeadStatus::OfferSent, $lead->status);
    }
}
