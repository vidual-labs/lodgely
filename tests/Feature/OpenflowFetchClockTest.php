<?php

namespace Tests\Feature;

use App\Importers\Openflow\OpenflowClient;
use App\Importers\Openflow\OpenflowLeadSource;
use App\Models\Lead;
use App\Models\OpenflowSource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * OpenFlow's fetch clock has two jobs that must not share a column:
 *
 *  - `last_fetched_at` throttles the hourly scheduler (isDue()), and is
 *    advanced on every attempt so a broken source isn't retried every hour.
 *  - `last_successful_fetch_at` is the high-water mark bounding how far back
 *    a pull walks, and may only advance when a pull actually completed.
 *
 * When one column did both, a single failed run moved the cutoff past
 * submissions nothing had ingested — and the next run skipped them for good.
 */
class OpenflowFetchClockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function makeSource(): OpenflowSource
    {
        $source = new OpenflowSource([
            'tenant_id'     => Tenant::DEFAULT_ID,
            'label'         => 'Acme form',
            'base_url'      => 'https://forms.example.com',
            'form_id'       => 'FORM-1',
            'field_map'     => ['fEmail' => 'email'],
            'refresh_hours' => 24,
            'is_active'     => true,
        ]);
        $source->setApiToken('ofw_secret');
        $source->save();

        return $source;
    }

    /** A client that always fails the submissions call, as a broken pull would. */
    private function failingClient(): OpenflowClient
    {
        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('formFields')->andReturn(['title' => 'Contact', 'fields' => []]);
        $client->shouldReceive('submissionsPage')->andThrow(new RuntimeException('OpenFlow unreachable'));

        return $client;
    }

    /** A client returning one submission from a day ago. */
    private function workingClient(): OpenflowClient
    {
        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('formFields')->andReturn([
            'title'  => 'Contact',
            'fields' => [['id' => 'fEmail', 'label' => 'Email', 'type' => 'email']],
        ]);
        $client->shouldReceive('submissionsPage')->andReturn([
            'submissions' => [[
                'id'         => 'sub-1',
                'created_at' => now()->subDay()->toIso8601String(),
                'data'       => ['fEmail' => 'alice@example.com'],
            ]],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        return $client;
    }

    public function test_a_failed_scheduled_fetch_does_not_advance_the_data_cutoff(): void
    {
        $source = $this->makeSource();

        $this->app->instance(OpenflowClient::class, $this->failingClient());
        $this->artisan('lodgely:openflow:fetch', ['--force' => true])->assertExitCode(0);

        $source->refresh();

        // The throttle moved, so the hourly scheduler leaves the broken source
        // alone until its refresh interval elapses...
        $this->assertNotNull($source->last_fetched_at);
        $this->assertFalse($source->isDue());

        // ...but the data cutoff did not, so nothing has been skipped.
        $this->assertNull($source->last_successful_fetch_at);
    }

    public function test_submissions_from_a_failed_window_are_still_ingested_on_the_next_run(): void
    {
        $source = $this->makeSource();

        $this->app->instance(OpenflowClient::class, $this->failingClient());
        $this->artisan('lodgely:openflow:fetch', ['--force' => true])->assertExitCode(0);
        $this->assertSame(0, Lead::query()->count());

        // A day-old submission: with the cutoff wrongly advanced by the failed
        // run, the next pull would treat it as already-seen and stop.
        // OpenflowLeadSource is a container singleton holding the client, so
        // drop the cached instance before swapping in the working one.
        $this->app->instance(OpenflowClient::class, $this->workingClient());
        $this->app->forgetInstance(OpenflowLeadSource::class);
        $this->artisan('lodgely:openflow:fetch', ['--force' => true])->assertExitCode(0);

        $this->assertSame(1, Lead::query()->count());
        $this->assertSame('alice@example.com', Lead::query()->value('email'));

        $source->refresh();
        $this->assertNotNull($source->last_successful_fetch_at);
    }

    public function test_a_successful_fetch_advances_both_clocks(): void
    {
        $source = $this->makeSource();

        $this->app->instance(OpenflowClient::class, $this->workingClient());
        $this->artisan('lodgely:openflow:fetch', ['--force' => true])->assertExitCode(0);

        $source->refresh();
        $this->assertNotNull($source->last_fetched_at);
        $this->assertNotNull($source->last_successful_fetch_at);
    }

    public function test_the_cutoff_still_bounds_the_walk_after_a_successful_fetch(): void
    {
        $source = $this->makeSource();
        $source->update(['last_successful_fetch_at' => now()]);

        // Older than the cutoff minus the overlap window — must be skipped.
        $client = $this->mock(OpenflowClient::class);
        $client->shouldReceive('formFields')->andReturn(['title' => 'Contact', 'fields' => []]);
        $client->shouldReceive('submissionsPage')->andReturn([
            'submissions' => [[
                'id'         => 'ancient',
                'created_at' => now()->subDays(30)->toIso8601String(),
                'data'       => ['fEmail' => 'old@example.com'],
            ]],
            'total' => 1, 'page' => 1, 'limit' => 100,
        ]);

        $leads = iterator_to_array((new OpenflowLeadSource($client))->pull(
            \App\Models\Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => 'openflow',
                'label'     => 'test',
                'meta'      => ['openflow_source_id' => $source->id],
            ])
        ));

        $this->assertSame([], $leads);
    }
}
