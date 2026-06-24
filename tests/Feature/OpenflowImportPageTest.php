<?php

namespace Tests\Feature;

use App\Importers\Openflow\OpenflowClient;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Livewire\Imports\OpenflowImportPage;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OpenflowImportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function client(): User
    {
        return User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
    }

    private function makeSource(array $overrides = []): OpenflowSource
    {
        $source = new OpenflowSource(array_merge([
            'tenant_id' => Tenant::DEFAULT_ID,
            'label'     => 'Acme form',
            'base_url'  => 'https://forms.example.com',
            'email'     => 'admin@openflow.local',
            'form_id'   => 'FORM-UUID',
            'form_name' => 'Contact',
        ], $overrides));
        $source->setPassword('s3cret');
        $source->save();

        return $source;
    }

    public function test_page_requires_authentication(): void
    {
        $this->get('/imports/openflow')->assertRedirect('/login');
    }

    public function test_client_cannot_access_page(): void
    {
        $this->actingAs($this->client())
            ->get('/imports/openflow')
            ->assertForbidden();
    }

    public function test_operator_can_load_page(): void
    {
        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->assertOk()
            ->assertSee('OpenFlow');
    }

    public function test_list_shows_configured_sources(): void
    {
        $this->makeSource(['label' => 'Acme contact form']);

        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->assertSee('Acme contact form');
    }

    public function test_save_source_requires_password_on_create(): void
    {
        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'New')
            ->set('form.base_url', 'https://forms.example.com')
            ->set('form.email', 'admin@openflow.local')
            ->set('form.form_id', 'FORM-1')
            ->set('form.password', '')
            ->call('saveSource')
            ->assertHasErrors(['form.password']);
    }

    public function test_save_source_creates_record_and_encrypts_password(): void
    {
        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'New source')
            ->set('form.base_url', 'https://forms.example.com/')
            ->set('form.email', 'admin@openflow.local')
            ->set('form.password', 'hunter2')
            ->set('form.form_id', 'FORM-1')
            ->set('form.refresh_hours', 24)
            ->call('saveSource')
            ->assertSet('mode', 'list')
            ->assertDispatched('toast');

        $saved = OpenflowSource::where('label', 'New source')->first();
        $this->assertNotNull($saved);
        // Base URL trailing slash trimmed; password encrypted but decryptable.
        $this->assertSame('https://forms.example.com', $saved->base_url);
        $this->assertSame('hunter2', $saved->password());
        $this->assertNotSame('hunter2', $saved->password_encrypted);
    }

    public function test_edit_keeps_existing_password_when_blank(): void
    {
        $source = $this->makeSource();

        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('editSource', $source->id)
            ->assertSet('form.password', '')
            ->set('form.label', 'Renamed')
            ->call('saveSource')
            ->assertSet('mode', 'list');

        $this->assertSame('Renamed', $source->fresh()->label);
        $this->assertSame('s3cret', $source->fresh()->password());
    }

    public function test_field_map_is_saved_from_mapping_rows(): void
    {
        $lw = Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'Mapped')
            ->set('form.base_url', 'https://forms.example.com')
            ->set('form.email', 'admin@openflow.local')
            ->set('form.password', 'pw')
            ->set('form.form_id', 'FORM-1')
            ->set('form.refresh_hours', 24)
            ->set('mappedFields', [
                ['id' => 'fEmail', 'label' => 'Email', 'type' => 'email', 'field' => 'email', 'custom_key' => ''],
                ['id' => 'fName', 'label' => 'Name', 'type' => 'short_text', 'field' => 'full_name', 'custom_key' => ''],
                ['id' => 'fBudget', 'label' => 'Budget', 'type' => 'number', 'field' => 'custom_answer', 'custom_key' => 'budget'],
                ['id' => 'fSkip', 'label' => 'Skip', 'type' => 'short_text', 'field' => '', 'custom_key' => ''],
            ]);

        $lw->call('saveSource')->assertSet('mode', 'list');

        $saved = OpenflowSource::where('label', 'Mapped')->first();
        $this->assertSame([
            'fEmail'  => 'email',
            'fName'   => 'full_name',
            'fBudget' => 'custom_answer:budget',
        ], $saved->field_map);
    }

    public function test_load_forms_surfaces_error_without_password(): void
    {
        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('form.base_url', 'https://forms.example.com')
            ->set('form.email', 'admin@openflow.local')
            ->set('form.password', '')
            ->call('loadForms')
            ->assertSet('formsLoaded', false)
            ->assertSet('loadError', fn ($v) => $v !== null);
    }

    public function test_load_forms_lists_forms_from_client(): void
    {
        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('login')->once()->andReturn('TOKEN');
        $client->shouldReceive('listForms')->once()->andReturn([
            ['id' => 'F1', 'title' => 'Lead form', 'submission_count' => 4],
        ]);
        // OpenflowLeadSource resolves OpenflowClient from the container.
        $this->app->instance(OpenflowClient::class, $client);

        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('form.base_url', 'https://forms.example.com')
            ->set('form.email', 'admin@openflow.local')
            ->set('form.password', 'pw')
            ->call('loadForms')
            ->assertSet('formsLoaded', true)
            ->assertSet('availableForms', [
                ['id' => 'F1', 'title' => 'Lead form', 'submission_count' => 4],
            ]);
    }

    public function test_pin_form_sets_id_and_name(): void
    {
        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('openCreate')
            ->set('availableForms', [['id' => 'F9', 'title' => 'Pinned', 'submission_count' => 0]])
            ->call('pinForm', 'F9')
            ->assertSet('form.form_id', 'F9')
            ->assertSet('form.form_name', 'Pinned');
    }

    public function test_delete_source_removes_record(): void
    {
        $source = $this->makeSource();

        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('deleteSource', $source->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('openflow_sources', ['id' => $source->id]);
    }

    public function test_toggle_active_flips_flag(): void
    {
        $source = $this->makeSource(['is_active' => true]);

        Livewire::actingAs($this->operator())
            ->test(OpenflowImportPage::class)
            ->call('toggleActive', $source->id);

        $this->assertFalse($source->fresh()->is_active);
    }
}
