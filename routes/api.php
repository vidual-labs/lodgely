<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{token}', [WebhookController::class, 'receive'])
    ->middleware('throttle:60,1')
    ->name('api.webhooks.receive');
