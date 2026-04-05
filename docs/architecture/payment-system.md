# KeyHome — Payment System Architecture

> **Last updated:** April 2026
> **Gateway:** Flutterwave (sole gateway — XAF/XOF markets)

---

## Strategy Pattern

```
PaymentController
       │
       ▼
PaymentService (orchestrator, injected via AppServiceProvider DI)
       │
       └──► PaymentGatewayInterface
                    │
                    └──► FlutterwavePaymentService (concrete implementation)
```

```php
// Contracts/PaymentGatewayInterface.php
interface PaymentGatewayInterface
{
    public function initiate(Payment $payment): InitiateResult;
    public function verify(string $transactionId): VerifyResult;
    public function refund(Payment $payment, int $amount): RefundResult;
}
```

Adding a new gateway (Wave, Stripe, etc.) requires:
1. Implement `PaymentGatewayInterface`
2. Add new case to `PaymentGateway` enum
3. Bind in `AppServiceProvider` — zero changes to `PaymentService`

---

## Payment Flow

### Initiation

```
POST /api/v1/payments/initiate
        │
        ├── Auth: Sanctum Bearer
        ├── PaymentRequest validation
        │
        ├── PaymentService::initiate()
        │   ├── Resolve amount SERVER-SIDE from PointPackage or SubscriptionPlan
        │   │   (client-sent amount IGNORED — prevents price tampering)
        │   ├── Create Payment record (status: pending)
        │   ├── event(new PaymentInitiated($payment))
        │   └── FlutterwavePaymentService::initiate()
        │       └── Returns Flutterwave payment link
        │
        └── Return: { payment_url, payment_id }
```

### Verification (Webhook)

```
POST /api/v1/webhooks/flutterwave
        │
        ├── Verify Flutterwave signature header
        ├── DB lock: Payment::lockForUpdate() — prevent double-processing
        ├── Skip if payment.status !== 'pending' (idempotency guard)
        │
        ├── FlutterwavePaymentService::verify()
        │
        ├── PaymentSucceeded / PaymentFailed event
        │
        └── HandlePostPaymentActions::handle()
            ├── Unlock ad contact (if type = unlock)
            ├── Credit PointTransaction (if type = credits)
            └── Activate SubscriptionPlan (if type = subscription)
```

---

## Critical Security Guarantees

| Risk | Mitigation |
|------|-----------|
| Price tampering | Amount resolved server-side from `PointPackage`/`SubscriptionPlan` ID |
| Double-spend | `lockForUpdate()` on Payment row during webhook verification |
| Replay attacks | `payment.status` idempotency check — skip if not `pending` |
| Webhook spoofing | Flutterwave signature header verified on every webhook call |

---

## Refund Flow

```
POST /api/v1/refunds/{payment}
        │
        ├── RefundRequest validation
        ├── Authorization: Admin or payment owner
        │
        ├── RefundService::process()
        │   ├── Create Refund record
        │   ├── FlutterwavePaymentService::refund()
        │   └── Update Payment.status = 'refunded'
        │
        └── Refund record returned
```

---

## Payment Types

| Type | `PaymentType` enum | Post-payment action |
|------|--------------------|---------------------|
| Ad unlock (contact reveal) | `unlock` | Creates `UnlockedAd` record |
| Credit purchase | `credits` | Credits `PointTransaction` to wallet |
| Subscription | `subscription` | Activates `Subscription` plan |
| Ad boost | `boost` | Applies `AdBoost` via `AdBoostService` |

---

## Data Model

```
payments
├── id (UUID)
├── user_id
├── gateway (string, not enum cast — future-proof)
├── transaction_id (Flutterwave reference)
├── status (pending / succeeded / failed / refunded)
├── type (PaymentType enum)
├── amount (integer, XAF/XOF centimes)
├── currency ('XAF' | 'XOF')
├── metadata (JSON — ad_id, package_id, plan_id)
└── timestamps

refunds
├── payment_id
├── amount
├── status (RefundStatus enum)
└── gateway_refund_id
```

---

## Environment Variables

```env
# All Flutterwave keys — never commit to git
FLW_PUBLIC_KEY=FLWPUBK_TEST_...       # Flutterwave public key
FLW_SECRET_KEY=FLWSECK_TEST_...       # ⚠️ Secret — server only
FLW_SECRET_HASH=...                   # Webhook signature verification
FLW_ENCRYPTION_KEY=...                # 3DES encryption key
```

---

## Testing

Flutterwave is mocked in tests — no real network calls:

```php
// In tests — PaymentGatewayInterface is bound to a FakePaymentGateway
// or Mockery mock
$this->mock(PaymentGatewayInterface::class, function ($mock) {
    $mock->shouldReceive('verify')->andReturn(new VerifyResult(true, 5000));
});
```

See `tests/Feature/Payment/` for the full payment test suite.
