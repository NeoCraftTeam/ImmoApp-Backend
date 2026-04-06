<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\HandlePostPaymentActions;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes a verified Flutterwave webhook payload asynchronously.
 *
 * The webhook controller verifies the signature and extracts the tx_ref
 * synchronously (fast), then hands off to this job so PHP-FPM workers
 * are freed immediately and Flutterwave always receives a timely 200.
 */
final class ProcessFlutterwaveWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 3 times with exponential back-off (60 s, 120 s, 240 s). */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 240];

    public function __construct(
        public readonly string $txRef,
        public readonly string $gateway,
        public readonly array  $rawPayload,
    ) {}

    public function handle(HandlePostPaymentActions $postPaymentActions): void
    {
        DB::transaction(function () use ($postPaymentActions): void {
            $payment = Payment::where('transaction_id', $this->txRef)
                ->where('gateway', $this->gateway)
                ->lockForUpdate()
                ->first();

            if (!$payment || !$payment->isPaid()) {
                return;
            }

            Log::info('[Webhook] Processing post-payment actions', [
                'tx_ref'  => $this->txRef,
                'gateway' => $this->gateway,
            ]);

            $postPaymentActions->execute($payment, $this->rawPayload);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[Webhook] ProcessFlutterwaveWebhookJob failed', [
            'tx_ref'  => $this->txRef,
            'gateway' => $this->gateway,
            'error'   => $exception->getMessage(),
        ]);
    }
}
