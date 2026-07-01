<?php

namespace Tests\Feature;

use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Import;
use App\Models\Lead;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescopeOpenflowExternalIdsTest extends TestCase
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
            'tenant_id' => Tenant::DEFAULT_ID,
            'label' => 'Acme form',
            'base_url' => 'https://forms.example.com',
            'email' => 'admin@openflow.local',
            'form_id' => 'FORM-A',
            'form_name' => 'Contact',
        ], $overrides));
        $source->setPassword('s3cret');
        $source->save();

        return $source;
    }

    private function makeLead(OpenflowSource $source, string $submissionId, string $externalId): Lead
    {
        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source' => 'openflow',
            'label' => 'test-import',
            'meta' => ['openflow_source_id' => $source->id],
        ]);

        return Lead::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'import_id' => $import->id,
            'source' => 'openflow',
            'external_id' => $externalId,
            'raw_payload' => ['id' => $submissionId, 'data' => []],
        ]);
    }

    public function test_rescope_backfills_unscoped_external_ids(): void
    {
        $source = $this->makeSource();
        $lead = $this->makeLead($source, 'sub-1', 'sub-1');

        $this->artisan('lodgely:openflow:rescope-ids')->assertExitCode(0);

        $this->assertSame(
            OpenflowLeadSource::scopedExternalId($source, 'sub-1'),
            $lead->fresh()->external_id,
        );
    }

    public function test_rescope_is_idempotent(): void
    {
        $source = $this->makeSource();
        $lead = $this->makeLead($source, 'sub-1', 'sub-1');

        $this->artisan('lodgely:openflow:rescope-ids')->assertExitCode(0);
        $firstPass = $lead->fresh()->external_id;

        $this->artisan('lodgely:openflow:rescope-ids')->assertExitCode(0);
        $this->assertSame($firstPass, $lead->fresh()->external_id);
    }

    public function test_rescope_dry_run_does_not_write(): void
    {
        $source = $this->makeSource();
        $lead = $this->makeLead($source, 'sub-1', 'sub-1');

        $this->artisan('lodgely:openflow:rescope-ids', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('sub-1', $lead->fresh()->external_id);
    }

    public function test_rescope_skips_leads_whose_source_was_deleted(): void
    {
        $source = $this->makeSource();
        $lead = $this->makeLead($source, 'sub-1', 'sub-1');
        $source->delete();

        $this->artisan('lodgely:openflow:rescope-ids')->assertExitCode(0);

        $this->assertSame('sub-1', $lead->fresh()->external_id);
    }

    public function test_rescope_distinguishes_two_forms_sharing_a_raw_submission_id(): void
    {
        $formA = $this->makeSource(['form_id' => 'FORM-A']);
        $formB = $this->makeSource(['form_id' => 'FORM-B']);
        $leadA = $this->makeLead($formA, '1', '1');
        $leadB = $this->makeLead($formB, '1', '1');

        $this->artisan('lodgely:openflow:rescope-ids')->assertExitCode(0);

        $this->assertNotSame($leadA->fresh()->external_id, $leadB->fresh()->external_id);
    }
}
