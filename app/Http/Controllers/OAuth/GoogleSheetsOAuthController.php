<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Importers\GoogleSheets\GoogleSheetsClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operator-only flow that walks an installed-app OAuth handshake for the
 * Google Sheets API and surfaces the resulting refresh token, ready to be
 * pasted into `.env` as `LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN`.
 *
 * lodgely deliberately keeps long-lived credentials in environment config
 * rather than the DB — this matches the existing GoogleAdsSource pattern and
 * keeps secrets out of automated backups.
 */
class GoogleSheetsOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'google_sheets_oauth_state';

    public function connect(Request $request, GoogleSheetsClient $client): RedirectResponse|View
    {
        abort_unless($request->user()?->isOperator(), 403);

        try {
            $state = Str::random(40);
            $request->session()->put(self::STATE_SESSION_KEY, $state);

            $url = $client->buildAuthorizationUrl($this->redirectUri(), $state);

            return redirect()->away($url);
        } catch (Throwable $e) {
            return view('oauth.google-sheets.error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function callback(Request $request, GoogleSheetsClient $client): View
    {
        abort_unless($request->user()?->isOperator(), 403);

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === null || $expectedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return view('oauth.google-sheets.error', [
                'message' => __('OAuth state mismatch. Restart the authorize flow from the beginning.'),
            ]);
        }

        if ($error = $request->query('error')) {
            return view('oauth.google-sheets.error', [
                'message' => __('Google returned an error: :error', ['error' => (string) $error]),
            ]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return view('oauth.google-sheets.error', [
                'message' => __('Google did not return an authorization code.'),
            ]);
        }

        try {
            $payload = $client->exchangeAuthorizationCode($code, $this->redirectUri());
        } catch (Throwable $e) {
            return view('oauth.google-sheets.error', [
                'message' => $e->getMessage(),
            ]);
        }

        $refreshToken = is_string($payload['refresh_token'] ?? null) ? $payload['refresh_token'] : null;

        return view('oauth.google-sheets.callback', [
            'refreshToken' => $refreshToken,
            'scope' => (string) ($payload['scope'] ?? ''),
        ]);
    }

    private function redirectUri(): string
    {
        return route('settings.google-sheets.callback');
    }
}
