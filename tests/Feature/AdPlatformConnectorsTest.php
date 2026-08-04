<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\AdMetricsImporter;
use App\Domain\Reporting\Services\CampaignRollup;
use App\Importers\Meta\MetaAdsSource;
use App\Models\AdPlatformSetting;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdPlatformConnectorsTest extends TestCase
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

    public function test_client_connectors_do_not_inherit_the_env_ad_account(): void
    {
        config([
            'lodgely.reporting.meta.ad_account_id'  => 'act_env_default',
            'lodgely.reporting.meta.access_token'   => 'env-token',
            'lodgely.reporting.google.customer_id'  => '1112223333',
        ]);

        $default = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $client = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Acme Roofing');

        // The shared default connector still falls back to .env, so installs
        // that configure credentials there keep working untouched.
        $this->assertSame('act_env_default', $default->effectiveMetaAccountId());
        $this->assertSame('1112223333', $default->effectiveGoogleCustomerId());

        // A per-client connector must not: inheriting the tenant-wide account
        // would fetch the *same* ad account a second time and tag it with the
        // client's name, double-counting every campaign in the operator's
        // tenant-wide rollups.
        $this->assertSame('', $client->effectiveMetaAccountId());
        $this->assertSame('', $client->effectiveGoogleCustomerId());
        $this->assertFalse($client->isMetaConnected());

        // Shared credentials (tokens, OAuth app) are still inherited — one
        // business manager legitimately spans several ad accounts.
        $this->assertSame('env-token', $client->effectiveMetaAccessToken());

        // Once the connector carries its own account id, it is used as given.
        $client->meta_ad_account_id = 'act_client_999';
        $this->assertSame('act_client_999', $client->effectiveMetaAccountId());
        $this->assertTrue($client->isMetaConnected());
    }

    public function test_operator_can_create_and_configure_a_client_connector(): void
    {
        $op = $this->operator();

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.store'), ['client_name' => 'Acme Roofing'])
            ->assertRedirect();

        $connector = AdPlatformSetting::query()->where('client_name', 'Acme Roofing')->first();
        $this->assertNotNull($connector);
        $this->assertSame(Tenant::DEFAULT_ID, $connector->tenant_id);

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.update', $connector), [
                'meta_enabled' => '1',
                'meta_ad_account_id' => '999888',
                'meta_currency' => 'EUR',
                'meta_access_token' => 'acme-token',
            ])
            ->assertRedirect(route('settings.ad-platforms.connectors.edit', $connector));

        $connector->refresh();
        $this->assertTrue($connector->meta_enabled);
        $this->assertSame('999888', $connector->meta_ad_account_id);
        $this->assertSame('acme-token', $connector->metaAccessToken());
    }

    public function test_cannot_create_a_second_connector_with_the_same_client_name(): void
    {
        $op = $this->operator();
        AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Acme Roofing');

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.store'), ['client_name' => 'acme roofing'])
            ->assertRedirect(route('settings.ad-platforms'))
            ->assertSessionHas('connectorError');

        $this->assertSame(1, AdPlatformSetting::where('client_name', 'Acme Roofing')->count());
    }

    public function test_client_cannot_manage_connectors(): void
    {
        $client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $this->actingAs($client)
            ->post(route('settings.ad-platforms.connectors.store'), ['client_name' => 'Acme'])
            ->assertForbidden();
    }

    public function test_deleting_a_connector_removes_it_but_not_the_default(): void
    {
        $op = $this->operator();
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Acme Roofing');
        $default = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);

        $this->actingAs($op)
            ->delete(route('settings.ad-platforms.connectors.destroy', $connector))
            ->assertRedirect(route('settings.ad-platforms'));

        $this->assertNull(AdPlatformSetting::find($connector->id));
        $this->assertNotNull(AdPlatformSetting::find($default->id));
    }

    public function test_cannot_delete_the_default_connector_via_the_connector_route(): void
    {
        $op = $this->operator();
        $default = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);

        $this->actingAs($op)
            ->delete(route('settings.ad-platforms.connectors.destroy', $default))
            ->assertNotFound();

        $this->assertNotNull(AdPlatformSetting::find($default->id));
    }

    public function test_fetch_pulls_from_every_enabled_meta_connector_and_tags_client_name(): void
    {
        $default = AdPlatformSetting::forTenant(Tenant::DEFAULT_ID);
        $default->meta_enabled = true;
        $default->meta_ad_account_id = '111';
        $default->setMetaAccessToken('default-token');
        $default->save();

        $acme = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Acme Roofing');
        $acme->meta_enabled = true;
        $acme->meta_ad_account_id = '222';
        $acme->setMetaAccessToken('acme-token');
        $acme->save();

        Http::fake([
            'graph.facebook.com/*act_111*' => Http::response(['data' => [
                ['campaign_id' => 'C_DEFAULT', 'campaign_name' => 'Shared campaign', 'impressions' => 100, 'clicks' => 5, 'spend' => '10.00'],
            ]], 200),
            'graph.facebook.com/*act_222*' => Http::response(['data' => [
                ['campaign_id' => 'C_ACME', 'campaign_name' => 'Acme campaign', 'impressions' => 200, 'clicks' => 8, 'spend' => '20.00'],
            ]], 200),
        ]);

        // preserve_keys=false: fetch() delegates to each connector's fetchOne()
        // generator via `yield from`, and each one's internal auto-index
        // restarts at 0 — with keys preserved a second connector's row would
        // silently clobber the first's in the array (this doesn't affect the
        // real ingestion pipeline, which consumes the generator via a plain
        // foreach and never looks at keys).
        $rows = iterator_to_array((new MetaAdsSource())->fetch(Tenant::DEFAULT_ID, new \DateTimeImmutable('2026-05-17')), false);

        $this->assertCount(2, $rows);
        $byClient = collect($rows)->keyBy('campaignId');
        $this->assertNull($byClient['C_DEFAULT']->clientName);
        $this->assertSame('Acme Roofing', $byClient['C_ACME']->clientName);
    }

    public function test_importer_persists_client_name_on_ad_spend_reports(): void
    {
        config()->set('lodgely.reporting.sources', []);

        $acme = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Acme Roofing');
        $acme->meta_enabled = true;
        $acme->meta_ad_account_id = '222';
        $acme->setMetaAccessToken('acme-token');
        $acme->save();

        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['campaign_id' => 'C_ACME', 'campaign_name' => 'Acme campaign', 'impressions' => 200, 'clicks' => 8, 'spend' => '20.00'],
        ]], 200)]);

        app(AdMetricsImporter::class)->run(Tenant::DEFAULT_ID, new \DateTimeImmutable('today'), 1);

        $row = AdSpendReport::where('campaign_id', 'C_ACME')->first();
        $this->assertNotNull($row);
        $this->assertSame('Acme Roofing', $row->client_name);
    }

    public function test_client_view_scopes_ad_spend_to_their_own_connector(): void
    {
        // Acme's own connector-tagged spend, plus untagged (default-connector)
        // spend attributed to Globex via the existing campaign heuristic.
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => 'Acme Roofing',
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => 'C_ACME',
            'campaign_name' => 'Acme campaign',
            'impressions' => 1000,
            'clicks' => 50,
            'spend_cents' => 5000,
            'currency' => 'USD',
            'platform_leads' => 3,
        ]);
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => null,
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => 'C_GLOBEX',
            'campaign_name' => 'Globex campaign',
            'impressions' => 2000,
            'clicks' => 90,
            'spend_cents' => 9000,
            'currency' => 'USD',
            'platform_leads' => 4,
        ]);

        $client = User::create([
            'name' => 'Acme User', 'email' => 'acme@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
        UserLeadScope::create(['user_id' => $client->id, 'client_name' => 'Acme Roofing']);

        $rollup = new CampaignRollup();
        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        // The operator-side client filter already scopes by direct client_name
        // match now, in addition to the legacy campaign heuristic.
        $kpis = $rollup->kpis(Tenant::DEFAULT_ID, $from, $to, null, 'Acme Roofing');
        $this->assertSame(5000, $kpis['total_spend_cents']);
    }
}
