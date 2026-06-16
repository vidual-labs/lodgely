<?php

namespace Tests\Feature;

use App\Domain\Leads\Services\ImportRunner;
use App\Domain\Leads\Services\LeadIngestor;
use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleSheetsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function makeSheetSource(array $overrides = []): GoogleSheetSource
    {
        return GoogleSheetSource::create(array_merge([
            'tenant_id'      => Tenant::DEFAULT_ID,
            'label'          => 'Test',
            'spreadsheet_id' => 'SHEET_ID',
            'sheet_range'    => 'Sheet1',
            'has_header_row' => true,
            'column_map'     => ['0' => 'full_name', '1' => 'email', '2' => 'phone'],
            'refresh_hours'  => 24,
            'is_active'      => true,
        ], $overrides));
    }

    private function makeImport(int $sheetSourceId): Import
    {
        return Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'test-import',
            'meta'      => ['sheet_source_id' => $sheetSourceId],
        ]);
    }

    public function test_reimporting_the_same_sheet_skips_existing_rows(): void
    {
        $sheet = $this->makeSheetSource();

        $rows = [
            ['Full Name', 'Email', 'Phone'],
            ['Alice Smith', 'alice@example.com', '555-1234'],
            ['Bob Jones',  'bob@example.com',   '555-5678'],
        ];

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn($rows);

        $runner = app(ImportRunner::class);
        $source = app(GoogleSheetsLeadSource::class);

        // First fetch: both data rows are new.
        $first = $runner->run($this->makeImport($sheet->id), $source);
        $this->assertSame(2, $first->rows_imported);
        $this->assertSame(0, $first->rows_skipped);
        $this->assertSame(2, Lead::count());

        // Second fetch of the unchanged sheet: every row is recognized and skipped.
        $second = $runner->run($this->makeImport($sheet->id), $source);
        $this->assertSame(0, $second->rows_imported);
        $this->assertSame(2, $second->rows_skipped);
        $this->assertSame(2, Lead::count(), 'No new leads should be created on re-fetch.');
    }

    public function test_new_row_added_to_sheet_is_imported_on_next_fetch(): void
    {
        $sheet  = $this->makeSheetSource();
        $client = $this->mock(GoogleSheetsClient::class);

        $client->shouldReceive('fetchValues')->andReturn(
            [
                ['Full Name', 'Email', 'Phone'],
                ['Alice Smith', 'alice@example.com', '555-1234'],
            ],
            [
                ['Full Name', 'Email', 'Phone'],
                ['Alice Smith', 'alice@example.com', '555-1234'],
                ['Bob Jones',   'bob@example.com',   '555-5678'],
            ],
        );

        $runner = app(ImportRunner::class);
        $source = app(GoogleSheetsLeadSource::class);

        $first = $runner->run($this->makeImport($sheet->id), $source);
        $this->assertSame(1, $first->rows_imported);

        $second = $runner->run($this->makeImport($sheet->id), $source);
        $this->assertSame(1, $second->rows_imported, 'Only the newly appended row is imported.');
        $this->assertSame(1, $second->rows_skipped, 'The previously seen row is skipped.');
        $this->assertSame(2, Lead::count());
    }

    public function test_lead_ingestor_is_idempotent_for_a_shared_external_id(): void
    {
        $ingestor = app(LeadIngestor::class);

        $payload = [
            'source'      => 'google_sheets',
            'external_id' => 'fixed-fingerprint',
            'full_name'   => 'Alice',
            'email'       => 'alice@example.com',
        ];

        $first  = $ingestor->ingest($payload);
        $second = $ingestor->ingest($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(1, Lead::where('source', 'google_sheets')->count());
    }

    public function test_dedupe_command_collapses_existing_duplicates(): void
    {
        $sheet  = $this->makeSheetSource();
        $import = $this->makeImport($sheet->id);
        $raw    = ['Alice', 'alice@example.com', '555-1234'];

        // Pre-fix state: the same row ingested three times as separate leads,
        // with no external_id yet.
        foreach (range(1, 3) as $ignored) {
            Lead::create([
                'tenant_id'  => Tenant::DEFAULT_ID,
                'import_id'  => $import->id,
                'source'     => 'google_sheets',
                'full_name'  => 'Alice',
                'email'      => 'alice@example.com',
                'raw_payload' => $raw,
            ]);
        }
        $this->assertSame(3, Lead::where('source', 'google_sheets')->count());

        // Dry-run reports but writes nothing.
        $this->artisan('lodgely:google-sheets:dedupe --dry-run')->assertSuccessful();
        $this->assertSame(3, Lead::where('source', 'google_sheets')->count());

        $this->artisan('lodgely:google-sheets:dedupe')->assertSuccessful();

        $remaining = Lead::where('source', 'google_sheets')->get();
        $this->assertCount(1, $remaining, 'Only the earliest copy survives.');
        $this->assertSame(
            GoogleSheetsLeadSource::fingerprint('SHEET_ID', $raw),
            $remaining->first()->external_id,
            'The survivor is backfilled so future fetches recognize it.',
        );
        $this->assertSame(2, Lead::onlyTrashed()->where('source', 'google_sheets')->count());
    }
}
