<?php

use App\Http\Controllers\Api\LeadMetadataController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Throttled per endpoint token rather than per caller IP: the token is the
// thing being protected, and the caller's apparent IP is spoofable whenever
// trusted proxies are left wide open. See AppServiceProvider::bootRateLimiters().
Route::post('/webhooks/{token}', [WebhookController::class, 'receive'])
    ->middleware('throttle:webhook')
    ->name('api.webhooks.receive');

// Read-only lead-metadata change feed for vigilo (LG-15). Bearer token with the
// lead-metadata:read scope; throttled per token like the webhook above.
Route::get('/v1/lead-metadata', [LeadMetadataController::class, 'index'])
    ->middleware(['throttle:api-token', 'api.token:lead-metadata:read'])
    ->name('api.v1.lead-metadata');
