<?php

namespace Tests\Feature;

use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Livewire\Imports\GoogleSheetsImportPage;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleSheetsImportPageTest extends TestCase
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

    private function makeSource(array $overrides = []): GoogleSheetSource
    {
        return GoogleSheetSource::create(array_merge([
            'tenant_id'      => Tenant::DEFAULT_ID,
            'label'          => 'Test Sheet',
            'spreadsheet_id' => 'abc123',
            'sheet_range'    => 'Sheet1',
            'has_header_row' => true,
            'refresh_hours'  => 24,
            'is_active'      => true,
        ], $overrides));
    }

    public function test_page_requires_authentication(): void
    {
        $this->get('/imports/google-sheets')->assertRedirect('/login');
    }

    public function test_client_cannot_access_page(): void
    {
        $this->actingAs($this->client())
            ->get('/imports/google-sheets')
            ->assertForbidden();
    }

    public function test_operator_can_load_page(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->assertOk()
            ->assertSee('Google Sheets');
    }

    public function test_list_shows_configured_sources(): void
    {
        $this->makeSource(['label' => 'My Contact Form']);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->assertSee('My Contact Form');
    }

    public function test_open_create_switches_to_form_mode(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->assertSet('mode', 'form')
            ->assertSet('editingId', null);
    }

    public function test_save_source_creates_new_record(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'New Sheet')
            ->set('form.spreadsheet_id', 'SHEET_XYZ')
            ->set('form.sheet_range', 'Sheet1')
            ->set('form.refresh_hours', 24)
            ->call('saveSource')
            ->assertSet('mode', 'list')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('google_sheet_sources', [
            'label'          => 'New Sheet',
            'spreadsheet_id' => 'SHEET_XYZ',
        ]);
    }

    public function test_save_source_validates_required_fields(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.label', '')
            ->set('form.spreadsheet_id', '')
            ->call('saveSource')
            ->assertHasErrors(['form.label', 'form.spreadsheet_id']);
    }

    public function test_edit_source_populates_form(): void
    {
        $source = $this->makeSource([
            'label'          => 'Edit Me',
            'spreadsheet_id' => 'EDIT_ID',
            'sheet_range'    => 'Data!A:D',
        ]);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('editSource', $source->id)
            ->assertSet('mode', 'form')
            ->assertSet('editingId', $source->id)
            ->assertSet('form.label', 'Edit Me')
            ->assertSet('form.spreadsheet_id', 'EDIT_ID');
    }

    public function test_save_source_updates_existing_record(): void
    {
        $source = $this->makeSource(['label' => 'Old Label']);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('editSource', $source->id)
            ->set('form.label', 'New Label')
            ->call('saveSource')
            ->assertSet('mode', 'list');

        $this->assertDatabaseHas('google_sheet_sources', [
            'id'    => $source->id,
            'label' => 'New Label',
        ]);
    }

    public function test_delete_source_removes_record(): void
    {
        $source = $this->makeSource();

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('deleteSource', $source->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('google_sheet_sources', ['id' => $source->id]);
    }

    public function test_toggle_active_flips_is_active(): void
    {
        $source = $this->makeSource(['is_active' => true]);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('toggleActive', $source->id);

        $this->assertFalse($source->fresh()->is_active);
    }

    public function test_load_columns_detects_header_row(): void
    {
        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->once()
            ->andReturn([['Name', 'Email', 'Phone']]);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.spreadsheet_id', 'SHEET_ID')
            ->set('form.sheet_range', 'Sheet1')
            ->set('form.has_header_row', true)
            ->call('loadColumns')
            ->assertSet('columnsLoaded', true)
            ->assertSet('detectedColumns', [
                ['index' => 0, 'display' => 'Name',  'field' => 'full_name'],
                ['index' => 1, 'display' => 'Email', 'field' => 'email'],
                ['index' => 2, 'display' => 'Phone', 'field' => 'phone'],
            ]);
    }

    public function test_load_columns_uses_column_letters_when_no_header(): void
    {
        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->once()
            ->andReturn([['Alice', 'alice@example.com']]);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.spreadsheet_id', 'SHEET_ID')
            ->set('form.has_header_row', false)
            ->call('loadColumns')
            ->assertSet('columnsLoaded', true)
            ->assertSet('detectedColumns', [
                ['index' => 0, 'display' => 'Column A', 'field' => ''],
                ['index' => 1, 'display' => 'Column B', 'field' => ''],
            ]);
    }

    public function test_load_columns_shows_error_when_spreadsheet_id_empty(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.spreadsheet_id', '')
            ->call('loadColumns')
            ->assertSet('columnsLoaded', false)
            ->assertSet('loadError', fn ($v) => $v !== null);
    }

    public function test_load_columns_preserves_existing_mapping_in_edit_mode(): void
    {
        $source = $this->makeSource([
            'column_map' => ['0' => 'full_name', '1' => 'email'],
        ]);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->once()
            ->andReturn([['Name', 'Email Address']]);

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('editSource', $source->id)
            ->call('loadColumns')
            ->assertSet('columnsLoaded', true)
            ->assertSet('detectedColumns', [
                ['index' => 0, 'display' => 'Name',          'field' => 'full_name'],
                ['index' => 1, 'display' => 'Email Address', 'field' => 'email'],
            ]);
    }

    public function test_column_map_is_saved_with_source(): void
    {
        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->once()
            ->andReturn([['Name', 'Email']]);

        $lw = Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'Mapped Sheet')
            ->set('form.spreadsheet_id', 'S1')
            ->set('form.sheet_range', 'Sheet1')
            ->set('form.refresh_hours', 24)
            ->call('loadColumns');

        $lw->set('detectedColumns.0.field', 'full_name')
            ->set('detectedColumns.1.field', 'email')
            ->call('saveSource');

        $this->assertDatabaseHas('google_sheet_sources', ['label' => 'Mapped Sheet']);
        $saved = GoogleSheetSource::where('label', 'Mapped Sheet')->first();
        $this->assertSame(['0' => 'full_name', '1' => 'email'], $saved->column_map);
    }

    public function test_fetch_now_dispatches_toast_on_success(): void
    {
        $source = $this->makeSource();
        Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'stub',
            'meta'      => [],
        ]);

        $import = Import::where('source', 'google_sheets')->first();
        $import->update(['rows_imported' => 3, 'rows_duplicate' => 1, 'rows_invalid' => 0]);

        $runner = $this->mock(\App\Domain\Leads\Services\ImportRunner::class);
        $runner->shouldReceive('run')->once()->andReturn($import->fresh());

        $leadSource = $this->mock(GoogleSheetsLeadSource::class);
        $leadSource->shouldReceive('key')->andReturn('google_sheets');

        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('fetchNow', $source->id)
            ->assertDispatched('toast');
    }

    public function test_back_to_list_resets_state(): void
    {
        Livewire::actingAs($this->operator())
            ->test(GoogleSheetsImportPage::class)
            ->call('openCreate')
            ->set('form.label', 'something')
            ->call('backToList')
            ->assertSet('mode', 'list')
            ->assertSet('editingId', null)
            ->assertSet('columnsLoaded', false);
    }
}
