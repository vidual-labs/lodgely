<?php

namespace Tests\Feature;

use App\Importers\Openflow\OpenflowClient;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Import;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OpenflowLeadSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function makeSource(array $overrides = []): OpenflowSource
    {
        $source = new OpenflowSource(array_merge([
            'tenant_id'           => Tenant::DEFAULT_ID,
            'label'               => 'Acme form',
            'base_url'            => 'https://forms.example.com',
            'email'               => 'admin@openflow.local',
            'form_id'             => 'FORM-UUID',
            'form_name'           => 'Contact',
            'field_map'           => ['fEmail' => 'email', 'fName' => 'full_name', 'fPhone' => 'phone'],
            'default_client_name' => 'ACME Corp',
            'refresh_hours'       => 24,
            'is_active'           => true,
        ], $overrides));
        $source->setPassword('s3cret');
        $source->save();

        return $source;
    }

    private function makeImport(int $sourceId): Import
    {
        return Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'openflow',
            'label'     => 'test-import',
            'meta'      => ['openflow_source_id' => $sourceId],
        ]);
    }

    /** A client mock with login + form-field labels stubbed; caller adds submissions. */
    private function mockClient(array $submissionsPage): OpenflowClient
    {
        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('login')
            ->andReturn('TOKEN');
        $client->shouldReceive('formFields')
            ->andReturn([
                'title'  => 'Contact',
                'fields' => [
                    ['id' => 'fEmail', 'label' => 'Email', 'type' => 'email'],
                    ['id' => 'fName', 'label' => 'Your name', 'type' => 'short_text'],
                    ['id' => 'fPhone', 'label' => 'Phone', 'type' => 'phone'],
                    ['id' => 'fBudget', 'label' => 'Budget', 'type' => 'number'],
                ],
            ]);
        $client->shouldReceive('submissionsPage')
            ->andReturn($submissionsPage);

        return $client;
    }

    public function test_numeric_field_ids_are_not_duplicated_into_custom_answers(): void
    {
        // OpenFlow installs that expose numeric field ids used to have every
        // mapped field emitted twice: once into its core lead column and again
        // as a custom answer, because PHP turns numeric-string array keys into
        // ints and the "already consumed" check compared them strictly.
        $source = $this->makeSource([
            'field_map' => ['101' => 'email', '102' => 'full_name'],
        ]);
        $import = $this->makeImport($source->id);

        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('login')->andReturn('TOKEN');
        $client->shouldReceive('formFields')->andReturn([
            'title'  => 'Contact',
            'fields' => [
                ['id' => '101', 'label' => 'Email', 'type' => 'email'],
                ['id' => '102', 'label' => 'Your name', 'type' => 'short_text'],
                ['id' => '103', 'label' => 'Budget', 'type' => 'number'],
            ],
        ]);
        $client->shouldReceive('submissionsPage')->andReturn([
            'submissions' => [[
                'id'         => 'sub-1',
                'created_at' => '2026-06-20T10:00:00Z',
                'data'       => ['101' => 'alice@example.com', '102' => 'Alice Smith', '103' => '5000'],
            ]],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('alice@example.com', $leads[0]->email);
        $this->assertSame('Alice Smith', $leads[0]->fullName);

        // Only the genuinely unmapped field survives as a custom answer.
        $questions = array_column($leads[0]->customAnswers, 'question');
        $this->assertSame(['Budget'], $questions);
    }

    public function test_key_returns_openflow(): void
    {
        $client = $this->mock(OpenflowClient::class);
        $this->assertSame('openflow', (new OpenflowLeadSource($client))->key());
    }

    public function test_pull_with_api_token_uses_it_directly_without_login(): void
    {
        $source = new OpenflowSource([
            'tenant_id' => Tenant::DEFAULT_ID,
            'label'     => 'Token source',
            'base_url'  => 'https://forms.example.com',
            'form_id'   => 'FORM-UUID',
            'field_map' => ['fEmail' => 'email'],
        ]);
        $source->setApiToken('ofw_secret');
        $source->save();
        $import = $this->makeImport($source->id);

        $client = $this->mock(OpenflowClient::class);
        // The token is used as the bearer directly — login is never called.
        $client->shouldNotReceive('login');
        $client->shouldReceive('formFields')
            ->with('https://forms.example.com', 'ofw_secret', 'FORM-UUID')
            ->andReturn(['title' => 'Contact', 'fields' => [['id' => 'fEmail', 'label' => 'Email', 'type' => 'email']]]);
        $client->shouldReceive('submissionsPage')
            ->with('https://forms.example.com', 'ofw_secret', 'FORM-UUID', 1, 100)
            ->andReturn([
                'submissions' => [['id' => 's1', 'created_at' => '2026-06-20T10:00:00Z', 'data' => ['fEmail' => 'a@example.com']]],
                'total' => 1, 'page' => 1, 'limit' => 100,
            ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('a@example.com', $leads[0]->email);
    }

    public function test_pull_maps_fields_and_keeps_unmapped_as_custom_answers(): void
    {
        $source = $this->makeSource();
        $import = $this->makeImport($source->id);

        $client = $this->mockClient([
            'submissions' => [
                [
                    'id'         => 'sub-1',
                    'created_at' => '2026-06-20T10:00:00Z',
                    'data'       => [
                        'fEmail'  => 'alice@example.com',
                        'fName'   => 'Alice Smith',
                        'fPhone'  => '555-1234',
                        'fBudget' => '5000',
                    ],
                ],
            ],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('alice@example.com', $leads[0]->email);
        $this->assertSame('Alice Smith', $leads[0]->fullName);
        $this->assertSame('555-1234', $leads[0]->phone);
        $this->assertSame('ACME Corp', $leads[0]->clientName);
        $this->assertSame(OpenflowLeadSource::scopedExternalId($source, 'sub-1'), $leads[0]->externalId);
        $this->assertSame('openflow', $leads[0]->platform);

        // The unmapped Budget field survives as a labelled custom answer.
        $this->assertSame([
            ['question' => 'Budget', 'answer' => '5000'],
        ], $leads[0]->customAnswers);
    }

    public function test_pull_supports_named_custom_answer_mapping(): void
    {
        $source = $this->makeSource([
            'field_map' => ['fEmail' => 'email', 'fBudget' => 'custom_answer:budget'],
        ]);
        $import = $this->makeImport($source->id);

        $client = $this->mockClient([
            'submissions' => [
                [
                    'id'         => 'sub-2',
                    'created_at' => '2026-06-20T10:00:00Z',
                    'data'       => ['fEmail' => 'bob@example.com', 'fBudget' => 'Large'],
                ],
            ],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('bob@example.com', $leads[0]->email);
        // Named custom answer uses the OpenFlow field label as the question.
        $this->assertSame([
            ['question' => 'Budget', 'answer' => 'Large'],
        ], $leads[0]->customAnswers);
    }

    public function test_pull_joins_array_answers(): void
    {
        $source = $this->makeSource(['field_map' => ['fEmail' => 'email']]);
        $import = $this->makeImport($source->id);

        $client = $this->mockClient([
            'submissions' => [
                [
                    'id'         => 'sub-3',
                    'created_at' => '2026-06-20T10:00:00Z',
                    'data'       => ['fEmail' => 'c@example.com', 'fBudget' => ['A', 'B']],
                ],
            ],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertSame('A, B', $leads[0]->customAnswers[0]['answer']);
    }

    public function test_pull_stops_at_high_water_mark(): void
    {
        // last_fetched_at = now; cutoff = now-60min. Older submissions are skipped.
        $source = $this->makeSource([
            'field_map'       => ['fEmail' => 'email'],
            'last_fetched_at' => now(),
        ]);
        $import = $this->makeImport($source->id);

        $client = $this->mockClient([
            'submissions' => [
                ['id' => 'new', 'created_at' => now()->toIso8601String(), 'data' => ['fEmail' => 'new@example.com']],
                ['id' => 'old', 'created_at' => now()->subDays(3)->toIso8601String(), 'data' => ['fEmail' => 'old@example.com']],
            ],
            'total' => 2, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('new@example.com', $leads[0]->email);
    }

    public function test_scoped_external_id_differs_across_forms_and_installs(): void
    {
        $formA = $this->makeSource(['form_id' => 'FORM-A']);
        $formB = $this->makeSource(['form_id' => 'FORM-B']);
        $otherInstall = $this->makeSource(['base_url' => 'https://other.example.com', 'form_id' => 'FORM-A']);

        // Two sources sharing a raw submission id (a very real risk when
        // OpenFlow's own submission ids are small per-form sequential
        // integers) must not collide once scoped.
        $idA = OpenflowLeadSource::scopedExternalId($formA, '1');
        $idB = OpenflowLeadSource::scopedExternalId($formB, '1');
        $idOther = OpenflowLeadSource::scopedExternalId($otherInstall, '1');

        $this->assertNotSame($idA, $idB);
        $this->assertNotSame($idA, $idOther);
        $this->assertNotSame($idB, $idOther);

        // Deterministic: re-pulling the same form yields the same scoped id.
        $this->assertSame($idA, OpenflowLeadSource::scopedExternalId($formA, '1'));
    }

    public function test_pull_throws_when_source_id_missing(): void
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'openflow',
            'label'     => 'no-source',
            'meta'      => [],
        ]);

        $client = $this->mock(OpenflowClient::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('openflow_source_id');

        iterator_to_array((new OpenflowLeadSource($client))->pull($import));
    }

    public function test_pull_throws_when_source_not_found(): void
    {
        $import = $this->makeImport(9999);
        $client = $this->mock(OpenflowClient::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('9999');

        iterator_to_array((new OpenflowLeadSource($client))->pull($import));
    }

    public function test_password_round_trips_through_encryption(): void
    {
        $source = $this->makeSource();
        $this->assertSame('s3cret', $source->fresh()->password());
        $this->assertNotSame('s3cret', $source->fresh()->password_encrypted);
    }

    public function test_is_due_logic(): void
    {
        $this->assertTrue($this->makeSource(['last_fetched_at' => null])->isDue());
        $this->assertFalse($this->makeSource(['refresh_hours' => 24, 'last_fetched_at' => now()->subHours(12)])->isDue());
        $this->assertTrue($this->makeSource(['refresh_hours' => 24, 'last_fetched_at' => now()->subHours(25)])->isDue());
        $this->assertFalse($this->makeSource(['is_active' => false, 'last_fetched_at' => null])->isDue());
    }
}
