<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PromoCodeController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

// --- PAYMENTS (Flutterwave / FedaPay) ---
Route::post('/webhooks/flutterwave', [PaymentController::class, 'flutterwaveWebhook'])
    ->middleware('throttle:120,1');
Route::post('/webhooks/fedapay', [PaymentController::class, 'fedapayWebhook'])
    ->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/payments/initialize/{ad}', [CreditController::class, 'unlock'])
        ->middleware('throttle:30,1');
    Route::post('/payments/initiate_payment', [PaymentController::class, 'flutterwaveInitiate'])
        ->middleware('throttle:5,1');
    Route::post('/payments/verify_payment', [PaymentController::class, 'flutterwaveVerify'])
        ->middleware('throttle:30,1');
    Route::post('/payments/cancel_payment', [PaymentController::class, 'flutterwaveCancel'])
        ->middleware('throttle:10,1');
    Route::get('/payments/history', [PaymentController::class, 'history'])
        ->middleware('throttle:60,1');
});

// --- REFUNDS (admin) ---
Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin/payments/{payment}')->group(function (): void {
    Route::post('/refund', [RefundController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/refunds', [RefundController::class, 'index'])
        ->middleware('throttle:30,1');
});

// --- SUBSCRIPTIONS (agencies) ---
Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
Route::middleware('auth:sanctum')->prefix('subscriptions')->group(function (): void {
    Route::get('/current', [SubscriptionController::class, 'current']);
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])
        ->middleware('throttle:5,1');
    Route::post('/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('throttle:5,1');
    Route::patch('/auto-renew', [SubscriptionController::class, 'toggleAutoRenew'])
        ->middleware('throttle:10,1');
    Route::get('/history', [SubscriptionController::class, 'history']);
});

// --- CREDITS / POINTS ---
Route::get('/credits/packages', [CreditController::class, 'packages']);
Route::middleware('auth:sanctum')->prefix('credits')->group(function (): void {
    Route::get('/balance', [CreditController::class, 'balance']);
    Route::post('/purchase/{package}', [CreditController::class, 'purchase'])
        ->middleware('throttle:10,1');
    Route::post('/verify-purchase', [CreditController::class, 'verifyPurchase'])
        ->middleware('throttle:30,1');
});

// --- INVOICES ---
Route::middleware('auth:sanctum')->prefix('invoices')->group(function (): void {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::get('/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
});

// --- PROMO CODES ---
Route::middleware('auth:sanctum')->post('/promo-codes/validate', [PromoCodeController::class, 'validate'])
    ->middleware('throttle:20,1');
