<?php

namespace Tests\Feature;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A recurring fetch that throws (expired OAuth token, unreachable sheet, …)
 * must record the failure on the import and stop the source from being
 * re-fetched on every scheduler tick — instead of a silent 0/0/0/0 row that
 * leaves the operator wondering why the inbox never updates.
 */
class ImportFailureVisibilityTest extends TestCase
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
            'label'          => 'Leads STT',
            'spreadsheet_id' => 'SHEET_ID',
            'sheet_range'    => 'Sheet1',
            'has_header_row' => true,
            'column_map'     => ['0' => 'full_name', '1' => 'email'],
            'refresh_hours'  => 24,
            'is_active'      => true,
        ], $overrides));
    }

    public function test_import_runner_records_the_error_and_rethrows(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->andThrow(new RuntimeException('Google OAuth token refresh failed (400): invalid_grant'));

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'test',
            'meta'      => ['sheet_source_id' => $sheet->id],
        ]);

        try {
            app(ImportRunner::class)->run($import, app(GoogleSheetsLeadSource::class));
            $this->fail('Expected the failing fetch to re-throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
        }

        $import->refresh();
        $this->assertTrue($import->failed(), 'the import is flagged as failed');
        $this->assertStringContainsString('invalid_grant', (string) $import->error);
        $this->assertNotNull($import->finished_at, 'a failed run is still marked finished');
    }

    public function test_scheduled_fetch_failure_is_visible_and_does_not_refetch_until_due(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->andThrow(new RuntimeException('Google Sheets values fetch failed (403): permission denied'));

        $this->artisan('lodgely:google-sheets:fetch')->assertSuccessful();

        // The failure is recorded on the import — no silent 0/0/0/0 row.
        $import = Import::where('source', 'google_sheets')->latest('id')->firstOrFail();
        $this->assertTrue($import->failed());
        $this->assertStringContainsString('permission denied', (string) $import->error);

        // No leads were created by the broken fetch.
        $this->assertSame(0, Lead::count());

        // The clock advanced: a 24h source is no longer due, so the next hourly
        // scheduler tick will NOT pile up another failed import.
        $sheet->refresh();
        $this->assertNotNull($sheet->last_fetched_at);
        $this->assertFalse($sheet->isDue(), 'source respects its refresh interval after a failure');
    }

    public function test_successful_run_clears_a_previously_recorded_error(): void
    {
        $sheet = $this->makeSheetSource();

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Full Name', 'Email'],
            ['Alice', 'alice@example.com'],
        ]);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'retry',
            'error'     => 'previous failure',
            'meta'      => ['sheet_source_id' => $sheet->id],
        ]);

        $result = app(ImportRunner::class)->run($import, app(GoogleSheetsLeadSource::class));

        $this->assertFalse($result->failed(), 'a clean run clears the stale error');
        $this->assertSame(1, $result->rows_imported);
    }
}
