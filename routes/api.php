<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TapWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Tap Payment Gateway Webhooks
|--------------------------------------------------------------------------
|
| These routes handle webhook notifications from Tap payment gateway.
| They are excluded from CSRF verification and rate limiting.
|
*/

Route::prefix('webhooks/tap')->name('webhooks.tap.')->group(function () {
    Route::post('refunds', [TapWebhookController::class, 'handleRefundWebhook'])
        ->name('refunds');
    
    Route::post('payments', [TapWebhookController::class, 'handlePaymentWebhook'])
        ->name('payments');
});
