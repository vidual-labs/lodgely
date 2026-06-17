<?php

namespace Tests\Feature;

use App\Importers\Meta\MetaLeadsSource;
use App\Models\Import;
use App\Models\MetaLeadSource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MetaLeadsSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function configureCredentials(string $token = 'test-token'): void
    {
        config()->set('lodgely.reporting.meta.access_token', $token);
        config()->set('lodgely.reporting.meta.api_version', 'v21.0');
    }

    private function makeSource(array $overrides = []): MetaLeadSource
    {
        return MetaLeadSource::create(array_merge([
            'tenant_id'     => Tenant::DEFAULT_ID,
            'label'         => 'Test',
            'page_id'       => 'PAGE1',
            'lookback_days' => 30,
            'refresh_hours' => 24,
            'is_active'     => true,
        ], $overrides));
    }

    private function makeImport(int $sourceId): Import
    {
        return Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'meta_leads',
            'label'     => 'test-import',
            'meta'      => ['meta_lead_source_id' => $sourceId],
        ]);
    }

    private function leadResponse(): array
    {
        return [
            'data' => [[
                'id'            => 'LEAD_1',
                'created_time'  => '2026-06-15T10:00:00+0000',
                'ad_id'         => 'AD1',
                'ad_name'       => 'Ad One',
                'adset_id'      => 'AS1',
                'adset_name'    => 'Adset One',
                'campaign_id'   => 'CMP1',
                'campaign_name' => 'Spring Campaign',
                'form_id'       => 'FORM1',
                'platform'      => 'ig',
                'is_organic'    => false,
                'field_data'    => [
                    ['name' => 'full_name', 'values' => ['Alice Smith']],
                    ['name' => 'email', 'values' => ['alice@example.com']],
                    ['name' => 'phone_number', 'values' => ['+49 30 1234567']],
                    ['name' => 'what_service', 'values' => ['Consultation']],
                ],
            ]],
        ];
    }

    public function test_key_and_label(): void
    {
        $source = new MetaLeadsSource;
        $this->assertSame('meta_leads', $source->key());
        $this->assertSame('Meta Lead Ads', $source->label());
    }

    public function test_pull_with_pinned_form_maps_lead_fields(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*/leads*' => Http::response($this->leadResponse(), 200),
        ]);

        $source = $this->makeSource(['form_id' => 'FORM1', 'form_name' => 'Pinned form']);
        $import = $this->makeImport($source->id);

        $leads = iterator_to_array((new MetaLeadsSource)->pull($import));

        $this->assertCount(1, $leads);
        $lead = $leads[0];

        $this->assertSame('meta_leads', $lead->source);
        $this->assertSame('Alice Smith', $lead->fullName);
        $this->assertSame('alice@example.com', $lead->email);
        $this->assertSame('+49 30 1234567', $lead->phone);
        $this->assertSame('LEAD_1', $lead->externalId);
        $this->assertSame('LEAD_1', $lead->metaLeadId);
        $this->assertSame('AD1', $lead->adId);
        $this->assertSame('Ad One', $lead->adName);
        $this->assertSame('CMP1', $lead->campaignId);
        $this->assertSame('Spring Campaign', $lead->campaignName);
        $this->assertSame('FORM1', $lead->formId);
        $this->assertSame('instagram', $lead->platform);
        $this->assertFalse($lead->isOrganic);

        // Non-core answers become {question, answer} custom answers.
        $this->assertSame([
            ['question' => 'What service', 'answer' => 'Consultation'],
        ], $lead->customAnswers);
    }

    public function test_pull_enumerates_page_forms_then_fetches_leads(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'FORM1', 'name' => 'Spring form', 'status' => 'ACTIVE'],
                ],
            ], 200),
            'graph.facebook.com/*/leads*' => Http::response($this->leadResponse(), 200),
        ]);

        $source = $this->makeSource(['page_id' => 'PAGE1', 'default_client_name' => 'ACME']);
        $import = $this->makeImport($source->id);

        $leads = iterator_to_array((new MetaLeadsSource)->pull($import));

        $this->assertCount(1, $leads);
        $this->assertSame('ACME', $leads[0]->clientName);
        $this->assertSame('Spring form', $leads[0]->formName);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/PAGE1/leadgen_forms'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/FORM1/leads'));
    }

    public function test_pull_combines_first_and_last_name_when_no_full_name(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*/leads*' => Http::response([
                'data' => [[
                    'id'         => 'LEAD_2',
                    'platform'   => 'fb',
                    'field_data' => [
                        ['name' => 'first_name', 'values' => ['Bob']],
                        ['name' => 'last_name', 'values' => ['Jones']],
                        ['name' => 'email', 'values' => ['bob@example.com']],
                    ],
                ]],
            ], 200),
        ]);

        $source = $this->makeSource(['form_id' => 'FORM1']);
        $import = $this->makeImport($source->id);

        $leads = iterator_to_array((new MetaLeadsSource)->pull($import));

        $this->assertSame('Bob Jones', $leads[0]->fullName);
        $this->assertSame('facebook', $leads[0]->platform);
        // No ad_id present → treated as organic.
        $this->assertTrue($leads[0]->isOrganic);
    }

    public function test_pull_throws_when_no_token_configured(): void
    {
        $this->configureCredentials('');

        $source = $this->makeSource(['form_id' => 'FORM1']);
        $import = $this->makeImport($source->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no Meta access token');

        iterator_to_array((new MetaLeadsSource)->pull($import));
    }

    public function test_pull_throws_when_source_id_missing(): void
    {
        $this->configureCredentials();

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'meta_leads',
            'label'     => 'no-source',
            'meta'      => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('meta_lead_source_id');

        iterator_to_array((new MetaLeadsSource)->pull($import));
    }

    public function test_pull_throws_on_api_error(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 401),
        ]);

        $source = $this->makeSource(['form_id' => 'FORM1']);
        $import = $this->makeImport($source->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meta Lead Ads call failed (401)');

        iterator_to_array((new MetaLeadsSource)->pull($import));
    }

    public function test_available_forms_lists_page_forms(): void
    {
        $this->configureCredentials();

        Http::fake([
            'graph.facebook.com/*/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'F1', 'name' => 'Form one', 'status' => 'ACTIVE'],
                    ['id' => 'F2', 'name' => 'Form two', 'status' => 'ARCHIVED'],
                ],
            ], 200),
        ]);

        $forms = (new MetaLeadsSource)->availableForms(Tenant::DEFAULT_ID, 'PAGE1');

        $this->assertCount(2, $forms);
        $this->assertSame('F1', $forms[0]['id']);
        $this->assertSame('Form one', $forms[0]['name']);
        $this->assertSame('ARCHIVED', $forms[1]['status']);
    }

    public function test_is_due_respects_refresh_interval(): void
    {
        $this->assertTrue($this->makeSource(['last_fetched_at' => null])->isDue());
        $this->assertFalse($this->makeSource(['refresh_hours' => 24, 'last_fetched_at' => now()->subHours(12)])->isDue());
        $this->assertTrue($this->makeSource(['refresh_hours' => 24, 'last_fetched_at' => now()->subHours(25)])->isDue());
        $this->assertFalse($this->makeSource(['is_active' => false, 'last_fetched_at' => null])->isDue());
    }
}
