<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PromoCodeController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

// --- PAYMENT METHODS CATALOGUE (public) ---
// Returns the list of methods currently enabled by the admin gate.
// Consumed by `<PaymentModal>` to render a dynamic selector instead of
// hard-coding the four PaymentMethod cases.
Route::get('/payments/methods', [PaymentMethodController::class, 'index'])
    ->middleware('throttle:60,1');

// --- PAYMENTS — multi-gateway webhooks ---
// Flutterwave: legacy `{gateway}` placeholder (constraint = flutterwave).
Route::post('/webhooks/{gateway}', [PaymentController::class, 'handleWebhook'])
    ->where('gateway', 'flutterwave')
    ->middleware('throttle:payments.webhook');

// Stripe: dedicated endpoint — verifies `Stripe-Signature` against the
// raw body. Cashier's default `/stripe/webhook` is disabled in
// AppServiceProvider via `Cashier::ignoreRoutes()` so we own the URL.
Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook'])
    ->middleware('throttle:payments.webhook');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/payments/initialize/{ad}', [CreditController::class, 'unlock'])
        ->middleware('throttle:30,1');
    Route::post('/payments/initiate_payment', [PaymentController::class, 'initiate'])
        ->middleware('throttle:payments.initiate');
    Route::post('/payments/verify_payment', [PaymentController::class, 'verify'])
        ->middleware('throttle:payments.verify');
    Route::post('/payments/cancel_payment', [PaymentController::class, 'cancel'])
        ->middleware('throttle:payments.cancel');
    Route::get('/payments/history', [PaymentController::class, 'history'])
        ->middleware('throttle:payments.history');
    Route::get('/payments/export', [PaymentController::class, 'export'])
        ->middleware('throttle:10,1');
});

// --- REFUNDS (admin) ---
Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin/payments/{payment}')->group(function (): void {
    Route::post('/refund', [RefundController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/refunds', [RefundController::class, 'index'])
        ->middleware('throttle:30,1');
});

// --- SUBSCRIPTIONS (agencies) ---
Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans'])
    ->middleware('cdn.cache:1800');
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
Route::get('/credits/packages', [CreditController::class, 'packages'])
    ->middleware('cdn.cache:1800');
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
