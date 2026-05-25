<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Payment\CreditController;
use App\Http\Controllers\Api\V1\Payment\InvoiceController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Payment\PaymentMethodController;
use App\Http\Controllers\Api\V1\Payment\PromoCodeController;
use App\Http\Controllers\Api\V1\Payment\RefundController;
use App\Http\Controllers\Api\V1\Payment\StripePaymentMethodController;
use App\Http\Controllers\Api\V1\Payment\SubscriptionController;
use App\Http\Controllers\Api\V1\ResendWebhookController;
use Illuminate\Support\Facades\Route;

// --- PAYMENT METHODS CATALOGUE (public) ---
// Returns the list of methods currently enabled by the admin gate.
// Consumed by `<PaymentModal>` to render a dynamic selector instead of
// hard-coding the four PaymentMethod cases.
Route::get('/payments/methods', [PaymentMethodController::class, 'index'])
    ->middleware('throttle:60,1');

// --- PUBLIC PAYMENT STATUS (auth-less, status-only) ---
// Allows the post-checkout callback page (`/payment/return`,
// `/credits/callback`,
// `/payment-success`) to poll a payment's status WITHOUT requiring a
// session — critical when the user's session cookie was lost during the
// cross-origin Flutterwave redirect (Safari / Firefox SameSite=Lax + cross-domain).
// Returns ONLY `{ status: 'pending'|'success'|'failed'|'cancelled'|'unknown' }`,
// no PII, no amount, no payment method. Knowing the `tx_ref` only grants
// the right to read the status, never to modify or read details.
// Throttled to 60 req/min/IP — enough for an aggressive callback poll
// (1 req/s for 60 s) but not enough to brute-force `tx_ref` space.
Route::get('/payments/{txRef}/public-status', [PaymentController::class, 'publicStatus'])
    ->where('txRef', 'KH-[A-Za-z0-9]+')
    ->middleware('throttle:60,1');

// --- PAYMENTS — multi-gateway webhooks ---
// GeniusPay + legacy Flutterwave (`{gateway}` placeholder).
Route::post('/webhooks/{gateway}', [PaymentController::class, 'handleWebhook'])
    ->where('gateway', 'geniuspay|flutterwave')
    ->middleware('throttle:payments.webhook');

// Stripe: dedicated endpoint — verifies `Stripe-Signature` against the
// raw body. Cashier's default `/stripe/webhook` is disabled in
// AppServiceProvider via `Cashier::ignoreRoutes()` so we own the URL.
Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook'])
    ->middleware('throttle:payments.webhook');

// E-2 : Resend — bounce and complaint webhook.
// Verifies Svix HMAC signature (RESEND_WEBHOOK_SECRET). Populates
// email_suppressions so suppressed addresses are never emailed again.
Route::post('/webhooks/resend', [ResendWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');

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
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
        ->whereUuid('payment')
        ->middleware('throttle:10,1');
    Route::get('/payments/export', [PaymentController::class, 'export'])
        ->middleware('throttle:10,1');
    Route::get('/payments/refunds', [RefundController::class, 'userRefunds'])
        ->middleware('throttle:30,1');
    Route::post('/payments/{payment}/refund-request', [RefundController::class, 'requestRefund'])
        ->whereUuid('payment')
        ->middleware('throttle:5,1');

    // --- STRIPE SAVED CARDS ---
    // CRUD on the authenticated user's Stripe Customer payment methods.
    // The Customer record is created lazily by Cashier
    // (`createOrGetStripeCustomer()`) the first time the user opts in to
    // save a card OR requests a SetupIntent from the profile page.
    Route::prefix('payments/stripe')->group(function (): void {
        Route::get('/payment-methods', [StripePaymentMethodController::class, 'index'])
            ->middleware('throttle:60,1');
        Route::delete('/payment-methods/{paymentMethod}', [StripePaymentMethodController::class, 'destroy'])
            ->where('paymentMethod', 'pm_[A-Za-z0-9_]+')
            ->middleware('throttle:30,1');
        Route::post('/payment-methods/{paymentMethod}/set-default', [StripePaymentMethodController::class, 'setDefault'])
            ->where('paymentMethod', 'pm_[A-Za-z0-9_]+')
            ->middleware('throttle:30,1');
        Route::post('/setup-intent', [StripePaymentMethodController::class, 'setupIntent'])
            ->middleware('throttle:30,1');
        Route::post('/payment-methods/notify-added', [StripePaymentMethodController::class, 'notifyCardAdded'])
            ->middleware('throttle:10,1');
    });
});

// --- REFUNDS (admin) ---
// OWASP A05 — align with the rest of the admin surface (registerAdmin,
// users create/destroy, ad-types, cities, quarters) which all require
// `mfa.admin` when an admin has MFA enrolled. Refunds move money, so
// skipping MFA here was a consistency gap: a stolen admin token/session
// would suffice. `mfa.admin` is a no-op for non-admin (never reached
// here thanks to `can:admin-access`, kept for defense in depth).
Route::middleware(['auth:sanctum', 'can:admin-access', 'mfa.admin'])->prefix('admin/payments/{payment}')->group(function (): void {
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
