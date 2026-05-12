<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\HandlePostPaymentActions;
use App\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth that turns a successful Payment into the
 * domain-level side effects (credits granted, subscription activated,
 * boost applied, …).
 *
 * Runs synchronously so the user's balance is up-to-date by the time the
 * `PaymentSucceeded::dispatch()` site returns — this matters in the
 * Stripe off-session path where `PaymentService::createPayment()` settles
 * the intent in ~150 ms and the frontend immediately polls
 * `verify-purchase` for the updated balance.
 *
 * Defence-in-depth: `HandlePostPaymentActions::execute()` is **idempotent**
 * (it locks the buyer row and skips when a `PointTransaction` already
 * exists for this payment), so concurrent firings — listener, webhook job,
 * manual verify endpoint — can never double-credit.
 */
final readonly class FulfilPaymentOnSuccess
{
    public function __construct(
        private HandlePostPaymentActions $actions,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        try {
            $this->actions->execute(
                $event->payment,
                (array) ($event->payment->gateway_response ?? []),
            );
        } catch (\Throwable $e) {
            // Never break the dispatching code path. Failures here are
            // surfaced via logs + retried by the verify endpoints which
            // also invoke `HandlePostPaymentActions` idempotently.
            Log::error('FulfilPaymentOnSuccess: échec exécution actions post-paiement', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
