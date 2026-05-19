<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Models\GoogleSheetsSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operator-only OAuth handshake for the Google Sheets API.
 *
 * The resulting refresh token is persisted in the database via
 * GoogleSheetsSetting, so operators configure credentials entirely through
 * the /settings/google-sheets UI without touching .env.
 */
class GoogleSheetsOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'google_sheets_oauth_state';

    public function connect(Request $request, GoogleSheetsClient $client): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        try {
            $state = Str::random(40);
            $request->session()->put(self::STATE_SESSION_KEY, $state);

            $url = $client->buildAuthorizationUrl($this->redirectUri(), $state);

            return redirect()->away($url);
        } catch (Throwable $e) {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', $e->getMessage());
        }
    }

    public function callback(Request $request, GoogleSheetsClient $client): RedirectResponse
    {
        abort_unless($request->user()?->isOperator(), 403);

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === null || $expectedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', __('OAuth state mismatch. Restart the authorize flow from the beginning.'));
        }

        if ($error = $request->query('error')) {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', __('Google returned an error: :error', ['error' => (string) $error]));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', __('Google did not return an authorization code.'));
        }

        try {
            $payload = $client->exchangeAuthorizationCode($code, $this->redirectUri());
        } catch (Throwable $e) {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', $e->getMessage());
        }

        $refreshToken = is_string($payload['refresh_token'] ?? null) ? $payload['refresh_token'] : null;

        if ($refreshToken === null) {
            return redirect()->route('settings.google-sheets')
                ->with('oauth_error', __('Google did not return a refresh token. Revoke access at https://myaccount.google.com/permissions and try again.'));
        }

        $row = GoogleSheetsSetting::forTenant(Tenant::DEFAULT_ID);
        $row->setRefreshToken($refreshToken);
        $row->save();

        // Bust the cached access token so the new refresh token takes effect
        // on the next API call without waiting for the 55-minute window.
        $cacheKey = 'lodgely.google_sheets.access_token.'.sha1($row->client_id.'|'.$refreshToken);
        Cache::forget($cacheKey);

        return redirect()->route('settings.google-sheets')
            ->with('oauth_success', __('Google Sheets connected successfully.'));
    }

    private function redirectUri(): string
    {
        // Build from APP_URL so the scheme always matches the operator's
        // configured public URL, even when PHP receives plain HTTP from a
        // reverse proxy (Caddy, nginx, Cloudflare). Using route() would
        // inherit the proxy-side HTTP scheme and make Google reject the
        // non-SSL redirect URI.
        return rtrim((string) config('app.url'), '/').'/settings/google-sheets/callback';
    }
}
