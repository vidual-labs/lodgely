<?php

namespace App\Http\Controllers;

use App\Importers\Google\GoogleAdsSource;
use App\Importers\Meta\MetaAdsSource;
use App\Models\AdPlatformSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Native HTML form endpoints for managing *additional* Meta/Google Ads
 * connectors assigned to a specific client, so an operator can run more than
 * one ad account per platform (one per client) instead of the single shared
 * default connector.
 *
 * Deliberately not Livewire: adding/removing rows from a dynamic list is
 * exactly the pattern that has silently dropped clicks elsewhere in this app
 * (see CLAUDE.md — inbox filter card, user-edit modal, backup restore). A
 * plain form → controller → redirect can't fail that way. The default
 * connector (client_name null) keeps using the existing Livewire
 * AdPlatformsPage form; this controller only manages the per-client ones.
 */
class AdPlatformConnectorController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->guardOperator($request);

        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.ad-platforms')
                ->with('connectorError', $validator->errors()->first());
        }

        $clientName = trim((string) $request->input('client_name'));

        $exists = AdPlatformSetting::query()
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->whereRaw('LOWER(client_name) = ?', [mb_strtolower($clientName)])
            ->exists();

        if ($exists) {
            return redirect()->route('settings.ad-platforms')
                ->with('connectorError', __('A connector for ":client" already exists.', ['client' => $clientName]));
        }

        $connector = AdPlatformSetting::forClient(Tenant::DEFAULT_ID, $clientName);

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with('connectorNotice', __('Connector for ":client" created. Add its credentials below.', ['client' => $clientName]));
    }

    public function edit(Request $request, AdPlatformSetting $connector): \Illuminate\View\View
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $appUrl = rtrim((string) config('app.url'), '/');

        return view('settings.ad-platform-connector-edit', [
            'connector' => $connector,
            'appUrl' => $appUrl,
            'appUrlIsHttps' => str_starts_with($appUrl, 'https://'),
            'googleConnectUrl' => route('settings.ad-platforms.google.connect', ['connector' => $connector->id]),
            'googleRedirectUri' => $appUrl.'/settings/ad-platforms/google/callback',
        ]);
    }

    public function update(Request $request, AdPlatformSetting $connector): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $data = $request->validate([
            'internal_label' => ['nullable', 'string', 'max:255'],

            'meta_enabled' => ['boolean'],
            'meta_ad_account_id' => ['nullable', 'string', 'max:64'],
            'meta_currency' => ['nullable', 'string', 'max:8'],
            'meta_api_version' => ['nullable', 'string', 'max:16'],
            'meta_access_token' => ['nullable', 'string', 'max:1000'],

            'google_enabled' => ['boolean'],
            'google_customer_id' => ['nullable', 'string', 'max:32'],
            'google_login_customer_id' => ['nullable', 'string', 'max:32'],
            'google_api_version' => ['nullable', 'string', 'max:16'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_developer_token' => ['nullable', 'string', 'max:255'],
        ]);

        $connector->internal_label = trim((string) ($data['internal_label'] ?? '')) ?: null;

        $connector->meta_enabled = (bool) ($data['meta_enabled'] ?? false);
        $connector->meta_ad_account_id = trim((string) ($data['meta_ad_account_id'] ?? ''));
        $connector->meta_currency = trim((string) ($data['meta_currency'] ?? '')) ?: 'USD';
        $connector->meta_api_version = trim((string) ($data['meta_api_version'] ?? '')) ?: 'v21.0';
        if (! empty($data['meta_access_token'])) {
            $connector->setMetaAccessToken(trim((string) $data['meta_access_token']));
        }

        $connector->google_enabled = (bool) ($data['google_enabled'] ?? false);
        $connector->google_customer_id = trim((string) ($data['google_customer_id'] ?? ''));
        $connector->google_login_customer_id = trim((string) ($data['google_login_customer_id'] ?? ''));
        $connector->google_api_version = trim((string) ($data['google_api_version'] ?? '')) ?: 'v18';

        $newClientId = trim((string) ($data['google_client_id'] ?? ''));
        $clientIdChanged = $newClientId !== (string) $connector->google_client_id;
        $connector->google_client_id = $newClientId;

        if (! empty($data['google_client_secret'])) {
            $connector->setGoogleClientSecret(trim((string) $data['google_client_secret']));
        }
        if (! empty($data['google_developer_token'])) {
            $connector->setGoogleDeveloperToken(trim((string) $data['google_developer_token']));
        }

        if ($clientIdChanged || ! empty($data['google_client_secret'])) {
            $connector->setGoogleRefreshToken(null);
        }

        $connector->save();

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with('connectorNotice', __('Connector settings saved.'));
    }

    public function destroy(Request $request, AdPlatformSetting $connector): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $clientName = $connector->client_name;
        $connector->delete();

        return redirect()->route('settings.ad-platforms')
            ->with('connectorNotice', __('Connector for ":client" removed.', ['client' => $clientName]));
    }

    public function disconnectGoogle(Request $request, AdPlatformSetting $connector): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $connector->setGoogleRefreshToken(null);
        $connector->save();

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with('connectorNotice', __('Disconnected from Google Ads.'));
    }

    /**
     * Set (or clear) this connector's Meta brand filter. Always resolves the
     * typed Page id against the Graph API and shows the resolved name back
     * before saving — the id is what's actually matched against at fetch
     * time, the name is only ever cached for display.
     */
    public function resolveMetaPage(Request $request, AdPlatformSetting $connector): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $pageId = trim((string) $request->input('meta_page_id'));

        if ($pageId === '') {
            $connector->meta_page_id = null;
            $connector->meta_page_name = null;
            $connector->save();

            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorNotice', __('Meta brand filter cleared — this connector now covers the whole ad account.'));
        }

        $token = trim($connector->effectiveMetaAccessToken());
        $apiVer = trim($connector->effectiveMetaApiVersion());
        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        if ($token === '') {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('Save a Meta access token first, then set the brand filter.'));
        }

        try {
            $name = (new MetaAdsSource())->resolvePageName($pageId, $token, $apiVer, $timeout);
        } catch (\Throwable $e) {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('Could not resolve that Page id: :error', ['error' => $e->getMessage()]));
        }

        if ($name === null) {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('No Page found for id ":id". Double-check the id and try again.', ['id' => $pageId]));
        }

        $connector->meta_page_id = $pageId;
        $connector->meta_page_name = $name;
        $connector->save();

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with('connectorNotice', __('Brand filter set: only ads published as ":name" (Page :id) will be included.', ['name' => $name, 'id' => $pageId]));
    }

    /**
     * Set (or clear) this connector's Google Business Name asset filter.
     * Same resolve-then-save flow as the Meta Page filter.
     */
    public function resolveGoogleBusinessName(Request $request, AdPlatformSetting $connector): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        $assetId = trim((string) $request->input('google_business_name_asset_id'));

        if ($assetId === '') {
            $connector->google_business_name_asset_id = null;
            $connector->google_business_name_asset_name = null;
            $connector->save();

            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorNotice', __('Google brand filter cleared — this connector now covers the whole ad account.'));
        }

        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        if (trim($connector->effectiveGoogleDeveloperToken()) === '' || trim($connector->effectiveGoogleRefreshToken()) === '') {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('Connect Google Ads first, then set the brand filter.'));
        }

        try {
            $name = (new GoogleAdsSource())->resolveBusinessNameAssetName($connector, $assetId, $timeout);
        } catch (\Throwable $e) {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('Could not resolve that asset id: :error', ['error' => $e->getMessage()]));
        }

        if ($name === null) {
            return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
                ->with('connectorError', __('No Business Name asset found for id ":id". Double-check the id and try again.', ['id' => $assetId]));
        }

        $connector->google_business_name_asset_id = $assetId;
        $connector->google_business_name_asset_name = $name;
        $connector->save();

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with('connectorNotice', __('Brand filter set: only campaigns using the ":name" business name asset will be included.', ['name' => $name]));
    }

    public function test(Request $request, AdPlatformSetting $connector, string $platform): RedirectResponse
    {
        $this->guardOperator($request);
        $this->guardOwnsConnector($connector);

        if (! in_array($platform, ['meta', 'google'], true)) {
            abort(404);
        }

        $key = $platform === 'meta' ? 'connectorMetaTestResult' : 'connectorGoogleTestResult';

        try {
            $date = new \DateTimeImmutable('yesterday');
            $source = $platform === 'meta' ? new MetaAdsSource() : new GoogleAdsSource();
            $snapshots = iterator_to_array($source->fetchOne($connector, $date));

            $message = 'success:'.__(':count campaign(s) returned for :date.', [
                'count' => count($snapshots),
                'date' => $date->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            $message = 'error:'.$e->getMessage();
        }

        return redirect()->route('settings.ad-platforms.connectors.edit', $connector)
            ->with($key, $message);
    }

    private function guardOperator(Request $request): void
    {
        abort_unless($request->user()?->isOperator(), 403);
    }

    private function guardOwnsConnector(AdPlatformSetting $connector): void
    {
        abort_unless((int) $connector->tenant_id === Tenant::DEFAULT_ID, 404);
        abort_if($connector->client_name === null, 404);
    }
}
