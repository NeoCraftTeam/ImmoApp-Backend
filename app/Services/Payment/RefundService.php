<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Enums\RefundStatus;
use App\Exceptions\PaymentGatewayException;
use App\Mail\RefundConfirmationMail;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orchestrates the full refund lifecycle: gateway call, side-effect reversal, notifications.
 */
final readonly class RefundService
{
    /**
     * Process a refund for a successful payment.
     *
     * @param  array{reason: string, amount?: float|null, admin_note?: string|null}  $data
     */
    public function processRefund(Payment $payment, User $admin, array $data): Refund
    {
        $this->validateRefundable($payment);

        $refundAmount = $data['amount'] ?? (float) $payment->amount;
        $isPartial = isset($data['amount']) && abs($refundAmount - (float) $payment->amount) > 0.01;

        $refund = DB::transaction(function () use ($payment, $admin, $data, $refundAmount, $isPartial): Refund {
            /** @var Payment $locked */
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            $this->validateRefundable($locked);

            $refund = Refund::create([
                'payment_id' => $locked->id,
                'user_id' => $locked->user_id,
                'processed_by' => $admin->id,
                'amount' => $refundAmount,
                'currency' => config('payment.default_currency', 'XAF'),
                'reason' => $data['reason'],
                'is_partial' => $isPartial,
                'admin_note' => $data['admin_note'] ?? null,
            ]);

            $refund->forceFill(['status' => RefundStatus::Processing])->save();

            return $refund;
        });

        try {
            $gatewayResult = $this->processGatewayRefund($payment, $refundAmount);

            DB::transaction(function () use ($refund, $payment, $gatewayResult, $isPartial): void {
                $refund->forceFill([
                    'status' => RefundStatus::Completed,
                    'gateway_refund_id' => $gatewayResult['refund_id'],
                    'gateway_response' => $gatewayResult['raw'],
                ])->save();

                if (!$isPartial) {
                    $payment->forceFill(['status' => PaymentStatus::REFUNDED])->save();
                }
            });

            $this->reverseSideEffects($payment, $refund);

            $this->notifyUser($refund);

            Log::info('Refund completed', [
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'amount' => $refundAmount,
                'admin_id' => $refund->processed_by,
            ]);
        } catch (PaymentGatewayException $e) {
            $refund->forceFill(['status' => RefundStatus::Failed])->save();

            Log::error('Refund gateway call failed', [
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $refund->fresh() ?? $refund;
    }

    /**
     * @return array{refund_id: string, status: string, amount_refunded: float, raw: array<string, mixed>}
     */
    private function processGatewayRefund(Payment $payment, float $amount): array
    {
        $gateway = $this->resolveGateway($payment);

        $gatewayTxId = $this->extractGatewayTransactionId($payment);

        return $gateway->refund($gatewayTxId, $amount);
    }

    private function validateRefundable(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::SUCCESS) {
            throw new \InvalidArgumentException('Seuls les paiements réussis peuvent être remboursés.');
        }

        $existingRefund = $payment->refunds()
            ->whereIn('status', [RefundStatus::Completed, RefundStatus::Processing])
            ->where('is_partial', false)
            ->exists();

        if ($existingRefund) {
            throw new \InvalidArgumentException('Ce paiement a déjà été remboursé intégralement.');
        }
    }

    private function reverseSideEffects(Payment $payment, Refund $refund): void
    {
        try {
            $payment->loadMissing('user');

            match ($payment->type) {
                PaymentType::CREDIT => $this->reverseCredits($payment),
                PaymentType::SUBSCRIPTION => $this->reverseSubscription($payment),
                PaymentType::UNLOCK, PaymentType::BOOST => null,
            };

            $refund->forceFill(['side_effects_reversed' => true])->save();
        } catch (\Throwable $e) {
            Log::warning('Side effect reversal failed', [
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function reverseCredits(Payment $payment): void
    {
        $creditTx = $payment->user->pointTransactions()
            ->where('payment_id', $payment->id)
            ->where('type', PointTransactionType::PURCHASE)
            ->first();

        if (!$creditTx) {
            return;
        }

        $pointsToDeduct = abs($creditTx->points);

        DB::transaction(function () use ($payment, $pointsToDeduct): void {
            /** @var User $freshUser */
            $freshUser = User::query()
                ->lockForUpdate()
                ->findOrFail($payment->user_id);

            $freshUser->decrement('point_balance', $pointsToDeduct);

            PointTransaction::create([
                'user_id' => $payment->user_id,
                'type' => PointTransactionType::REFUND,
                'points' => -$pointsToDeduct,
                'description' => "Remboursement — annulation de l'achat #{$payment->transaction_id}",
            ]);
        });
    }

    private function reverseSubscription(Payment $payment): void
    {
        $subscription = Subscription::where('payment_id', $payment->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return;
        }

        $subscription->cancel('Remboursement du paiement #'.$payment->transaction_id);
    }

    private function notifyUser(Refund $refund): void
    {
        $refund->loadMissing(['user', 'payment']);

        if ($refund->user?->email) {
            Mail::to($refund->user->email)
                ->queue(new RefundConfirmationMail($refund));
        }
    }

    private function extractGatewayTransactionId(Payment $payment): string
    {
        $gatewayResponse = $payment->gateway_response ?? [];

        $id = $gatewayResponse['id'] ?? $gatewayResponse['transaction_id'] ?? null;

        if (!$id) {
            throw new \InvalidArgumentException(
                "Impossible de trouver l'ID de transaction gateway pour le paiement #{$payment->id}."
            );
        }

        return (string) $id;
    }

    private function resolveGateway(Payment $payment): PaymentGatewayInterface
    {
        return match ($payment->gateway?->value) {
            'flutterwave' => new FlutterwavePaymentService,
            default => throw new \InvalidArgumentException("Gateway non supporté: {$payment->gateway?->value}"),
        };
    }
}
