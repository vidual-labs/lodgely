<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Enums\LeadStatus;
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
 * Status used to be the field nobody touched. These pin the two automatic
 * steps that now fill it in — opening a lead marks it Reviewed, the first
 * outreach toggle marks it Pending — and, more importantly, everything the
 * automation must keep its hands off.
 */
class LeadStatusAutomationTest extends TestCase
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

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    public function test_opening_the_side_panel_marks_a_new_lead_reviewed(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id);

        $this->assertSame(LeadStatus::Reviewed, $lead->fresh()->status);

        $event = LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.status_changed')->sole();
        $this->assertSame('new', $event->payload['from']);
        $this->assertSame('reviewed', $event->payload['to']);
        $this->assertTrue($event->payload['automatic'], 'the trail must show this was not a manual edit');
        $this->assertSame($client->id, $event->user_id);
    }

    public static function untouchableStatuses(): array
    {
        return [
            'offer sent' => [LeadStatus::OfferSent],
            'successful' => [LeadStatus::Successful],
            'declined'   => [LeadStatus::Declined],
            'no reply'   => [LeadStatus::NoReply],
            'forwarded'  => [LeadStatus::Forwarded],
            'incomplete' => [LeadStatus::Incomplete],
            'duplicate'  => [LeadStatus::Duplicate],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('untouchableStatuses')]
    public function test_opening_a_lead_never_overwrites_a_status_someone_chose(LeadStatus $status): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => $status->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id)
            ->call('toggleOutreach', $lead->id, 'called_at');

        $this->assertSame($status, $lead->fresh()->status);
        $this->assertSame(0, LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.status_changed')->count());
    }

    public function test_first_outreach_toggle_marks_the_lead_pending(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $lead->id, 'mailed_at');

        $lead->refresh();
        $this->assertNotNull($lead->mailed_at);
        $this->assertSame(LeadStatus::Pending, $lead->status);
    }

    public function test_a_reviewed_lead_also_advances_to_pending_on_first_contact(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::Reviewed->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $lead->id, 'qualified_at');

        $this->assertSame(LeadStatus::Pending, $lead->fresh()->status);
    }

    public function test_clearing_an_outreach_toggle_does_not_walk_the_status_back(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        $component = Livewire::actingAs($client)->test(InboxPage::class);

        $component->call('toggleOutreach', $lead->id, 'called_at');
        $this->assertSame(LeadStatus::Pending, $lead->fresh()->status);

        $component->call('toggleOutreach', $lead->id, 'called_at');

        $lead->refresh();
        $this->assertNull($lead->called_at, 'the toggle itself still clears');
        $this->assertSame(LeadStatus::Pending, $lead->status, 'the contact still happened');
    }

    public function test_a_second_toggle_does_not_re_record_the_same_status_change(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $lead->id, 'called_at')
            ->call('toggleOutreach', $lead->id, 'mailed_at');

        $this->assertSame(
            1,
            LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.status_changed')->count(),
            'Pending is reached once, not once per pill',
        );
    }

    public function test_operators_browsing_leads_review_them_the_same_way_clients_do(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id);

        $this->assertSame(LeadStatus::Reviewed, $lead->fresh()->status);
    }

    public function test_opening_a_lead_outside_a_clients_scope_changes_nothing(): void
    {
        $client = $this->clientFor('Acme');
        $someoneElses = Lead::factory()->create(['client_name' => 'Other', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('selectLead', $someoneElses->id);

        $this->assertSame(LeadStatus::New, $someoneElses->fresh()->status);
    }
}
