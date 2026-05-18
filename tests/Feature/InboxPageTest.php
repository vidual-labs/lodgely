<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Livewire\Inbox\InboxPage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\SavedFilter;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class InboxPageTest extends TestCase
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

    public function test_filters_narrow_the_lead_list_and_clear_filters_resets_them(): void
    {
        $op = $this->operator();

        $high = Lead::factory()->create([
            'client_name' => 'Acme',
            'priority' => LeadPriority::High->value,
            'status' => LeadStatus::New->value,
        ]);
        Lead::factory()->create([
            'client_name' => 'Other',
            'priority' => LeadPriority::Low->value,
            'status' => LeadStatus::Reviewed->value,
        ]);

        $cmp = Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->set('client', 'Acme')
            ->set('priority', LeadPriority::High->value);

        $leads = $cmp->viewData('leads');
        $this->assertSame([$high->id], $leads->pluck('id')->all());

        $cmp->set('bulkSelected', [$high->id])
            ->call('clearFilters')
            ->assertDispatched('inbox-filters-cleared')
            ->dispatch('inbox-filters-cleared');

        $this->assertSame('', $cmp->get('client'));
        $this->assertSame('', $cmp->get('priority'));
        $this->assertSame([], $cmp->get('bulkSelected'));
        $this->assertCount(2, $cmp->viewData('leads'));
    }

    public function test_default_saved_filter_is_applied_on_mount(): void
    {
        $op = $this->operator();

        SavedFilter::create([
            'user_id' => $op->id,
            'tenant_id' => Tenant::DEFAULT_ID,
            'name' => 'My high priority',
            'filters' => ['priority' => LeadPriority::High->value],
            'is_default' => true,
        ]);

        $cmp = Livewire::actingAs($op)->test(InboxPage::class);

        $this->assertSame(LeadPriority::High->value, $cmp->get('priority'));
    }

    public function test_save_filter_persists_current_filter_state(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->set('search', 'acme')
            ->set('status', LeadStatus::New->value)
            ->call('openSaveDialog')
            ->set('newFilterName', 'New from Acme')
            ->call('saveFilter')
            ->assertHasNoErrors();

        $saved = SavedFilter::where('user_id', $op->id)->first();
        $this->assertNotNull($saved);
        $this->assertSame('New from Acme', $saved->name);
        $this->assertSame('acme', $saved->filters['search']);
        $this->assertSame(LeadStatus::New->value, $saved->filters['status']);
    }

    public function test_toggling_default_saved_filter_unsets_any_previous_default(): void
    {
        $op = $this->operator();

        $a = SavedFilter::create([
            'user_id' => $op->id, 'tenant_id' => Tenant::DEFAULT_ID,
            'name' => 'A', 'filters' => [], 'is_default' => true,
        ]);
        $b = SavedFilter::create([
            'user_id' => $op->id, 'tenant_id' => Tenant::DEFAULT_ID,
            'name' => 'B', 'filters' => [], 'is_default' => false,
        ]);

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('toggleDefaultFilter', $b->id);

        $this->assertTrue((bool) $b->fresh()->is_default);
        $this->assertFalse((bool) $a->fresh()->is_default);
    }

    public function test_bulk_set_status_updates_selected_leads_and_audits_each(): void
    {
        $op = $this->operator();

        $a = Lead::factory()->create(['status' => LeadStatus::New->value]);
        $b = Lead::factory()->create(['status' => LeadStatus::New->value]);
        $unselected = Lead::factory()->create(['status' => LeadStatus::New->value]);

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->set('bulkSelected', [$a->id, $b->id])
            ->set('bulkStatusValue', LeadStatus::Reviewed->value)
            ->call('bulkSetStatus');

        $this->assertSame(LeadStatus::Reviewed, $a->fresh()->status);
        $this->assertSame(LeadStatus::Reviewed, $b->fresh()->status);
        $this->assertSame(LeadStatus::New, $unselected->fresh()->status);

        $this->assertCount(
            2,
            LeadEvent::where('type', 'lead.status_changed')->get()
        );
    }

    public function test_client_cannot_invoke_bulk_actions(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme', 'status' => LeadStatus::New->value]);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->set('bulkSelected', [$lead->id])
            ->set('bulkStatusValue', LeadStatus::Reviewed->value)
            ->call('bulkSetStatus')
            ->assertStatus(403);

        $this->assertSame(LeadStatus::New, $lead->fresh()->status);
    }

    public function test_operator_can_open_manual_form_but_client_cannot(): void
    {
        $op = $this->operator();
        $client = $this->clientFor('Acme');

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('openManualForm')
            ->assertSet('showManualForm', true);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('openManualForm')
            ->assertStatus(403);
    }

    public function test_manual_form_requires_at_least_one_identifier(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('openManualForm')
            ->set('manual.message', 'Just a note, no contact details.')
            ->set('manual.priority', 'medium')
            ->call('saveManual')
            ->assertHasErrors('manual.full_name');

        $this->assertSame(0, Lead::count());
    }

    public function test_manual_form_ingests_a_lead_through_the_lead_ingestor(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('openManualForm')
            ->set('manual.client_name', 'Acme')
            ->set('manual.full_name', 'Jane Doe')
            ->set('manual.email', 'jane@example.com')
            ->set('manual.priority', 'high')
            ->call('saveManual')
            ->assertHasNoErrors()
            ->assertSet('showManualForm', false);

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertSame('manual', $lead->source);
        $this->assertSame('Acme', $lead->client_name);
        $this->assertSame('Jane Doe', $lead->full_name);
        $this->assertSame(LeadPriority::High, $lead->priority);
        $this->assertNotNull($lead->retention_until, 'LeadIngestor must set retention_until');
    }

    public function test_client_can_toggle_outreach_fields_on_visible_lead(): void
    {
        $client = $this->clientFor('Acme');
        $lead = Lead::factory()->create(['client_name' => 'Acme']);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $lead->id, 'called_at');

        $lead->refresh();
        $this->assertNotNull($lead->called_at, 'Client should be able to set called_at');
        $this->assertNull($lead->qualified_at);
        $this->assertNull($lead->mailed_at);
        $this->assertSame(
            1,
            LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.outreach_toggled')->count()
        );

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $lead->id, 'called_at');

        $this->assertNull($lead->fresh()->called_at, 'Toggling again clears the timestamp');
    }

    public function test_toggle_outreach_rejects_unknown_field(): void
    {
        $client = $this->clientFor('Acme');
        $own = Lead::factory()->create(['client_name' => 'Acme']);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $own->id, 'status')
            ->assertStatus(422);

        $this->assertNull($own->fresh()->called_at);
    }

    public function test_toggle_outreach_cannot_touch_leads_outside_client_scope(): void
    {
        $client = $this->clientFor('Acme');
        $hers = Lead::factory()->create(['client_name' => 'Other']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($client)
            ->test(InboxPage::class)
            ->call('toggleOutreach', $hers->id, 'called_at');

        $this->assertNull($hers->fresh()->called_at);
    }

    public function test_add_note_creates_a_note_and_writes_an_audit_event(): void
    {
        $op = $this->operator();
        $lead = Lead::factory()->create(['client_name' => 'Acme']);

        Livewire::actingAs($op)
            ->test(InboxPage::class)
            ->call('selectLead', $lead->id)
            ->set('newNoteBody', 'Followed up by email.')
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertSame(1, $lead->notes()->count());
        $this->assertSame(
            'Followed up by email.',
            $lead->notes()->first()->body
        );
        $this->assertSame(
            1,
            LeadEvent::where('lead_id', $lead->id)->where('type', 'lead.note_added')->count()
        );
    }
}
