<?php

namespace Tests\Feature;

use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GoogleSheetsLeadSourceTest extends TestCase
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

    public function test_key_returns_google_sheets(): void
    {
        $client = $this->mock(GoogleSheetsClient::class);
        $source = new GoogleSheetsLeadSource($client);

        $this->assertSame('google_sheets', $source->key());
    }

    public function test_pull_yields_mapped_leads(): void
    {
        $sheet  = $this->makeSheetSource();
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')
            ->with('SHEET_ID', 'Sheet1')
            ->andReturn([
                ['Full Name', 'Email', 'Phone'],
                ['Alice Smith', 'alice@example.com', '555-1234'],
                ['Bob Jones',  'bob@example.com',   '555-5678'],
            ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads  = iterator_to_array($source->pull($import));

        $this->assertCount(2, $leads);
        $this->assertSame('Alice Smith', $leads[0]->fullName);
        $this->assertSame('alice@example.com', $leads[0]->email);
        $this->assertSame('555-1234', $leads[0]->phone);
        $this->assertSame('Bob Jones', $leads[1]->fullName);
    }

    public function test_pull_skips_header_row_when_flag_is_true(): void
    {
        $sheet  = $this->makeSheetSource(['has_header_row' => true]);
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Name', 'Email'],
            ['Alice', 'alice@example.com'],
        ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads  = iterator_to_array($source->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('Alice', $leads[0]->fullName);
    }

    public function test_pull_includes_all_rows_when_no_header(): void
    {
        $sheet  = $this->makeSheetSource(['has_header_row' => false]);
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Alice', 'alice@example.com', '555-0000'],
            ['Bob',   'bob@example.com',   '555-1111'],
        ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads  = iterator_to_array($source->pull($import));

        $this->assertCount(2, $leads);
    }

    public function test_pull_uses_default_client_and_campaign_names(): void
    {
        $sheet = $this->makeSheetSource([
            'column_map'            => [],
            'has_header_row'        => false,
            'default_client_name'   => 'ACME Corp',
            'default_campaign_name' => 'Summer 2026',
        ]);
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Alice', 'alice@example.com'],
        ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads  = iterator_to_array($source->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('ACME Corp', $leads[0]->clientName);
        $this->assertSame('Summer 2026', $leads[0]->campaignName);
    }

    public function test_pull_returns_empty_when_sheet_is_empty(): void
    {
        $sheet  = $this->makeSheetSource();
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([]);

        $source = new GoogleSheetsLeadSource($client);
        $leads  = iterator_to_array($source->pull($import));

        $this->assertEmpty($leads);
    }

    public function test_pull_throws_when_sheet_source_id_missing(): void
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'no-source',
            'meta'      => [],
        ]);

        $client = $this->mock(GoogleSheetsClient::class);
        $source = new GoogleSheetsLeadSource($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sheet_source_id');

        iterator_to_array($source->pull($import));
    }

    public function test_pull_throws_when_sheet_source_not_found(): void
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'google_sheets',
            'label'     => 'bad-source',
            'meta'      => ['sheet_source_id' => 9999],
        ]);

        $client = $this->mock(GoogleSheetsClient::class);
        $source = new GoogleSheetsLeadSource($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('9999');

        iterator_to_array($source->pull($import));
    }

    public function test_pull_emits_custom_answers_as_question_answer_list(): void
    {
        $sheet = $this->makeSheetSource([
            'column_map' => [
                '0' => 'full_name',
                '1' => 'email',
                '2' => 'utm_source',
                '3' => 'custom_answer:event_size',
                '4' => 'question_01',
            ],
        ]);
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Name', 'Email', 'UTM Source', 'Event Size', 'Service requested'],
            ['Alice', 'alice@example.com', 'facebook', 'Large', 'Consultation'],
        ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads = iterator_to_array($source->pull($import));

        $this->assertCount(1, $leads);

        $answers = $leads[0]->customAnswers;
        $this->assertIsArray($answers);
        // Plain list of {question, answer} objects — shape the inbox expects.
        $this->assertSame(array_keys($answers[0]), ['question', 'answer']);

        $byQuestion = [];
        foreach ($answers as $qa) {
            $byQuestion[$qa['question']] = $qa['answer'];
        }
        $this->assertSame('facebook',     $byQuestion['UTM Source']);
        $this->assertSame('Large',        $byQuestion['Event Size']);
        $this->assertSame('Consultation', $byQuestion['Service requested']);
    }

    public function test_pull_uses_humanised_key_when_no_header_row(): void
    {
        $sheet = $this->makeSheetSource([
            'has_header_row' => false,
            'column_map'     => [
                '0' => 'full_name',
                '1' => 'custom_answer:event_size',
            ],
        ]);
        $import = $this->makeImport($sheet->id);

        $client = $this->mock(GoogleSheetsClient::class);
        $client->shouldReceive('fetchValues')->andReturn([
            ['Alice', 'Large'],
        ]);

        $source = new GoogleSheetsLeadSource($client);
        $leads = iterator_to_array($source->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame([
            ['question' => 'Event size', 'answer' => 'Large'],
        ], $leads[0]->customAnswers);
    }

    public function test_google_sheet_source_is_due_when_never_fetched(): void
    {
        $sheet = $this->makeSheetSource(['last_fetched_at' => null]);
        $this->assertTrue($sheet->isDue());
    }

    public function test_google_sheet_source_is_not_due_when_recently_fetched(): void
    {
        $sheet = $this->makeSheetSource([
            'refresh_hours'  => 24,
            'last_fetched_at' => now()->subHours(12),
        ]);
        $this->assertFalse($sheet->isDue());
    }

    public function test_google_sheet_source_is_due_when_interval_elapsed(): void
    {
        $sheet = $this->makeSheetSource([
            'refresh_hours'  => 24,
            'last_fetched_at' => now()->subHours(25),
        ]);
        $this->assertTrue($sheet->isDue());
    }

    public function test_google_sheet_source_is_not_due_when_inactive(): void
    {
        $sheet = $this->makeSheetSource([
            'is_active'      => false,
            'last_fetched_at' => null,
        ]);
        $this->assertFalse($sheet->isDue());
    }
}
