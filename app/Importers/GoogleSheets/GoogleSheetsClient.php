<?php

namespace App\Importers\GoogleSheets;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Google Sheets v4 REST API.
 *
 * Holds the OAuth + HTTP plumbing in one place so future code (e.g. a leads
 * source backed by a sheet, or an ad-hoc data fetcher) can just call
 * fetchValues() without re-implementing the refresh-token dance.
 *
 * Access tokens are exchanged from a long-lived refresh token and cached for
 * just under the 1 h Google validity window, mirroring GoogleAdsSource.
 */
class GoogleSheetsClient
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const API_BASE = 'https://sheets.googleapis.com/v4';

    /**
     * Fetch a rectangular range of cell values from a spreadsheet.
     *
     * Returns the raw `values` array from the Sheets API: a list of rows,
     * each row a list of cell values (strings unless valueRenderOption is
     * customized later).
     *
     * @return array<int, array<int, mixed>>
     */
    public function fetchValues(string $spreadsheetId, string $range): array
    {
        $config = $this->config();
        $timeout = (int) ($config['http_timeout_sec'] ?? 30);

        $accessToken = $this->accessToken($config, $timeout);

        $url = sprintf(
            '%s/spreadsheets/%s/values/%s',
            self::API_BASE,
            rawurlencode($spreadsheetId),
            rawurlencode($range),
        );

        $response = Http::timeout($timeout)
            ->retry(2, 500, throw: false)
            ->withToken($accessToken)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Google Sheets values fetch failed (%d): %s',
                $response->status(),
                substr($response->body(), 0, 400),
            ));
        }

        $values = $response->json('values');

        return is_array($values) ? $values : [];
    }

    /**
     * Build the consent URL the operator visits to grant access. `state` ties
     * the redirect back to the original session so the callback can verify
     * the request originated here.
     */
    public function buildAuthorizationUrl(string $redirectUri, string $state): string
    {
        $config = $this->config();
        $clientId = trim((string) ($config['client_id'] ?? ''));

        if ($clientId === '') {
            throw new RuntimeException(
                'Google Sheets: LODGELY_GOOGLE_SHEETS_CLIENT_ID must be set before authorizing.'
            );
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', (array) ($config['scopes'] ?? [])),
            // `offline` + `consent` is what makes Google issue a refresh_token
            // every time. Without these the second authorize returns access-only.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange the authorization code from the OAuth redirect for a token
     * payload. Returns the raw decoded JSON, which contains at least
     * `access_token` and (when `prompt=consent` was used) `refresh_token`.
     *
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        $config = $this->config();
        $timeout = (int) ($config['http_timeout_sec'] ?? 30);

        $clientId = trim((string) ($config['client_id'] ?? ''));
        $clientSecret = trim((string) ($config['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException(
                'Google Sheets: client_id and client_secret must be set before completing the OAuth flow.'
            );
        }

        $response = Http::timeout($timeout)
            ->retry(2, 500, throw: false)
            ->asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Google OAuth code exchange failed (%d): %s',
                $response->status(),
                substr($response->body(), 0, 300),
            ));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Exchange the refresh token for a fresh access token. Cached for just
     * under the 1 h Google validity window so each call reuses the same
     * token instead of refreshing on every API hit.
     *
     * @param  array<string, mixed>  $config
     */
    public function accessToken(array $config, int $timeout): string
    {
        $clientId = trim((string) ($config['client_id'] ?? ''));
        $clientSecret = trim((string) ($config['client_secret'] ?? ''));
        $refreshToken = trim((string) ($config['refresh_token'] ?? ''));

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException(
                'Google Sheets: OAuth client_id, client_secret and refresh_token must all be set. '
                .'Run the authorize flow at /settings/google-sheets/connect to obtain a refresh token.'
            );
        }

        $cacheKey = 'lodgely.google_sheets.access_token.'.sha1($clientId.'|'.$refreshToken);

        return Cache::remember($cacheKey, 3300, function () use ($clientId, $clientSecret, $refreshToken, $timeout) {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->asForm()
                ->acceptJson()
                ->post(self::TOKEN_URL, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Google OAuth token refresh failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 300),
                ));
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google OAuth token refresh returned an unexpected payload shape.');
            }

            return $token;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return (array) config('lodgely.importers.google_sheets');
    }
}
