<?php

namespace Tests\Feature;

use App\Domain\Ai\Providers\OpenAiCompatibleProvider;
use App\Livewire\Settings\AiSettingsPage;
use App\Models\AiSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\FakeLlmProvider;
use Tests\TestCase;

class AiSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantAndOperator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name'      => 'Op',
            'email'     => 'op@example.com',
            'password'  => Hash::make('p'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_settings_route_404s_when_ai_disabled(): void
    {
        config()->set('lodgely.ai.enabled', false);
        $op = $this->setupTenantAndOperator();

        $this->actingAs($op)->get('/settings/ai')->assertNotFound();
    }

    public function test_client_cannot_open_settings_page_even_when_ai_enabled(): void
    {
        config()->set('lodgely.ai.enabled', true);
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $this->actingAs($client)->get('/settings/ai')->assertForbidden();
    }

    public function test_operator_saves_settings_and_api_key_is_encrypted_at_rest(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();

        Livewire::actingAs($op)
            ->test(AiSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.provider', 'openai_compatible')
            ->set('form.api_key', 'sk-secret-12345')
            ->set('form.model', 'gpt-4o-mini')
            ->set('form.kinds_enabled.report_view', true)
            ->set('form.lead_data_consent', false)
            ->call('save')
            ->assertHasNoErrors();

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);

        $this->assertTrue($row->enabled);
        $this->assertSame('openai_compatible', $row->provider);
        $this->assertSame('gpt-4o-mini', $row->model);
        $this->assertNotNull($row->api_key_encrypted);
        $this->assertNotSame('sk-secret-12345', $row->api_key_encrypted, 'API key must be encrypted at rest');
        $this->assertSame('sk-secret-12345', Crypt::decryptString($row->api_key_encrypted));
    }

    public function test_blank_api_key_input_does_not_clear_the_stored_one(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();

        // First save: set a key.
        Livewire::actingAs($op)
            ->test(AiSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.provider', 'openai_compatible')
            ->set('form.api_key', 'sk-first')
            ->call('save');

        // Second save: leave api_key blank, change model.
        Livewire::actingAs($op)
            ->test(AiSettingsPage::class)
            ->set('form.model', 'gpt-4o')
            ->set('form.api_key', '')
            ->call('save');

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertSame('gpt-4o', $row->model);
        $this->assertSame('sk-first', Crypt::decryptString($row->api_key_encrypted));
    }

    public function test_test_connection_uses_provider_ping(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();

        $fake = new FakeLlmProvider();
        $fake->pingResult = true;
        $this->app->instance(OpenAiCompatibleProvider::class, $fake);

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->save();

        $component = Livewire::actingAs($op)
            ->test(AiSettingsPage::class)
            ->call('testConnection');

        $this->assertStringStartsWith('success:', (string) $component->get('testResult'));
    }

    public function test_settings_page_is_reachable_with_the_default_config(): void
    {
        // No config override: LODGELY_AI_ENABLED defaults to true, so the page
        // exists before any .env edit.
        $op = $this->setupTenantAndOperator();

        $this->withoutVite()->actingAs($op)->get('/settings/ai')->assertSuccessful();
    }

    public function test_operator_saves_ranking_fields(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();

        Livewire::actingAs($op)
            ->test(AiSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.provider', 'openai_compatible')
            ->set('form.kinds_enabled.lead_ranking', true)
            ->set('form.lead_data_consent', true)
            ->set('form.lead_ranking_profile', 'Dinners for 20-30 people in Leipzig.')
            ->set('form.ranking_batch_size', 40)
            ->call('save')
            ->assertHasNoErrors();

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertTrue($row->isKindEnabled('lead_ranking'));
        $this->assertTrue($row->leadRankingAvailable());
        $this->assertSame('Dinners for 20-30 people in Leipzig.', $row->lead_ranking_profile);
        $this->assertSame(40, $row->rankingBatchSize());
    }

    public function test_operator_saves_client_profiles_case_insensitively(): void
    {
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();
        \App\Models\Lead::factory()->create(['client_name' => 'Acme']);
        \App\Models\Lead::factory()->create(['client_name' => 'acme']);
        \App\Models\Lead::factory()->create(['client_name' => 'Northwind']);

        $component = Livewire::actingAs($op)->test(AiSettingsPage::class);
        $names = array_column($component->get('clientProfiles'), 'client_name');
        $this->assertSame(['Acme', 'Northwind'], $names);

        $component->set('clientProfiles.0.profile', 'Vegan menus only.')
            ->call('saveClientProfiles')
            ->assertHasNoErrors();

        $this->assertSame('Vegan menus only.', \App\Models\ClientAiProfile::textFor(Tenant::DEFAULT_ID, 'ACME'));
        $this->assertNull(\App\Models\ClientAiProfile::textFor(Tenant::DEFAULT_ID, 'Northwind'));

        // Blank clears the row again.
        Livewire::actingAs($op)->test(AiSettingsPage::class)
            ->set('clientProfiles.0.profile', '')
            ->call('saveClientProfiles');
        $this->assertSame(0, \App\Models\ClientAiProfile::count());
    }

    public function test_rank_now_is_operator_only_and_reports_the_count(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config()->set('lodgely.ai.enabled', true);
        $op = $this->setupTenantAndOperator();

        // Gates off → explanatory status, nothing queued.
        $this->actingAs($op)->post('/settings/ai/rank-leads')
            ->assertRedirect(route('settings.ai'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'turned off'));

        $row = AiSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->provider = 'openai_compatible';
        $row->kinds_enabled = ['lead_ranking' => true];
        $row->lead_data_consent = true;
        $row->save();
        \App\Models\Lead::factory()->count(3)->create(['priority_source' => null, 'ai_ranked_at' => null, 'duplicate_flag' => false]);

        $this->actingAs($op)->post('/settings/ai/rank-leads')
            ->assertRedirect(route('settings.ai'))
            ->assertSessionHas('status', 'Queued 3 lead(s) for AI ranking.');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RankLeadWithAi::class, 3);

        config()->set('lodgely.ai.enabled', false);
        $this->actingAs($op)->post('/settings/ai/rank-leads')->assertNotFound();
        config()->set('lodgely.ai.enabled', true);

        // Fresh session for the second user — AuthenticateSession logs out a
        // session whose password hash belongs to someone else.
        $this->flushSession();
        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
        $this->actingAs($client)->post('/settings/ai/rank-leads')->assertForbidden();
    }
}
