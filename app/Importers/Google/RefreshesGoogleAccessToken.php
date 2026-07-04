<?php

namespace App\Importers\Google;

use App\Models\AdPlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared OAuth token exchange for the Google Ads adapters. Exchange the
 * refresh token for a fresh access token, cached for just under the 1 h
 * Google validity window so each scheduled run (and both the campaign-level
 * and creative-level adapters) reuse the same token instead of refreshing on
 * every request.
 */
trait RefreshesGoogleAccessToken
{
    protected function accessToken(AdPlatformSetting $settings, int $timeout): string
    {
        $clientId = trim($settings->effectiveGoogleClientId());
        $clientSecret = trim($settings->effectiveGoogleClientSecret());
        $refreshToken = trim($settings->effectiveGoogleRefreshToken());

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException(
                'Google Ads source: OAuth client id, client secret and refresh token must all be set. Connect Google Ads in Settings → Ad platforms.'
            );
        }

        $cacheKey = 'lodgely.google_ads.access_token.'.sha1($clientId.'|'.$refreshToken);

        return Cache::remember($cacheKey, 3300, function () use ($clientId, $clientSecret, $refreshToken, $timeout) {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->asForm()
                ->acceptJson()
                ->post('https://oauth2.googleapis.com/token', [
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
}
