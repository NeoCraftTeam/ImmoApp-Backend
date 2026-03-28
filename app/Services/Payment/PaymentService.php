<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Events\PaymentFailed;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central orchestrator for all payment operations.
 *
 * Delegates to the configured gateway. Supports automatic fallback to a secondary
 * gateway when the primary fails (e.g. Flutterwave → FedaPay).
 */
final readonly class PaymentService
{
    public function __construct(private PaymentGatewayInterface $gateway, private ?PaymentGatewayInterface $fallbackGateway = null) {}

    /**
     * Create a pending payment record and obtain the checkout link.
     *
     * @param  array{
     *     amount: float,
     *     currency?: string,
     *     type: string,
     *     payment_method?: string,
     *     phone_number?: string,
     *     ad_id?: string|null,
     *     agency_id?: string|null,
     *     plan_id?: string|null,
     *     period?: string|null,
     *     description?: string,
     *     meta?: array<string, mixed>
     * } $data
     * @return array{payment: Payment, link: string, tx_ref: string, gateway: string}
     */
    public function createPayment(User $user, array $data): array
    {
        $txRef = 'KH-'.strtoupper(Str::random(12));
        $currency = $data['currency'] ?? config('payment.default_currency', 'XAF');
        $meta = array_merge($data['meta'] ?? [], [
            'payment_type' => $data['type'],
            'user_id' => $user->id,
            'ad_id' => $data['ad_id'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'period' => $data['period'] ?? null,
        ]);

        $redirectUrl = config('payment.gateways.flutterwave.redirect_url')
            ?: config('app.frontend_url', config('app.url')).'/payment/callback';

        $gatewayPayload = [
            'amount' => $data['amount'],
            'currency' => $currency,
            'email' => $user->email,
            'phone' => $data['phone_number'] ?? ($user->phone_number ?? ''),
            'name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: $user->email,
            'tx_ref' => $txRef,
            'redirect_url' => $redirectUrl,
            'description' => $data['description'] ?? 'Paiement KeyHome',
            'payment_method' => $data['payment_method'] ?? 'flutterwave',
            'meta' => $meta,
        ];

        [$result, $usedGateway] = $this->initiateWithFallback($gatewayPayload);

        $payment = DB::transaction(function () use ($data, $txRef, $result, $user, $usedGateway): Payment {
            $payment = new Payment;
            $payment->type = PaymentType::from((string) $data['type']);
            $payment->amount = (int) round((float) $data['amount']);
            $payment->transaction_id = $txRef;
            $payment->payment_method = PaymentMethod::from((string) ($data['payment_method'] ?? 'flutterwave'));
            $payment->user_id = $user->id;
            $payment->status = PaymentStatus::PENDING;
            $payment->gateway = PaymentGateway::from($usedGateway->getName());
            $payment->payment_link = $result['link'];
            $payment->phone_number = $data['phone_number'] ?? null;
            $payment->ad_id = $data['ad_id'] ?? null;
            $payment->agency_id = $data['agency_id'] ?? null;
            $payment->plan_id = $data['plan_id'] ?? null;
            $payment->period = $data['period'] ?? null;
            $payment->save();

            PaymentInitiated::dispatch($payment);

            return $payment;
        });

        Log::info('Payment created', [
            'payment_id' => $payment->id,
            'gateway' => $usedGateway->getName(),
            'amount' => $data['amount'],
            'user_id' => $user->id,
            'tx_ref' => $txRef,
        ]);

        return [
            'payment' => $payment,
            'link' => $result['link'],
            'tx_ref' => $txRef,
            'gateway' => $usedGateway->getName(),
        ];
    }

    /**
     * Verify a payment by its internal tx_ref and update its status.
     */
    public function verifyByTxRef(string $txRef): Payment
    {
        $payment = Payment::where('transaction_id', $txRef)
            ->where('gateway', $this->gateway->getName())
            ->firstOrFail();

        return $this->syncPaymentStatus($payment);
    }

    /**
     * Verify a payment model instance and sync its status.
     *
     * Uses a DB lock to prevent race conditions with concurrent webhook processing.
     */
    public function syncPaymentStatus(Payment $payment): Payment
    {
        if ($payment->isTerminal()) {
            return $payment;
        }

        if (!$payment->transaction_id) {
            return $payment;
        }

        $result = $this->gateway->verify($payment->transaction_id);

        $expectedCurrency = config('payment.default_currency', 'XAF');

        return DB::transaction(function () use ($payment, $result, $expectedCurrency): Payment {
            /** @var Payment $locked */
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($locked->isTerminal()) {
                return $locked;
            }

            if ($result['status'] === 'success') {
                $paidAmount = (float) $result['amount'];
                $paidCurrency = (string) $result['currency'];

                if (abs($paidAmount - (float) $locked->amount) > 0.01 || strcasecmp($paidCurrency, (string) $expectedCurrency) !== 0) {
                    Log::critical('Payment amount/currency mismatch', [
                        'payment_id' => $locked->id,
                        'expected_amount' => $locked->amount,
                        'received_amount' => $paidAmount,
                        'expected_currency' => $expectedCurrency,
                        'received_currency' => $paidCurrency,
                    ]);

                    $locked->forceFill([
                        'status' => PaymentStatus::FAILED,
                        'gateway_response' => $result['raw'],
                    ])->save();

                    PaymentFailed::dispatch($locked->fresh() ?? $locked);

                    return $locked->fresh() ?? $locked;
                }

                $updateData = [
                    'status' => PaymentStatus::SUCCESS,
                    'gateway_response' => $result['raw'],
                ];

                // Update payment_method from gateway resolution (e.g. orange_money, mobile_money, card)
                if (!empty($result['payment_method'])) {
                    $updateData['payment_method'] = PaymentMethod::tryFrom($result['payment_method']);
                }

                $locked->forceFill($updateData)->save();

                Log::info('Payment verified as success', [
                    'payment_id' => $locked->id,
                    'gateway' => $locked->gateway,
                ]);

                PaymentSucceeded::dispatch($locked->fresh() ?? $locked);
            } elseif ($result['status'] === 'cancelled') {
                $locked->forceFill([
                    'status' => PaymentStatus::CANCELLED,
                    'gateway_response' => $result['raw'],
                ])->save();

                PaymentFailed::dispatch($locked->fresh() ?? $locked);
            } elseif ($result['status'] === 'failed') {
                $locked->forceFill([
                    'status' => PaymentStatus::FAILED,
                    'gateway_response' => $result['raw'],
                ])->save();

                PaymentFailed::dispatch($locked->fresh() ?? $locked);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Process an incoming webhook from a specific gateway.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed> Normalised webhook data from the gateway's handleWebhook
     */
    public function processWebhook(array $payload, array $headers, string $gatewayName): array
    {
        $gateway = $this->resolveGateway($gatewayName);
        $data = $gateway->handleWebhook($payload, $headers);

        $txRef = $data['tx_ref'];
        $payment = Payment::where('transaction_id', $txRef)
            ->where('gateway', $gatewayName)
            ->lockForUpdate()
            ->first();

        if (!$payment) {
            Log::warning('Webhook: payment not found', ['tx_ref' => $txRef, 'gateway' => $gatewayName]);

            return $data;
        }

        if ($payment->isTerminal()) {
            Log::info('Webhook ignoré: Paiement #'.$payment->id.' déjà traité (status: '.$payment->status->value.').');

            return $data;
        }

        $expectedCurrency = config('payment.default_currency', 'XAF');

        if ($data['status'] === 'success') {
            $paidAmount = (float) $data['amount'];
            $paidCurrency = (string) $data['currency'];

            if (abs($paidAmount - (float) $payment->amount) > 0.01 || strcasecmp($paidCurrency, (string) $expectedCurrency) !== 0) {
                Log::critical('Webhook: amount/currency mismatch', [
                    'payment_id' => $payment->id,
                    'expected_amount' => $payment->amount,
                    'received_amount' => $paidAmount,
                    'expected_currency' => $expectedCurrency,
                    'received_currency' => $paidCurrency,
                ]);

                $payment->forceFill([
                    'status' => PaymentStatus::FAILED,
                    'gateway_response' => $data['raw'],
                ])->save();

                PaymentFailed::dispatch($payment->fresh() ?? $payment);

                return $data;
            }

            $webhookUpdate = [
                'status' => PaymentStatus::SUCCESS,
                'gateway_response' => $data['raw'],
            ];

            // Update payment_method from gateway resolution (e.g. orange_money, mobile_money, card)
            if (!empty($data['payment_method'])) {
                $webhookUpdate['payment_method'] = PaymentMethod::tryFrom($data['payment_method']);
            }

            $payment->forceFill($webhookUpdate)->save();

            Log::info('Webhook: payment succeeded', ['payment_id' => $payment->id]);
            PaymentSucceeded::dispatch($payment->fresh() ?? $payment);
        } elseif ($data['status'] === 'cancelled') {
            $payment->forceFill([
                'status' => PaymentStatus::CANCELLED,
                'gateway_response' => $data['raw'],
            ])->save();

            Log::info('Webhook: payment cancelled', ['payment_id' => $payment->id]);
            PaymentFailed::dispatch($payment->fresh() ?? $payment);
        } elseif ($data['status'] === 'failed') {
            $payment->forceFill([
                'status' => PaymentStatus::FAILED,
                'gateway_response' => $data['raw'],
            ])->save();

            Log::info('Webhook: payment failed', ['payment_id' => $payment->id]);
            PaymentFailed::dispatch($payment->fresh() ?? $payment);
        }

        return $data;
    }

    public function getGatewayName(): string
    {
        return $this->gateway->getName();
    }

    /**
     * Resolve the authoritative server-side price for a payment type.
     *
     * @param  array<string, mixed>  $validated
     */
    public function resolveAmountForType(string $type, array $validated): ?float
    {
        return match ($type) {
            'credit' => $this->resolveCreditAmount($validated['plan_id'] ?? null),
            'subscription' => $this->resolveSubscriptionAmount($validated['plan_id'] ?? null, $validated['period'] ?? 'monthly'),
            default => null,
        };
    }

    private function resolveCreditAmount(?string $packageId): ?float
    {
        if (!$packageId) {
            return null;
        }

        $package = PointPackage::where('id', $packageId)->where('is_active', true)->first();

        return $package ? (float) $package->price : null;
    }

    private function resolveSubscriptionAmount(?string $planId, string $period): ?float
    {
        if (!$planId) {
            return null;
        }

        $plan = SubscriptionPlan::where('id', $planId)->where('is_active', true)->first();

        if (!$plan) {
            return null;
        }

        return $period === 'yearly' && $plan->price_yearly
            ? (float) $plan->price_yearly
            : (float) $plan->price;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array{link: string, tx_ref: string, status: string, gateway: string}, 1: PaymentGatewayInterface}
     */
    private function initiateWithFallback(array $payload): array
    {
        try {
            return [$this->gateway->initiate($payload), $this->gateway];
        } catch (PaymentGatewayException $e) {
            if ($this->fallbackGateway === null) {
                throw $e;
            }

            Log::warning('Primary payment gateway failed, trying fallback', [
                'primary' => $this->gateway->getName(),
                'fallback' => $this->fallbackGateway->getName(),
                'error' => $e->getMessage(),
            ]);

            return [$this->fallbackGateway->initiate($payload), $this->fallbackGateway];
        }
    }

    private function resolveGateway(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'flutterwave' => app(FlutterwavePaymentService::class),
            'fedapay' => app(FedaPayPaymentService::class),
            default => throw new \InvalidArgumentException("Gateway [{$name}] not supported."),
        };
    }
}
