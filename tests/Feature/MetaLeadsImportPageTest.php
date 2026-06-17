<?php

namespace Tests\Feature;

use App\Livewire\Imports\MetaLeadsImportPage;
use App\Models\MetaLeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MetaLeadsImportPageTest extends TestCase
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

    private function makeSource(array $overrides = []): MetaLeadSource
    {
        return MetaLeadSource::create(array_merge([
            'tenant_id'     => Tenant::DEFAULT_ID,
            'label'         => 'Spring forms',
            'page_id'       => 'PAGE1',
            'lookback_days' => 30,
            'refresh_hours' => 24,
            'is_active'     => true,
        ], $overrides));
    }

    public function test_page_requires_authentication(): void
    {
        $this->get('/imports/meta-leads')->assertRedirect('/login');
    }

    public function test_client_cannot_access_page(): void
    {
        $this->actingAs($this->client())
            ->get('/imports/meta-leads')
            ->assertForbidden();
    }

    public function test_operator_can_load_page(): void
    {
        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->assertOk()
            ->assertSee('Meta Lead Ads');
    }

    public function test_save_source_creates_record(): void
    {
        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'My forms')
            ->set('form.page_id', '123456')
            ->set('form.lookback_days', 30)
            ->set('form.refresh_hours', 24)
            ->call('saveSource')
            ->assertSet('mode', 'list')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('meta_lead_sources', [
            'label'   => 'My forms',
            'page_id' => '123456',
        ]);
    }

    public function test_save_requires_label(): void
    {
        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('openCreate')
            ->set('form.label', '')
            ->set('form.page_id', '123')
            ->call('saveSource')
            ->assertHasErrors(['form.label']);
    }

    public function test_save_requires_page_or_form_id(): void
    {
        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'No target')
            ->set('form.page_id', '')
            ->set('form.form_id', '')
            ->call('saveSource')
            ->assertHasErrors(['form.page_id']);
    }

    public function test_edit_source_populates_form(): void
    {
        $source = $this->makeSource(['label' => 'Edit me', 'page_id' => 'PG9', 'form_id' => 'FM9']);

        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('editSource', $source->id)
            ->assertSet('mode', 'form')
            ->assertSet('editingId', $source->id)
            ->assertSet('form.label', 'Edit me')
            ->assertSet('form.page_id', 'PG9')
            ->assertSet('form.form_id', 'FM9');
    }

    public function test_toggle_active_flips_flag(): void
    {
        $source = $this->makeSource(['is_active' => true]);

        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('toggleActive', $source->id);

        $this->assertFalse($source->fresh()->is_active);
    }

    public function test_delete_source_removes_record(): void
    {
        $source = $this->makeSource();

        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('deleteSource', $source->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('meta_lead_sources', ['id' => $source->id]);
    }

    public function test_load_forms_lists_page_forms(): void
    {
        config()->set('lodgely.reporting.meta.access_token', 'test-token');

        Http::fake([
            'graph.facebook.com/*/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'F1', 'name' => 'Form one', 'status' => 'ACTIVE'],
                ],
            ], 200),
        ]);

        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('openCreate')
            ->set('form.page_id', 'PAGE1')
            ->call('loadForms')
            ->assertSet('formsLoaded', true)
            ->assertSet('loadError', null)
            ->assertSee('Form one');
    }

    public function test_pin_form_sets_form_id_and_resolves_name(): void
    {
        Livewire::actingAs($this->operator())
            ->test(MetaLeadsImportPage::class)
            ->call('openCreate')
            ->set('availableForms', [
                ['id' => 'FORM_X', 'name' => 'Pinned form', 'status' => 'ACTIVE'],
            ])
            ->call('pinForm', 'FORM_X')
            ->assertSet('form.form_id', 'FORM_X')
            ->assertSet('form.form_name', 'Pinned form');
    }
}
