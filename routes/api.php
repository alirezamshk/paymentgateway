<?php

use App\Http\Controllers\Api\V1\GatewayCallbackController;
use App\Http\Controllers\Api\V1\MerchantController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Client API: HMAC authenticated, rate limited per IP (before auth) and per client (after auth).
    Route::middleware(['throttle:api-ip', 'auth.client', 'throttle:client-api'])->group(function () {
        Route::post('payments', [PaymentController::class, 'store'])->name('api.payments.store');
        Route::get('payments/{paymentId}', [PaymentController::class, 'show'])->name('api.payments.show');
        Route::post('payments/{paymentId}/verify', [PaymentController::class, 'verify'])->name('api.payments.verify');
        Route::post('payments/{paymentId}/cancel', [PaymentController::class, 'cancel'])->name('api.payments.cancel');

        Route::get('merchants', [MerchantController::class, 'index'])->name('api.merchants.index');
        Route::post('merchants', [MerchantController::class, 'store'])->name('api.merchants.store');
        Route::get('merchants/{merchantId}', [MerchantController::class, 'show'])->name('api.merchants.show');
        Route::patch('merchants/{merchantId}', [MerchantController::class, 'update'])->name('api.merchants.update');
    });

    // PSP -> customer browser -> Tech-Kala. Not client-authenticated; outcome comes from PSP verify.
    Route::match(['get', 'post'], 'gateways/{provider}/callback/{payment}', GatewayCallbackController::class)
        ->where('provider', '[a-z0-9_-]+')
        ->middleware('throttle:callbacks')
        ->name('gateways.callback');
});
