# Backend API — Production Readiness Audit

Deep-dive audit of every API controller, policy, service, and model.
Files reviewed: `PaymentController`, `SubscriptionController`, `AdController`, `UserController`, `AuthController`, `AgencyController`, `AdPolicy`, `PaymentPolicy`, `UserPolicy`, `SubscriptionService`, `FedaPayService`, `Payment` model, `Subscription` model, `routes/api.php`.

---

## 🔴 P0 — Critical (will lose money or data under real traffic)

### P0-1: Payment `initialize` — TOCTOU race condition
**File:** [PaymentController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/PaymentController.php)

The existing-payment check (`$existingPayment`) and `Payment::create()` are not atomic. Under concurrent requests (user double-taps "Pay"), two `PENDING` payments + two FedaPay transactions are created for the same ad — one of which the user will pay but the other won't be cancelled.

**Fix:** Wrap in `DB::transaction()` with `lockForUpdate()`:
```php
DB::transaction(function () use (...) {
    $existing = Payment::where('user_id', $user->id)
        ->where('ad_id', $adId)
        ->where('status', PaymentStatus::SUCCESS)
        ->lockForUpdate()
        ->first();
    if ($existing) { return ...; }
    Payment::create([...]);
});
```

---

### P0-2: Webhook lacks idempotency — double-activation possible
**File:** [PaymentController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/PaymentController.php)

If FedaPay replays a `transaction.approved` webhook (network retry, operator error), the handler:
1. Sets `status = SUCCESS` again (no-op but touches `updated_at`)
2. Re-calls `SubscriptionService::activateSubscription()` → generates a **duplicate invoice** and sends **duplicate emails**

**Fix:** Add an idempotency guard at the top of the webhook handler:
```php
$payment = Payment::where('transaction_id', $transactionId)
    ->lockForUpdate()->firstOrFail();
if ($payment->status === PaymentStatus::SUCCESS) {
    return response()->json(['message' => 'Already processed'], 200);
}
```

---

