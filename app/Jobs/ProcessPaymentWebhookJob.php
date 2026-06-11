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
 * Processes a verified payment-gateway webhook payload asynchronously.
 *
 * The webhook controller verifies the signature and extracts the tx_ref
 * synchronously (fast), then hands off to this job so PHP-FPM workers are
 * freed immediately and the gateway (GeniusPay / Stripe) always receives a
 * timely 200.
 */
final class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 3 times with exponential back-off (60 s, 120 s, 240 s). */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 240];

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly string $txRef,
        public readonly string $gateway,
        public readonly array $rawPayload,
        public readonly ?string $ingestRequestId = null,
        public readonly ?string $ingestCorrelationId = null,
    ) {}

    public function handle(HandlePostPaymentActions $postPaymentActions): void
    {
        $previousSharedContext = Log::sharedContext();
        $this->applyIngestLogContext();
        try {
            DB::transaction(function () use ($postPaymentActions): void {
                $payment = Payment::where('transaction_id', $this->txRef)
                    ->where('gateway', $this->gateway)
                    ->lockForUpdate()
                    ->first();

                if (!$payment || !$payment->isPaid()) {
                    return;
                }

                Log::info('[Webhook] Processing post-payment actions', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'tx_ref' => $this->txRef,
                    'gateway' => $this->gateway,
                ]);

                $postPaymentActions->execute($payment, $this->rawPayload);
            });
        } finally {
            Log::flushSharedContext();
            if ($previousSharedContext !== []) {
                Log::withContext($previousSharedContext);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[Webhook] ProcessPaymentWebhookJob failed', [
            'tx_ref' => $this->txRef,
            'gateway' => $this->gateway,
            'request_id' => $this->ingestRequestId,
            'correlation_id' => $this->ingestCorrelationId ?? $this->ingestRequestId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function applyIngestLogContext(): void
    {
        $requestId = $this->ingestRequestId !== null && $this->ingestRequestId !== ''
            ? $this->ingestRequestId
            : null;
        $correlationId = $this->ingestCorrelationId !== null && $this->ingestCorrelationId !== ''
            ? $this->ingestCorrelationId
            : $requestId;

        if ($requestId !== null) {
            Log::withContext([
                'request_id' => $requestId,
                'correlation_id' => $correlationId ?? $requestId,
            ]);
        }
    }
}
