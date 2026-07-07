<?php

namespace Tests\Feature;

use App\Importers\Google\GoogleAdsSource;
use App\Importers\Meta\MetaAdsSource;
use App\Models\AdPlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers scoping a single connector to one brand within an ad account that
 * actually serves several — matched by the platform's permanent id (Meta
 * Page id / Google Business Name asset id), never the customer-facing name.
 */
class AdPlatformBrandFilterTest extends TestCase
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

    // ------------------------------------------------------------- Google

    public function test_google_fetch_scopes_to_campaigns_using_the_business_name_asset(): void
    {
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->google_customer_id = '1234567890';
        $connector->google_developer_token_encrypted = null;
        $connector->setGoogleDeveloperToken('dev-token');
        $connector->google_business_name_asset_id = '999';
        $connector->setGoogleRefreshToken('refresh');
        $connector->setGoogleClientSecret('secret');
        $connector->google_client_id = 'client-id';
        $connector->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3600], 200),
            'googleads.googleapis.com/*' => Http::sequence()
                ->push(['results' => [
                    ['assetGroup' => ['campaign' => 'customers/1234567890/campaigns/555']],
                ]], 200)
                ->push(['results' => [
                    [
                        'campaign' => ['id' => '555', 'name' => 'Brand A campaign'],
                        'customer' => ['currencyCode' => 'USD'],
                        'metrics' => ['impressions' => 100, 'clicks' => 5, 'costMicros' => 500000, 'conversions' => 2],
                    ],
                ]], 200),
        ]);

        $rows = iterator_to_array((new GoogleAdsSource())->fetchOne($connector, new \DateTimeImmutable('2026-05-17')), false);

        $this->assertCount(1, $rows);
        $this->assertSame('555', $rows[0]->campaignId);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'googleAds:search')
                && str_contains($body['query'] ?? '', 'campaign.id IN (555)');
        });
    }

    public function test_google_fetch_returns_nothing_when_asset_is_not_linked_to_any_campaign(): void
    {
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->google_customer_id = '1234567890';
        $connector->setGoogleDeveloperToken('dev-token');
        $connector->google_business_name_asset_id = '999';
        $connector->setGoogleRefreshToken('refresh');
        $connector->setGoogleClientSecret('secret');
        $connector->google_client_id = 'client-id';
        $connector->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3600], 200),
            'googleads.googleapis.com/*' => Http::response(['results' => []], 200),
        ]);

        $rows = iterator_to_array((new GoogleAdsSource())->fetchOne($connector, new \DateTimeImmutable('2026-05-17')), false);

        $this->assertSame([], $rows);
        // Only the asset_group_asset lookup ran — no campaign query fired.
        Http::assertSentCount(2); // token refresh + the one asset lookup
    }

    public function test_operator_resolves_and_saves_the_google_business_name_filter(): void
    {
        $op = $this->operator();
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->google_customer_id = '1234567890';
        $connector->setGoogleDeveloperToken('dev-token');
        $connector->setGoogleRefreshToken('refresh');
        $connector->setGoogleClientSecret('secret');
        $connector->google_client_id = 'client-id';
        $connector->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3600], 200),
            'googleads.googleapis.com/*' => Http::response(['results' => [
                ['asset' => ['businessNameAsset' => ['businessName' => 'Brand A Co']]],
            ]], 200),
        ]);

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.google.business-name-filter', $connector), [
                'google_business_name_asset_id' => '999',
            ])
            ->assertRedirect(route('settings.ad-platforms.connectors.edit', $connector))
            ->assertSessionHas('connectorNotice');

        $connector->refresh();
        $this->assertSame('999', $connector->google_business_name_asset_id);
        $this->assertSame('Brand A Co', $connector->google_business_name_asset_name);
    }

    public function test_resolving_an_unknown_google_asset_id_does_not_save_it(): void
    {
        $op = $this->operator();
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->google_customer_id = '1234567890';
        $connector->setGoogleDeveloperToken('dev-token');
        $connector->setGoogleRefreshToken('refresh');
        $connector->setGoogleClientSecret('secret');
        $connector->google_client_id = 'client-id';
        $connector->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3600], 200),
            'googleads.googleapis.com/*' => Http::response(['results' => []], 200),
        ]);

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.google.business-name-filter', $connector), [
                'google_business_name_asset_id' => '404404',
            ])
            ->assertSessionHas('connectorError');

        $this->assertNull($connector->fresh()->google_business_name_asset_id);
    }

    // --------------------------------------------------------------- Meta

    public function test_meta_fetch_aggregates_matching_ads_up_to_campaign_level(): void
    {
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->meta_ad_account_id = '111';
        $connector->setMetaAccessToken('token');
        $connector->meta_page_id = 'PAGE_A';
        $connector->save();

        Http::fake([
            'graph.facebook.com/*/ads*' => Http::response(['data' => [
                ['id' => 'AD_1', 'creative' => ['object_story_spec' => ['page_id' => 'PAGE_A']]],
                ['id' => 'AD_2', 'creative' => ['object_story_spec' => ['page_id' => 'PAGE_A']]],
                ['id' => 'AD_3', 'creative' => ['object_story_spec' => ['page_id' => 'PAGE_B']]],
            ]], 200),
            'graph.facebook.com/*/insights*' => Http::response(['data' => [
                ['ad_id' => 'AD_1', 'campaign_id' => 'C1', 'campaign_name' => 'Brand A campaign', 'impressions' => 100, 'clicks' => 5, 'spend' => '10.00'],
                ['ad_id' => 'AD_2', 'campaign_id' => 'C1', 'campaign_name' => 'Brand A campaign', 'impressions' => 50, 'clicks' => 2, 'spend' => '5.00'],
                ['ad_id' => 'AD_3', 'campaign_id' => 'C2', 'campaign_name' => 'Brand B campaign', 'impressions' => 999, 'clicks' => 99, 'spend' => '99.00'],
            ]], 200),
        ]);

        $rows = iterator_to_array((new MetaAdsSource())->fetchOne($connector, new \DateTimeImmutable('2026-05-17')), false);

        $this->assertCount(1, $rows);
        $this->assertSame('C1', $rows[0]->campaignId);
        $this->assertSame(150, $rows[0]->impressions);
        $this->assertSame(7, $rows[0]->clicks);
        $this->assertSame(1500, $rows[0]->spendCents);
        $this->assertSame('Brand A', $rows[0]->clientName);
    }

    public function test_meta_fetch_returns_nothing_when_no_ad_publishes_as_the_filtered_page(): void
    {
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->meta_ad_account_id = '111';
        $connector->setMetaAccessToken('token');
        $connector->meta_page_id = 'PAGE_NOBODY_USES';
        $connector->save();

        Http::fake([
            'graph.facebook.com/*/ads*' => Http::response(['data' => [
                ['id' => 'AD_1', 'creative' => ['object_story_spec' => ['page_id' => 'PAGE_A']]],
            ]], 200),
        ]);

        $rows = iterator_to_array((new MetaAdsSource())->fetchOne($connector, new \DateTimeImmutable('2026-05-17')), false);

        $this->assertSame([], $rows);
        Http::assertSentCount(1); // only the ads lookup — no insights call fired.
    }

    public function test_operator_resolves_and_saves_the_meta_page_filter(): void
    {
        $op = $this->operator();
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->meta_ad_account_id = '111';
        $connector->setMetaAccessToken('token');
        $connector->save();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['name' => 'Brand A Page'], 200),
        ]);

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.meta.page-filter', $connector), [
                'meta_page_id' => 'PAGE_A',
            ])
            ->assertRedirect(route('settings.ad-platforms.connectors.edit', $connector))
            ->assertSessionHas('connectorNotice');

        $connector->refresh();
        $this->assertSame('PAGE_A', $connector->meta_page_id);
        $this->assertSame('Brand A Page', $connector->meta_page_name);
    }

    public function test_clearing_the_meta_page_filter_removes_it_without_an_api_call(): void
    {
        $op = $this->operator();
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');
        $connector->meta_ad_account_id = '111';
        $connector->setMetaAccessToken('token');
        $connector->meta_page_id = 'PAGE_A';
        $connector->meta_page_name = 'Brand A Page';
        $connector->save();

        Http::fake();

        $this->actingAs($op)
            ->post(route('settings.ad-platforms.connectors.meta.page-filter', $connector), [
                'meta_page_id' => '',
            ])
            ->assertSessionHas('connectorNotice');

        $connector->refresh();
        $this->assertNull($connector->meta_page_id);
        $this->assertNull($connector->meta_page_name);
        Http::assertNothingSent();
    }

    public function test_client_cannot_resolve_brand_filters(): void
    {
        $client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, 'Brand A');

        $this->actingAs($client)
            ->post(route('settings.ad-platforms.connectors.meta.page-filter', $connector), ['meta_page_id' => 'PAGE_A'])
            ->assertForbidden();
    }
}