### P0-3: `AdPolicy::update` blocks owners from editing their own ads
**File:** [AdPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/AdPolicy.php#L30-L33)

```php
public function update(User $user, Ad $ad): bool
{
    return $user->isAdmin(); // ← Only admins!
}
```

Agents/owners who created an ad **cannot** update it. The `AdController::update` has full update logic that dead-ends because the policy rejects them.

**Fix:**
```php
public function update(User $user, Ad $ad): bool
{
    if ($user->isAdmin()) return true;
    return $user->isAgent() && $user->id === $ad->user_id;
}
```

---

### P0-4: `PaymentPolicy::create` — operator precedence bug
**File:** [PaymentPolicy.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Policies/PaymentPolicy.php#L30-L33)

```php
return $user->isCustomer() || $user->isAgent() && (...);
//     ^^^^^^^^^^^^^^^^^^^^^^^^  ← OR has lower precedence
```

This evaluates as `isCustomer() || (isAgent() && (...))` — any customer can create a payment, but it may cause unexpected behavior if the intent was different. **Verify intent and add parentheses.**

---

### P0-5: `ads_nearby_user({user})` — IDOR leaks any user's GPS location
**File:** [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L955-L998)

Any authenticated user can call `GET /api/v1/ads/{userId}/nearby` with **any** user ID. The controller fetches that user's `location` from the database (line 987-997) and returns the ads around them — effectively **disclosing the user's home GPS coordinates** through the `meta.center` object in the response.

**Fix:** Restrict `$user` to `auth()->id()` or add an ownership check:
```php
if ($targetUser->id !== auth()->id() && !auth()->user()->isAdmin()) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

---

### P0-6: `radius` parameter is uncapped — full-table geo scan
**File:** [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L1000)

```php
$radius = (float) $request->input('radius', $defaultRadius);
```

No upper bound. A client can send `radius=999999999` and force `ST_DistanceSphere` across every row, yielding a massive result set and DoS-ing the database.

**Fix:** Clamp to a sane maximum (e.g., 50km):
```php
$radius = min((float) $request->input('radius', 1000), 50000);
```

---

## 🟠 P1 — High (security/integrity risk under normal usage)

### P1-1: Email uniqueness check is TOCTOU in registration
**File:** [AuthController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AuthController.php#L244)

`User::where('email', ...)->exists()` runs **before** the `DB::transaction()` closure. Two concurrent signups with the same email can both pass this check. The database `unique` constraint will catch one, but it'll throw an unhandled `QueryException` (500) instead of a clean 409.

**Fix:** Move the check inside the transaction or rely on a `try/catch` on `UniqueConstraintViolationException`.

---

### P1-2: Registration trusts client-supplied `type` field
**File:** [AuthController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AuthController.php#L270-L271)

```php
'role' => $data['role'] ?? 'customer',
'type' => $data['type'] ?? 'individual',
```

The `registerCustomer` endpoint hardcodes `$data['role'] = 'customer'` but the `type` is passed through from the client. A customer could set `type=agency`, which may have downstream effects in payment eligibility or data visibility.

**Fix:** Force `type` to `null` or `individual` for customer registration.

---

### P1-3: `per_page` is uncapped in search/index endpoints
**Files:** [AdController.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L125) (index), [search](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L1775) (search), [searchFallback](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L1886) (fallback)

A client can send `per_page=99999` and force a massive query + JSON serialization, consuming memory and database resources.

**Fix:** Clamp `per_page`:
```php
$perPage = min(max((int) request('per_page', 15), 1), 100);
```

---

### P1-4: `SubscriptionService::createSubscription` silently cancels active subscriptions
**File:** [SubscriptionService.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Services/SubscriptionService.php#L31-L32)

```php
$this->cancelActiveSubscriptions($agency);
```

This runs inside the transaction. If a webhook triggers `activateSubscription` while a user is in the middle of subscribing to a new plan, the active subscription is cancelled before the new one is even paid. There's no user confirmation for downgrade/plan-change scenarios.

---

### P1-5: FedaPay `createPayment` callback_url is unauthenticated client redirect
**File:** [FedaPayService.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Services/FedaPayService.php#L40)

```php
'callback_url' => config('app.frontend_url') . "/payment-success?ad_id={$adId}",
```

The `ad_id` is interpolated into the callback URL **without URL-encoding**. More importantly, an attacker could manipulate the return URL. This is low-risk since FedaPay controls the redirect, but URL-encode the parameters at minimum.

---

## 🟡 P2 — Medium (correctness / robustness issues)

| # | Finding | Location |
|---|---------|----------|
| P2-8 | `AdRequest` missing `status` validation on update, preventing agents from marking ads as Sold/Reserved | [AdRequest.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Requests/AdRequest.php) |
| P2-1 | `UserController::store()` creates a Sanctum token and returns it — this is an admin endpoint, so giving the created user a token is surprising and potentially insecure | [UserController.php:322](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L322) |
| P2-2 | `UserController::store()` email check is outside the transaction (TOCTOU, same as P1-1) | [UserController.php:283](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/UserController.php#L283) |
| P2-3 | `Ad::store()` — accepts `expires_at` from client input. Expired ads should be set internally or by a scheduled job | [AdController.php:283](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L283) |
| P2-4 | `Ad::update()` — calls `$ad->update($data)` with all validated fields including `latitude`/`longitude` which aren't actual columns (they're computed into `location`), may silently fail or pollute `$ad->getAttributes()` | [AdController.php:588](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L588) |
| P2-5 | `SubscriptionService::expireSubscriptions()` fetches all expired subs into memory and processes them one by one instead of chunking | [SubscriptionService.php:124](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Services/SubscriptionService.php#L124) |
| P2-6 | `boostAgencyAds()` eager-loads all users then all ads into memory — N+1 and memory risk for large agencies | [SubscriptionService.php:146](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Services/SubscriptionService.php#L146) |
| P2-7 | `searchFallback` uses `ilike` which is PostgreSQL-specific — will crash on MySQL if the fallback is ever triggered on MySQL | [AdController.php:1900](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/app/Http/Controllers/Api/V1/AdController.php#L1900) |

---

## 🟢 P3 — Low (style, observability, minor improvements)

| # | Finding | Location |
|---|---------|----------|
| P3-1 | `Ad::store()` logs `$data` which may contain PII; use `$request->except(...)` | `AdController:268` |
| P3-2 | Multiple image upload field names (`images`, `image`, `photos`) accepted for backward compat → should be documented or consolidated | `AdController:292-306` |
| P3-3 | `FedaPayService` calls `setApiKey`/`setEnvironment` both in constructor and in each method — redundant | `FedaPayService:14-16,31-33` |
| P3-4 | `registerAdmin` is a public endpoint in `AuthController` — verify it is gated by auth middleware in routes | `AuthController:534` |
| P3-5 | `destroy` (User) doesn't handle cascading payments/subscriptions — FK constraint may throw 500 if user has data | `UserController:727` |

---

## Summary

| Severity | Count | Action Required |
|----------|-------|-----------------|
| 🔴 P0 | 6 | **Fix before production deployment** |
| 🟠 P1 | 5 | Fix before or immediately after launch |
| 🟡 P2 | 7 | Plan for next sprint |
| 🟢 P3 | 5 | Backlog / tech debt |

> **Recommendation:** Address all P0 items in a single focused sprint (estimated 2-3 hours implementation + testing). P1 items should follow immediately. P2/P3 can be scheduled for the next release cycle.
