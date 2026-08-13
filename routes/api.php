<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Throttled per endpoint token rather than per caller IP: the token is the
// thing being protected, and the caller's apparent IP is spoofable whenever
// trusted proxies are left wide open. See AppServiceProvider::bootRateLimiters().
Route::post('/webhooks/{token}', [WebhookController::class, 'receive'])
    ->middleware('throttle:webhook')
    ->name('api.webhooks.receive');
