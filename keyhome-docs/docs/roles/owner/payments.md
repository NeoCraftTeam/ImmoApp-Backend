---
sidebar_position: 6
title: "Payments"
---

# Payments

## Feature Description

The KeyHome payment system uses **Flutterwave** as the payment gateway, supporting mobile money (Orange Money, MTN MoMo), card payments, and other regional payment methods. Payments are used for:

1. **Ad Boost** — Promote a listing to the top of search results
2. **Credits / Points** — Purchase points to unlock contact information of landlords
3. **Subscriptions** — Buy subscription plans for premium features
4. **Pro Services** — Purchase ad-on professional services

---

## Payment Flow

```
Owner/Tenant initiates a purchase
 
 POST /payments/initiate_payment
 { type, payment_method, phone_number, ad_id?, plan_id?, period? }
 
 Response: { reference, payment_link, tx_ref, gateway, status }
 
 User redirected to payment_link (Flutterwave hosted page)
 OR inline payment for mobile money (OTP on phone)
 
 User completes payment → Flutterwave redirects to /payment-success?tx_ref=...
 
 POST /payments/verify_payment { tx_ref }
 
 On success: purchase activated (boost enabled, points credited, subscription active)
```

---

## Payment Types

| Type | `payment_type` | Description |
|---|---|---|
| Unlock contact | `unlock` | Deduct points or initiate payment to see owner contact |
| Ad Boost | `boost` | Promote listing for a set duration |
| Credit Package | `credit` | Buy point packages (e.g. 500 pts for 2,000 XOF) |
| Subscription | `subscription` | Purchase monthly/annual plan |

---

## Credit / Points System

Tenants use **points** (credits) to unlock landlord contact information. Points are purchased via the Flutterwave payment flow.

- **Unlock flow**: `POST /payments/initialize/:adId`
 - If `status === 'unlocked'` → contact info immediately visible (already had enough points)
 - If `status === 'insufficient_points'` → trigger credit purchase flow showing available packages
 - If `status === 'owner'` → user owns this ad (no unlock needed)
 - If `status === 'already_unlocked'` → previously unlocked

```typescript
// src/hooks/usePayment.ts
const { initiatePayment, isLoading, error } = usePayment();

const handleUnlock = async () => {
 const result = await paymentsService.initialize(adId);
 if (result.status === 'insufficient_points') {
 // Show credit packages to purchase
 openCreditModal(result.packages);
 }
};
```

---

## Ad Boost

Owners can boost their listings from the dashboard or `/owner/pro-services`:

```typescript
// src/services/owner.service.ts

// Boost with subscription plan (paid)
await ownerService.boostAd(adId, {
 plan_id: 'boost-plan-uuid',
 callback_url: window.location.origin + '/payment-success',
});

// Self-boost using existing credits (free if eligible)
await ownerService.selfBoostAd(adId, { duration_days: 7 });

// Remove boost
await ownerService.unboostAd(adId);

// Check boost status
const status = await ownerService.getBoostStatus(adId);
// { is_boosted: true, boost_expires_at: '2024-02-01T00:00:00Z' }
```

---

## Subscription Plans

Owners can subscribe to premium plans at `/owner/subscriptions`:

```typescript
// src/services/subscriptions.service.ts
const plans = await subscriptionsService.getPlans();
// GET /subscriptions/plans → SubscriptionPlan[]

const current = await subscriptionsService.getCurrent();
// GET /subscriptions/current → CurrentSubscription | null
```

---

## Payment History

Both tenants (at `/payments`) and owners (at `/owner/payments`) can view their payment history:

```typescript
// src/services/payments.service.ts
const history = await paymentsService.getHistory({ page: 1 });
// GET /payments/history → { data: PaymentHistoryItem[], meta }
```

### PaymentHistoryItem

```typescript
interface PaymentHistoryItem {
 id: string;
 reference: string; // Flutterwave transaction reference
 status: PaymentStatus; // pending | success | failed
 type: PaymentType; // unlock | subscription | boost | credit
 amount: number; // Amount in XOF/XAF
 gateway: PaymentGateway; // flutterwave | stripe
 ad: Ad | null; // Associated listing (for unlock/boost)
 created_at: string;
}
```

---

## Payment Success Page

After Flutterwave redirects back, the `/payment-success` page:
1. Reads `tx_ref` from the URL query parameters
2. Calls `POST /payments/verify_payment { tx_ref }`
3. Shows success/failure UI
4. Redirects the user to the appropriate page

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/payments/page.tsx` | Tenant payment history |
| `src/app/(owner)/owner/payments/page.tsx` | Owner payment settings & history |
| `src/app/(owner)/owner/subscriptions/page.tsx` | Subscription management |
| `src/app/(owner)/owner/pro-services/page.tsx` | Pro service purchases |
| `src/app/payment-success/page.tsx` | Flutterwave return URL handler |
| `src/components/payment/PaymentFlow.tsx` | Payment method selection UI |
| `src/components/payment/CreditPackages.tsx` | Credit package selection |
| `src/components/payment/UnlockAd.tsx` | Contact unlock component |
| `src/hooks/usePayment.ts` | Payment state management hook |
| `src/hooks/useTransactionStatus.ts` | Transaction status polling |
| `src/services/payments.service.ts` | All payment API calls |
| `src/services/subscriptions.service.ts` | Subscription API calls |
| `src/types/payment.ts` | Payment type definitions |

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `POST` | `/api/v1/payments/initialize/:adId` | — | `UnlockResponse` |
| `POST` | `/api/v1/payments/initiate_payment` | `FlutterwaveInitiatePayload` | `FlutterwaveInitiateResponse` |
| `POST` | `/api/v1/payments/verify_payment` | `{ tx_ref }` | `FlutterwaveVerifyResponse` |
| `POST` | `/api/v1/payments/cancel_payment` | `{ tx_ref }` | `{ message, status }` |
| `GET` | `/api/v1/payments/history` | `{ page }` | `{ data: PaymentHistoryItem[], meta }` |
| `GET` | `/api/v1/subscriptions/plans` | — | `SubscriptionPlan[]` |
| `GET` | `/api/v1/subscriptions/current` | — | `CurrentSubscription \| null` |
| `GET` | `/api/v1/subscriptions/history` | `{ page? }` | Paginated history |
| `GET` | `/api/v1/my/boost-plans` | — | Boost plan list |
| `POST` | `/api/v1/my/ads/:id/boost` | `{ plan_id, callback_url? }` | — |
| `DELETE` | `/api/v1/my/ads/:id/boost` | — | — |
| `GET` | `/api/v1/my/ads/:id/boost-status` | — | `BoostStatus` |

### Flutterwave Initiate Payload

```typescript
interface FlutterwaveInitiatePayload {
 type: 'credit' | 'subscription' | 'boost';
 payment_method: 'orange_money' | 'mobile_money' | 'card';
 phone_number?: string; // Required for mobile money
 ad_id?: string; // Required for boost
 plan_id?: string; // Required for subscription/boost
 period?: 'monthly' | 'yearly'; // Required for subscription
}
```

### Flutterwave Initiate Response

```typescript
interface FlutterwaveInitiateResponse {
 reference: string;
 payment_link: string; // Redirect URL for hosted payment page
 tx_ref: string; // Transaction reference for verification
 gateway: 'flutterwave';
 status: 'pending';
}
```

---

## Related Documentation

- [Ad Management](./ad-management.md) — Ad boost
- [Ad Details](../visitor/ad-details.md) — Contact unlock
