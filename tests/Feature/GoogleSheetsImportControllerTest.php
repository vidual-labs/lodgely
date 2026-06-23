<?php

namespace Tests\Feature;

use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class GoogleSheetsImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function makeImportWithLeads(int $leadCount, string $source = 'google_sheets'): Import
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => $source,
            'label'     => 'test-import',
            'meta'      => [],
        ]);

        for ($i = 0; $i < $leadCount; $i++) {
            Lead::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'import_id' => $import->id,
                'source'    => $source,
                'full_name' => "Lead {$i}",
                'email'     => "lead{$i}@example.com",
            ]);
        }

        return $import;
    }

    public function test_destroy_removes_import_and_its_leads(): void
    {
        $import = $this->makeImportWithLeads(3);

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.imports.destroy', $import->id))
            ->assertRedirect(route('imports.google-sheets'));

        $this->assertDatabaseMissing('imports', ['id' => $import->id]);
        $this->assertSame(0, Lead::withTrashed()->where('import_id', $import->id)->count());
    }

    public function test_destroy_all_wipes_every_google_sheets_import_and_lead(): void
    {
        $this->makeImportWithLeads(3);
        $this->makeImportWithLeads(2);
        // A lead whose import was already removed (import_id nulled) must also go.
        Lead::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'import_id' => null,
            'source'    => 'google_sheets',
            'full_name' => 'Orphan',
            'email'     => 'orphan@example.com',
        ]);

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.imports.destroy-all'))
            ->assertRedirect(route('imports.google-sheets'));

        $this->assertSame(0, Import::where('source', 'google_sheets')->count());
        $this->assertSame(0, Lead::withTrashed()->where('source', 'google_sheets')->count());
    }

    public function test_destroy_all_removes_leads_whose_source_was_mapped_from_a_column(): void
    {
        // Reproduces the real bug: the sheet had a "Source" column mapped, so the
        // leads' `source` is e.g. "facebook" — not "google_sheets" — yet they
        // belong to a google_sheets import via import_id.
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'mapped-source',
            'meta'      => [],
        ]);
        foreach (range(1, 3) as $i) {
            Lead::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'import_id' => $import->id,
                'source'    => 'facebook',
                'full_name' => "FB {$i}",
                'email'     => "fb{$i}@example.com",
            ]);
        }

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.imports.destroy-all'))
            ->assertRedirect(route('imports.google-sheets'));

        $this->assertSame(0, Import::where('source', 'google_sheets')->count());
        $this->assertSame(0, Lead::withTrashed()->where('import_id', $import->id)->count());
        $this->assertSame(0, Lead::withTrashed()->where('source', 'facebook')->count());
    }

    public function test_destroy_leaves_non_google_sheets_imports_untouched(): void
    {
        $csv = $this->makeImportWithLeads(2, 'csv');

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.imports.destroy', $csv->id))
            ->assertNotFound();

        $this->assertDatabaseHas('imports', ['id' => $csv->id]);
    }

    public function test_client_cannot_delete_imports(): void
    {
        $import = $this->makeImportWithLeads(1);

        $this->actingAs($this->client())
            ->post(route('imports.google-sheets.imports.destroy', $import->id))
            ->assertForbidden();

        $this->assertDatabaseHas('imports', ['id' => $import->id]);
    }

    private function makeSheetSource(): GoogleSheetSource
    {
        return GoogleSheetSource::create([
            'tenant_id'      => Tenant::DEFAULT_ID,
            'label'          => 'Leads STT',
            'spreadsheet_id' => 'SHEET_ID',
            'sheet_range'    => 'Sheet1',
            'has_header_row' => true,
            'column_map'     => ['0' => 'full_name', '1' => 'email'],
            'refresh_hours'  => 24,
            'is_active'      => true,
        ]);
    }

    public function test_fetch_endpoint_imports_leads_and_redirects(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Full Name', 'Email'],
            ['Alice', 'alice@example.com'],
            ['Bob', 'bob@example.com'],
        ]);

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.fetch', $sheet->id))
            ->assertRedirect(route('imports.google-sheets'))
            ->assertSessionHas('status');

        $this->assertSame(2, Lead::where('source', 'google_sheets')->count());
        $this->assertNotNull($sheet->fresh()->last_fetched_at);
    }

    public function test_fetch_endpoint_reports_a_failure_without_throwing(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->andThrow(new RuntimeException('Google OAuth token refresh failed (400): invalid_grant'));

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.fetch', $sheet->id))
            ->assertRedirect(route('imports.google-sheets'))
            ->assertSessionHas('status');

        $import = Import::where('source', 'google_sheets')->latest('id')->firstOrFail();
        $this->assertTrue($import->failed());
        $this->assertStringContainsString('invalid_grant', (string) $import->error);
    }

    public function test_fetch_endpoint_flags_an_empty_sheet(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([]);

        $this->actingAs($this->operator())
            ->post(route('imports.google-sheets.fetch', $sheet->id))
            ->assertRedirect(route('imports.google-sheets'))
            ->assertSessionHas('status', fn ($status) => str_contains((string) $status, 'no rows'));

        $this->assertSame(0, Lead::count());
    }

    public function test_client_cannot_trigger_fetch(): void
    {
        $sheet = $this->makeSheetSource();

        $this->actingAs($this->client())
            ->post(route('imports.google-sheets.fetch', $sheet->id))
            ->assertForbidden();
    }
}
