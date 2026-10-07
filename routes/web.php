<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Web\PaymentPageController;
use App\Http\Controllers\Web\SandboxPspController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->view('pay.message', ['title' => 'Tech-Kala Payment Service', 'message' => 'Central payment service.']));

Route::get('pay/{payment}', [PaymentPageController::class, 'show'])
    ->middleware('throttle:payment-page')
    ->name('pay.show');

if (config('gateways.sandbox_enabled') && ! app()->isProduction()) {
    Route::get('sandbox-psp/{token}', [SandboxPspController::class, 'show'])->name('sandbox.psp.show');
    Route::post('sandbox-psp/{token}', [SandboxPspController::class, 'complete'])->name('sandbox.psp.complete');
}

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:admin-login');
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('clients', [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('clients/{client}/edit', [Admin\ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::post('clients/{client}/toggle', [Admin\ClientController::class, 'toggle'])->name('clients.toggle');
        Route::post('clients/{client}/credentials', [Admin\ClientController::class, 'issueCredential'])->name('clients.credentials.issue');
        Route::post('clients/{client}/credentials/{credential}/revoke', [Admin\ClientController::class, 'revokeCredential'])->name('clients.credentials.revoke');
        Route::post('clients/{client}/webhook-secret', [Admin\ClientController::class, 'rotateWebhookSecret'])->name('clients.webhook-secret');

        Route::get('merchants', [Admin\MerchantController::class, 'index'])->name('merchants.index');
        Route::get('merchants/create', [Admin\MerchantController::class, 'create'])->name('merchants.create');
        Route::post('merchants', [Admin\MerchantController::class, 'store'])->name('merchants.store');
        Route::get('merchants/{merchant}/edit', [Admin\MerchantController::class, 'edit'])->name('merchants.edit');
        Route::put('merchants/{merchant}', [Admin\MerchantController::class, 'update'])->name('merchants.update');
        Route::post('merchants/{merchant}/toggle', [Admin\MerchantController::class, 'toggle'])->name('merchants.toggle');
        Route::post('merchants/{merchant}/default', [Admin\MerchantController::class, 'makeDefault'])->name('merchants.default');
        Route::post('merchants/{merchant}/test', [Admin\MerchantController::class, 'test'])->name('merchants.test');

        Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [Admin\PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');

        Route::get('webhooks', [Admin\WebhookController::class, 'index'])->name('webhooks.index');
        Route::get('webhooks/{delivery}', [Admin\WebhookController::class, 'show'])->name('webhooks.show');
        Route::post('webhooks/{delivery}/retry', [Admin\WebhookController::class, 'retry'])->name('webhooks.retry');

        Route::get('providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
        Route::get('providers/{provider}/edit', [Admin\ProviderController::class, 'edit'])->name('providers.edit');
        Route::put('providers/{provider}', [Admin\ProviderController::class, 'update'])->name('providers.update');
        Route::post('providers/{provider}/toggle', [Admin\ProviderController::class, 'toggle'])->name('providers.toggle');

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
