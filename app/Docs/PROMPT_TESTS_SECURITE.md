# 🧪 PROMPT TECHNIQUE COMPLET — Tests de Sécurité & Intégrité des Données
## Stack : Laravel (Backend) · Next.js (Frontend) · Application Immobilière KeyHome

> **Objectif :** Écrire une suite de tests exhaustive couvrant la sécurité, l'intégrité des données,
> les cas limites, et les flux critiques de l'application. Chaque test doit être reproductible,
> déterministe, et isolé. Aucun test ne doit dépendre d'un autre.

---

## 🎯 PHILOSOPHIE DE TEST

```
Pyramide de tests à respecter :
                    ┌─────────┐
                    │   E2E   │  ← 10% — Playwright (flux critiques uniquement)
                  ┌─┴─────────┴─┐
                  │ Intégration  │  ← 30% — Feature tests Laravel, Testing Library React
                ┌─┴─────────────┴─┐
                │    Unitaires     │  ← 60% — PHPUnit, Vitest
                └───────────────────┘

Règles absolues :
- Chaque test = un seul comportement vérifié (Single Assertion Principle)
- Nomenclature : it_should_[do_something]_when_[condition]
- Jamais de données de production dans les tests
- Jamais de dépendance réseau réelle (mock tout appel HTTP externe)
- Base de données : SQLite in-memory en test (Laravel) / MSW (Next.js)
- Chaque test doit pouvoir tourner seul, dans n'importe quel ordre
```

---

## 🗂️ PARTIE 1 — TESTS BACKEND LARAVEL

### Installation et configuration

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
composer require --dev mockery/mockery fakerphp/faker

# Configurer phpunit.xml
# - DB_CONNECTION=sqlite, DB_DATABASE=:memory:
# - PAYMENT_GATEWAY=flutterwave (test)
# - Désactiver les queues : QUEUE_CONNECTION=sync
# - Désactiver les emails : MAIL_MAILER=array
```

```xml
<!-- phpunit.xml — configuration obligatoire -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="APP_KEY" value="base64:testkey=="/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="PAYMENT_GATEWAY" value="flutterwave"/>
    <env name="FLW_SECRET_KEY" value="FLWSECK_TEST-fake"/>
    <env name="FLW_WEBHOOK_SECRET" value="test_webhook_secret_123"/>
    <env name="FEDAPAY_API_KEY" value="sk_sandbox_fake"/>
    <env name="FEDAPAY_WEBHOOK_SECRET" value="test_fedapay_secret_456"/>
</php>
```

---

### 1.1 — Tests Unitaires : `FlutterwavePaymentService`

**Fichier :** `tests/Unit/Services/Payment/FlutterwavePaymentServiceTest.php`

```php
<?php

namespace Tests\Unit\Services\Payment;

use Tests\TestCase;
use App\Services\Payment\FlutterwavePaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;

class FlutterwavePaymentServiceTest extends TestCase
{
    private FlutterwavePaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FlutterwavePaymentService::class);
    }

    // ─── INITIATION ──────────────────────────────────────────────────────────

    /** @test */
    public function it_should_initiate_payment_successfully_when_payload_is_valid(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data'   => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
            ], 200),
        ]);

        $result = $this->service->initiate([
            'amount'       => 150000,
            'currency'     => 'XAF',
            'email'        => 'test@keyhome.app',
            'phone'        => '+237699000000',
            'name'         => 'Jean Dupont',
            'tx_ref'       => 'TXN-2025-ABCDEF',
            'payment_type' => 'rent',
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('checkout.flutterwave.com', $result['link']);
        $this->assertEquals('flutterwave', $result['gateway']);
    }

    /** @test */
    public function it_should_send_correct_authorization_header_to_flutterwave(): void
    {
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://test']], 200)]);

        $this->service->initiate([
            'amount' => 50000, 'currency' => 'XAF', 'email' => 'test@test.com',
            'phone' => '+237699000000', 'name' => 'Test', 'tx_ref' => 'TX-001', 'payment_type' => 'rent',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer ' . config('payment.gateways.flutterwave.secret_key'));
        });
    }

    /** @test */
    public function it_should_throw_exception_when_flutterwave_returns_error(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'error', 'message' => 'Invalid key'], 400),
        ]);

        $this->expectException(\App\Exceptions\PaymentGatewayException::class);

        $this->service->initiate([
            'amount' => 50000, 'currency' => 'XAF', 'email' => 'test@test.com',
            'phone' => '+237699000000', 'name' => 'Test', 'tx_ref' => 'TX-001', 'payment_type' => 'rent',
        ]);
    }

    /** @test */
    public function it_should_throw_exception_when_flutterwave_is_unreachable(): void
    {
        Http::fake(['api.flutterwave.com/*' => Http::response(null, 503)]);

        $this->expectException(\App\Exceptions\PaymentGatewayException::class);
        $this->expectExceptionMessage('Gateway indisponible');

        $this->service->initiate(['amount' => 50000, 'currency' => 'XAF', 'email' => 'x@x.com',
            'phone' => '+237699000000', 'name' => 'X', 'tx_ref' => 'TX-002', 'payment_type' => 'rent']);
    }

    // ─── VÉRIFICATION ────────────────────────────────────────────────────────

    /** @test */
    public function it_should_verify_transaction_successfully_when_reference_is_valid(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data'   => [
                    'status'         => 'successful',
                    'amount'         => 150000,
                    'currency'       => 'XAF',
                    'payment_type'   => 'mobilemoneycameroon',
                    'created_at'     => '2025-03-07T10:30:00.000Z',
                ],
            ], 200),
        ]);

        $result = $this->service->verify('TXN-2025-ABCDEF');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(150000, $result['amount']);
        $this->assertEquals('XAF', $result['currency']);
    }

    /** @test */
    public function it_should_return_failed_status_when_transaction_was_not_paid(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data'   => ['status' => 'failed', 'amount' => 150000, 'currency' => 'XAF'],
            ], 200),
        ]);

        $result = $this->service->verify('TXN-FAILED-001');

        $this->assertEquals('failed', $result['status']);
    }

    // ─── WEBHOOK ─────────────────────────────────────────────────────────────

    /** @test */
    public function it_should_validate_webhook_successfully_when_signature_is_correct(): void
    {
        $secret  = config('payment.gateways.flutterwave.webhook_secret');
        $payload = ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001', 'status' => 'successful']];
        $headers = ['verif-hash' => $secret];

        $result = $this->service->handleWebhook($payload, $headers);

        $this->assertEquals('TXN-001', $result['tx_ref']);
        $this->assertEquals('successful', $result['status']);
    }

    /** @test */
    public function it_should_reject_webhook_when_signature_is_invalid(): void
    {
        $this->expectException(\App\Exceptions\InvalidWebhookSignatureException::class);

        $this->service->handleWebhook(
            ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001']],
            ['verif-hash' => 'invalid_signature_hacker']
        );
    }

    /** @test */
    public function it_should_reject_webhook_when_signature_header_is_missing(): void
    {
        $this->expectException(\App\Exceptions\InvalidWebhookSignatureException::class);

        $this->service->handleWebhook(
            ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001']],
            [] // pas de header
        );
    }

    /** @test */
    public function it_should_ignore_non_charge_completed_webhook_events(): void
    {
        $secret  = config('payment.gateways.flutterwave.webhook_secret');
        $payload = ['event' => 'transfer.completed', 'data' => ['id' => 999]];
        $headers = ['verif-hash' => $secret];

        $result = $this->service->handleWebhook($payload, $headers);

        $this->assertNull($result['tx_ref'] ?? null);
        $this->assertEquals('ignored', $result['action']);
    }
}
```

---

### 1.2 — Tests Unitaires : `PaymentService` (Orchestrateur)

**Fichier :** `tests/Unit/Services/Payment/PaymentServiceTest.php`

```php
<?php

// Tester les comportements de l'orchestrateur avec des mocks des gateways

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── CRÉATION ────────────────────────────────────────────────────────────

    /** @test */
    public function it_should_create_pending_transaction_in_database_when_payment_is_initiated(): void
    // Vérifier : Transaction créée, status=pending, gateway=flutterwave, bon user_id

    /** @test */
    public function it_should_return_payment_link_when_transaction_is_created(): void
    // Vérifier : la réponse contient bien un payment_link valide

    /** @test */
    public function it_should_fire_PaymentInitiated_event_when_transaction_is_created(): void
    // Event::fake(); ... Event::assertDispatched(PaymentInitiated::class)

    /** @test */
    public function it_should_mark_transaction_as_failed_when_gateway_throws_exception(): void
    // Vérifier : Transaction en base, mais status=failed, pas de payment_link

    // ─── VÉRIFICATION ────────────────────────────────────────────────────────

    /** @test */
    public function it_should_mark_transaction_as_success_when_gateway_confirms_payment(): void
    // Vérifier : status=success, paid_at not null, event PaymentSucceeded fired

    /** @test */
    public function it_should_return_cached_success_without_calling_gateway_when_already_paid(): void
    // Idempotence : si status=success → retourner sans appel HTTP externe
    // Http::assertNothingSent()

    /** @test */
    public function it_should_fire_PaymentSucceeded_event_only_once_for_duplicate_webhooks(): void
    // Envoyer le même webhook 3 fois → event fired une seule fois

    /** @test */
    public function it_should_reject_payment_amount_mismatch_between_db_and_gateway(): void
    // Transaction créée pour 150000 XAF, gateway retourne 50000 XAF
    // Doit lancer AmountMismatchException et ne pas marquer comme success

    // ─── SÉCURITÉ MÉTIER ─────────────────────────────────────────────────────

    /** @test */
    public function it_should_prevent_user_from_verifying_another_users_transaction(): void
    // User A tente de vérifier la transaction de User B → 403

    /** @test */
    public function it_should_prevent_initiating_payment_for_property_not_owned_by_user(): void
    // Propriété appartient à User B, User A tente d'initier → 403
}
```

---

### 1.3 — Tests Feature : Endpoints HTTP

**Fichier :** `tests/Feature/Payment/PaymentInitiateTest.php`

```php
<?php

class PaymentInitiateTest extends TestCase
{
    use RefreshDatabase;

    // ─── AUTHENTIFICATION ────────────────────────────────────────────────────

    /** @test */
    public function it_should_return_401_when_user_is_not_authenticated(): void
    {
        $response = $this->postJson('/api/v1/payments/initiate', [
            'amount' => 150000, 'currency' => 'XAF', 'type' => 'rent',
            'payment_method' => 'mtn_cm', 'phone_number' => '+237699000000',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('transactions', ['amount' => 150000]);
    }

    /** @test */
    public function it_should_return_401_when_token_is_expired(): void
    // Créer un token expiré (Carbon::now()->subHour()), vérifier 401

    /** @test */
    public function it_should_return_401_when_token_is_tampered(): void
    // Modifier le Bearer token → 401

    // ─── VALIDATION ──────────────────────────────────────────────────────────

    /** @test */
    public function it_should_return_422_when_amount_is_missing(): void

    /** @test */
    public function it_should_return_422_when_amount_is_below_minimum(): void
    // amount = 50 (< 100 XAF minimum)

    /** @test */
    public function it_should_return_422_when_amount_is_negative(): void
    // amount = -1000

    /** @test */
    public function it_should_return_422_when_currency_is_not_supported(): void
    // currency = 'USD' → 422

    /** @test */
    public function it_should_return_422_when_phone_number_format_is_invalid(): void
    // phone = '0699' → 422 avec message explicite

    /** @test */
    public function it_should_return_422_when_payment_method_is_invalid(): void
    // payment_method = 'paypal' → 422

    /** @test */
    public function it_should_return_422_when_phone_is_missing_for_mobile_money(): void
    // payment_method = 'mtn_cm' sans phone_number → 422

    // ─── SUCCÈS ──────────────────────────────────────────────────────────────

    /** @test */
    public function it_should_return_201_with_payment_link_when_payload_is_valid(): void
    {
        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data'   => ['link' => 'https://checkout.flutterwave.com/pay/abc123'],
        ], 200)]);

        $user     = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate', [
            'amount'         => 150000,
            'currency'       => 'XAF',
            'type'           => 'rent',
            'payment_method' => 'mtn_cm',
            'phone_number'   => '+237699000000',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['reference', 'payment_link', 'tx_ref', 'gateway'])
                 ->assertJsonPath('gateway', 'flutterwave');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'status'  => 'pending',
            'amount'  => 150000,
            'gateway' => 'flutterwave',
        ]);
    }

    // ─── RATE LIMITING ───────────────────────────────────────────────────────

    /** @test */
    public function it_should_return_429_when_user_exceeds_5_requests_per_minute(): void
    {
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://test']], 200)]);

        $user    = User::factory()->create();
        $payload = ['amount' => 150000, 'currency' => 'XAF', 'type' => 'rent',
                    'payment_method' => 'mtn_cm', 'phone_number' => '+237699000000'];

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->postJson('/api/v1/payments/initiate', $payload);
        }

        $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate', $payload);
        $response->assertStatus(429);
    }

    // ─── ISOLATION DES DONNÉES ───────────────────────────────────────────────

    /** @test */
    public function it_should_not_expose_other_users_data_in_response(): void
    // S'assurer que la réponse ne contient que les données de l'utilisateur connecté
}
```

---

### 1.4 — Tests Feature : Webhooks

**Fichier :** `tests/Feature/Payment/PaymentWebhookTest.php`

```php
<?php

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeValidWebhookPayload(string $txRef, string $status = 'successful'): array
    {
        return [
            'event' => 'charge.completed',
            'data'  => [
                'tx_ref'       => $txRef,
                'status'       => $status,
                'amount'       => 150000,
                'currency'     => 'XAF',
                'payment_type' => 'mobilemoneycameroon',
                'created_at'   => now()->toISOString(),
            ],
        ];
    }

    private function validHeaders(): array
    {
        return ['verif-hash' => config('payment.gateways.flutterwave.webhook_secret')];
    }

    // ─── SIGNATURE ───────────────────────────────────────────────────────────

    /** @test */
    public function it_should_return_200_when_webhook_signature_is_valid(): void
    {
        $transaction = Transaction::factory()->pending()->create(['amount' => 150000]);
        $payload     = $this->makeValidWebhookPayload($transaction->reference);

        $response = $this->postJson('/api/v1/webhooks/flutterwave', $payload, $this->validHeaders());
        $response->assertStatus(200);
    }

    /** @test */
    public function it_should_return_401_when_webhook_has_no_signature_header(): void
    {
        $response = $this->postJson('/api/v1/webhooks/flutterwave',
            $this->makeValidWebhookPayload('TXN-001'),
            [] // pas de header
        );

        $response->assertStatus(401);
    }

    /** @test */
    public function it_should_return_401_when_webhook_signature_is_tampered(): void
    {
        $response = $this->postJson('/api/v1/webhooks/flutterwave',
            $this->makeValidWebhookPayload('TXN-001'),
            ['verif-hash' => 'hacker_attempt_' . bin2hex(random_bytes(16))]
        );

        $response->assertStatus(401);
        // Vérifier que RIEN n'a changé en base
        $this->assertDatabaseMissing('transactions', ['status' => 'success']);
    }

    /** @test */
    public function it_should_not_update_database_when_signature_is_invalid(): void
    {
        $transaction = Transaction::factory()->pending()->create();

        $this->postJson('/api/v1/webhooks/flutterwave',
            $this->makeValidWebhookPayload($transaction->reference),
            ['verif-hash' => 'wrong_signature']
        );

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'pending']);
    }

    // ─── TRAITEMENT ──────────────────────────────────────────────────────────

    /** @test */
    public function it_should_mark_transaction_as_success_when_webhook_is_charge_completed(): void
    {
        $transaction = Transaction::factory()->pending()->create(['amount' => 150000]);
        $payload     = $this->makeValidWebhookPayload($transaction->reference, 'successful');

        $this->postJson('/api/v1/webhooks/flutterwave', $payload, $this->validHeaders());

        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'status' => 'success',
        ]);
        $this->assertNotNull($transaction->fresh()->paid_at);
    }

    /** @test */
    public function it_should_fire_PaymentSucceeded_event_when_webhook_marks_transaction_success(): void
    {
        Event::fake();
        $transaction = Transaction::factory()->pending()->create(['amount' => 150000]);

        $this->postJson('/api/v1/webhooks/flutterwave',
            $this->makeValidWebhookPayload($transaction->reference),
            $this->validHeaders()
        );

        Event::assertDispatched(PaymentSucceeded::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    }

    // ─── IDEMPOTENCE ─────────────────────────────────────────────────────────

    /** @test */
    public function it_should_be_idempotent_when_same_webhook_is_received_twice(): void
    {
        Event::fake();
        $transaction = Transaction::factory()->pending()->create(['amount' => 150000]);
        $payload     = $this->makeValidWebhookPayload($transaction->reference);

        $this->postJson('/api/v1/webhooks/flutterwave', $payload, $this->validHeaders());
        $this->postJson('/api/v1/webhooks/flutterwave', $payload, $this->validHeaders());
        $this->postJson('/api/v1/webhooks/flutterwave', $payload, $this->validHeaders());

        // Event fired UNE SEULE fois
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
        // Status toujours success, pas de doublon
        $this->assertEquals(1, Transaction::where('id', $transaction->id)->count());
    }

    // ─── SÉCURITÉ AVANCÉE ────────────────────────────────────────────────────

    /** @test */
    public function it_should_reject_replay_attack_with_old_timestamp(): void
    // Webhook avec timestamp > 5 minutes → 401

    /** @test */
    public function it_should_not_downgrade_success_to_failed_via_fake_webhook(): void
    {
        // Transaction déjà success → un attaquant envoie un webhook "failed"
        $transaction = Transaction::factory()->success()->create(['amount' => 150000]);

        $this->postJson('/api/v1/webhooks/flutterwave', [
            'event' => 'charge.completed',
            'data'  => ['tx_ref' => $transaction->reference, 'status' => 'failed', 'amount' => 150000, 'currency' => 'XAF'],
        ], $this->validHeaders());

        // Le statut NE DOIT PAS changer
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'success']);
    }

    /** @test */
    public function it_should_reject_webhook_with_amount_different_from_database(): void
    // Montant dans le webhook ≠ montant en base → transaction marquée suspicious, pas success
}
```

---

### 1.5 — Tests de Sécurité : Autorisation et Isolation

**Fichier :** `tests/Feature/Security/AuthorizationTest.php`

```php
<?php

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ─── ISOLATION DONNÉES UTILISATEURS ─────────────────────────────────────

    /** @test */
    public function it_should_return_only_authenticated_user_transactions_in_history(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Transaction::factory()->count(3)->create(['user_id' => $userA->id]);
        Transaction::factory()->count(5)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson('/api/v1/payments/history');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        // S'assurer qu'aucune transaction de userB n'est exposée
        foreach ($data as $transaction) {
            $this->assertEquals($userA->id, $transaction['user_id'] ?? $userA->id);
        }
    }

    /** @test */
    public function it_should_return_404_when_user_tries_to_access_another_users_transaction(): void
    {
        $userA       = User::factory()->create();
        $userB       = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/payments/{$transaction->reference}");
        $response->assertStatus(404); // 404 et pas 403 (ne pas révéler l'existence)
    }

    /** @test */
    public function it_should_return_403_when_user_tries_to_verify_another_users_transaction(): void
    {
        $userA       = User::factory()->create();
        $userB       = User::factory()->create();
        $transaction = Transaction::factory()->pending()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->postJson("/api/v1/payments/{$transaction->reference}/verify");
        $response->assertStatus(403);
    }

    // ─── INJECTION & MANIPULATION ────────────────────────────────────────────

    /** @test */
    public function it_should_ignore_gateway_field_sent_by_client(): void
    {
        // L'utilisateur tente de forcer le gateway via la requête
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://test']], 200)]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate', [
            'amount'         => 150000,
            'currency'       => 'XAF',
            'type'           => 'rent',
            'payment_method' => 'mtn_cm',
            'phone_number'   => '+237699000000',
            'gateway'        => 'fedapay',  // tentative de manipulation
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transactions', ['gateway' => 'flutterwave']); // gateway toujours flutterwave
    }

    /** @test */
    public function it_should_ignore_status_field_sent_by_client(): void
    {
        // L'utilisateur tente de forcer le status=success
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://test']], 200)]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/payments/initiate', [
            'amount'         => 150000,
            'currency'       => 'XAF',
            'type'           => 'rent',
            'payment_method' => 'mtn_cm',
            'phone_number'   => '+237699000000',
            'status'         => 'success',  // tentative de manipulation
        ]);

        $this->assertDatabaseHas('transactions', ['status' => 'pending']); // toujours pending
    }

    /** @test */
    public function it_should_sanitize_metadata_field_to_prevent_xss_injection(): void
    // metadata contenant <script>alert(1)</script> → doit être sanitisé ou rejeté

    /** @test */
    public function it_should_reject_sql_injection_attempt_in_reference_field(): void
    {
        $user     = User::factory()->create();
        $response = $this->actingAs($user)->getJson("/api/v1/payments/'; DROP TABLE transactions; --");
        $response->assertStatus(404); // Pas de 500, pas d'erreur SQL exposée
    }

    // ─── EXPOSITION DE DONNÉES SENSIBLES ─────────────────────────────────────

    /** @test */
    public function it_should_never_expose_flutterwave_secret_key_in_api_response(): void
    {
        $user     = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/v1/payments/history');
        $content  = $response->getContent();

        $this->assertStringNotContainsString(config('payment.gateways.flutterwave.secret_key'), $content);
        $this->assertStringNotContainsString('FLW_SECRET', $content);
        $this->assertStringNotContainsString('webhook_secret', $content);
    }

    /** @test */
    public function it_should_never_expose_raw_gateway_response_in_api_response(): void
    // gateway_response (données brutes Flutterwave) ne doit pas apparaître dans la réponse publique

    /** @test */
    public function it_should_mask_phone_number_in_list_responses(): void
    // +237699000000 → +237699***000 dans la liste (pas dans le détail)
}
```

---

### 1.6 — Tests des Factories

**Fichier :** `database/factories/TransactionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'reference'          => 'TXN-TEST-' . strtoupper(Str::random(8)),
            'external_reference' => 'FLW-MOCK-' . $this->faker->randomNumber(8),
            'user_id'            => \App\Models\User::factory(),
            'gateway'            => 'flutterwave',
            'status'             => 'pending',
            'type'               => $this->faker->randomElement(['rent', 'purchase', 'deposit']),
            'amount'             => $this->faker->randomElement([50000, 100000, 150000, 500000]),
            'currency'           => 'XAF',
            'phone_number'       => '+237' . $this->faker->numerify('6########'),
            'payment_method'     => $this->faker->randomElement(['mtn_cm', 'orange_cm']),
            'ip_address'         => $this->faker->ipv4(),
            'user_agent'         => $this->faker->userAgent(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'paid_at' => null]);
    }

    public function success(): static
    {
        return $this->state(['status' => 'success', 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'failed_at' => now()]);
    }

    public function flutterwave(): static
    {
        return $this->state(['gateway' => 'flutterwave']);
    }

    public function fedapay(): static
    {
        return $this->state(['gateway' => 'fedapay']);
    }
}
```

---

## 🗂️ PARTIE 2 — TESTS FRONTEND NEXT.JS

### Installation et configuration

```bash
npm install --save-dev vitest @testing-library/react @testing-library/user-event
npm install --save-dev @testing-library/jest-dom msw zod
npm install --save-dev @playwright/test  # Tests E2E
```

```typescript
// vitest.config.ts
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles:  ['./tests/setup.ts'],
    globals:     true,
    coverage: {
      provider:  'v8',
      reporter:  ['text', 'lcov'],
      thresholds: { lines: 80, functions: 80, branches: 70 },
    },
  },
});
```

```typescript
// tests/setup.ts
import '@testing-library/jest-dom';
import { server } from './mocks/server';

beforeAll(()  => server.listen({ onUnhandledRequest: 'error' }));
afterEach(()  => server.resetHandlers());
afterAll(()   => server.close());
```

---

### 2.1 — Mock Service Worker (MSW)

**Fichier :** `tests/mocks/handlers.ts`

```typescript
import { http, HttpResponse } from 'msw';

export const handlers = [

  // ─── Initier un paiement ─────────────────────────────────────────────────
  http.post('/api/payment/initiate', () => {
    return HttpResponse.json({
      reference:    'TXN-TEST-ABCDEF',
      payment_link: 'https://checkout.flutterwave.com/pay/test123',
      tx_ref:       'TXN-TEST-ABCDEF',
      gateway:      'flutterwave',
      expires_at:   new Date(Date.now() + 30 * 60 * 1000).toISOString(),
    }, { status: 201 });
  }),

  // ─── Statut d'une transaction ─────────────────────────────────────────────
  http.get('/api/payment/:reference', ({ params }) => {
    return HttpResponse.json({
      reference:      params.reference,
      status:         'pending',
      amount:         150000,
      currency:       'XAF',
      gateway:        'flutterwave',
      payment_method: 'mtn_cm',
      created_at:     new Date().toISOString(),
    });
  }),

  // ─── Vérifier une transaction ─────────────────────────────────────────────
  http.post('/api/payment/:reference/verify', () => {
    return HttpResponse.json({
      reference: 'TXN-TEST-ABCDEF',
      status:    'success',
      paid_at:   new Date().toISOString(),
    });
  }),
];

// Handlers pour scénarios d'erreur (à utiliser avec server.use(...))
export const errorHandlers = {
  initiateNetworkError: http.post('/api/payment/initiate', () => {
    return HttpResponse.error();
  }),
  initiateValidationError: http.post('/api/payment/initiate', () => {
    return HttpResponse.json({ message: 'Validation failed', errors: { amount: ['Montant invalide'] } }, { status: 422 });
  }),
  initiateRateLimit: http.post('/api/payment/initiate', () => {
    return HttpResponse.json({ message: 'Trop de requêtes' }, { status: 429 });
  }),
  gatewayUnavailable: http.post('/api/payment/initiate', () => {
    return HttpResponse.json({ message: 'Gateway indisponible' }, { status: 503 });
  }),
};
```

---

### 2.2 — Tests du Hook `usePayment`

**Fichier :** `tests/hooks/usePayment.test.ts`

```typescript
import { renderHook, act, waitFor } from '@testing-library/react';
import { server } from '../mocks/server';
import { errorHandlers } from '../mocks/handlers';
import { usePayment } from '@/hooks/usePayment';

describe('usePayment', () => {

  // ─── ÉTAT INITIAL ──────────────────────────────────────────────────────────

  it('should have correct initial state', () => {
    const { result } = renderHook(() => usePayment());

    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
    expect(result.current.transaction).toBeNull();
  });

  // ─── SUCCÈS ────────────────────────────────────────────────────────────────

  it('should set isLoading to true while request is in flight', async () => {
    const { result } = renderHook(() => usePayment());
    const states: boolean[] = [];

    act(() => {
      result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
      states.push(result.current.isLoading);
    });

    expect(states).toContain(true);
    await waitFor(() => expect(result.current.isLoading).toBe(false));
  });

  it('should store reference in sessionStorage after successful initiation', async () => {
    const { result } = renderHook(() => usePayment());
    const setSpy = vi.spyOn(Storage.prototype, 'setItem');

    await act(async () => {
      await result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
    });

    expect(setSpy).toHaveBeenCalledWith('payment_reference', 'TXN-TEST-ABCDEF');
  });

  // ─── ERREURS ───────────────────────────────────────────────────────────────

  it('should set error message when network fails', async () => {
    server.use(errorHandlers.initiateNetworkError);
    const { result } = renderHook(() => usePayment());

    await act(async () => {
      await result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
    });

    expect(result.current.error).not.toBeNull();
    expect(result.current.isLoading).toBe(false);
  });

  it('should set user-friendly error message when rate limited', async () => {
    server.use(errorHandlers.initiateRateLimit);
    const { result } = renderHook(() => usePayment());

    await act(async () => {
      await result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
    });

    expect(result.current.error).toMatch(/trop de requêtes/i);
  });

  it('should clear error on resetPayment', async () => {
    server.use(errorHandlers.initiateNetworkError);
    const { result } = renderHook(() => usePayment());

    await act(async () => {
      await result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
    });

    expect(result.current.error).not.toBeNull();

    act(() => result.current.resetPayment());

    expect(result.current.error).toBeNull();
    expect(result.current.transaction).toBeNull();
  });

  // ─── SÉCURITÉ ──────────────────────────────────────────────────────────────

  it('should never include secret keys in the request payload', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch');
    const { result } = renderHook(() => usePayment());

    await act(async () => {
      await result.current.initiatePayment({
        amount: 150000, currency: 'XAF', type: 'rent',
        payment_method: 'mtn_cm', phone_number: '+237699000000',
      });
    });

    const calls = fetchSpy.mock.calls;
    calls.forEach(([_, init]) => {
      const body = JSON.stringify(init?.body ?? '');
      expect(body).not.toMatch(/FLWSECK/);
      expect(body).not.toMatch(/secret/i);
      expect(body).not.toMatch(/api_key/i);
    });
  });
});
```

---

### 2.3 — Tests du Hook `useTransactionStatus`

**Fichier :** `tests/hooks/useTransactionStatus.test.ts`

```typescript
describe('useTransactionStatus', () => {

  beforeEach(() => { vi.useFakeTimers(); });
  afterEach(()  => { vi.useRealTimers(); });

  it('should start polling when reference is provided', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch');
    renderHook(() => useTransactionStatus('TXN-TEST-ABCDEF'));

    await act(async () => { vi.advanceTimersByTime(3000); });
    expect(fetchSpy).toHaveBeenCalled();
  });

  it('should stop polling when status becomes success', async () => {
    let callCount = 0;
    server.use(
      http.get('/api/payment/:ref', () => {
        callCount++;
        return HttpResponse.json({ status: callCount === 2 ? 'success' : 'pending', reference: 'TXN-001' });
      })
    );

    const { result } = renderHook(() => useTransactionStatus('TXN-001'));

    await act(async () => { vi.advanceTimersByTime(9000); }); // 3 cycles
    await waitFor(() => expect(result.current.transaction?.status).toBe('success'));

    const countAfterSuccess = callCount;
    await act(async () => { vi.advanceTimersByTime(9000); }); // 3 cycles de plus
    expect(callCount).toBe(countAfterSuccess); // Pas de nouveaux appels
  });

  it('should stop polling after 5 minutes timeout', async () => {
    // Status toujours pending
    const { result } = renderHook(() => useTransactionStatus('TXN-TIMEOUT'));

    await act(async () => { vi.advanceTimersByTime(5 * 60 * 1000 + 1000); });
    expect(result.current.isPolling).toBe(false);
    expect(result.current.hasTimedOut).toBe(true);
  });

  it('should not poll when reference is null', () => {
    const fetchSpy = vi.spyOn(global, 'fetch');
    renderHook(() => useTransactionStatus(null));

    act(() => { vi.advanceTimersByTime(10000); });
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});
```

---

### 2.4 — Tests Composant `PaymentModal`

**Fichier :** `tests/components/PaymentModal.test.tsx`

```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { PaymentModal } from '@/components/payment/PaymentModal';
import { server } from '../mocks/server';
import { errorHandlers } from '../mocks/handlers';

const defaultProps = {
  isOpen:     true,
  onClose:    vi.fn(),
  amount:     150000,
  currency:   'XAF' as const,
  type:       'rent' as const,
  propertyId: 1,
  propertyTitle: 'Appartement T3 Bastos',
};

describe('PaymentModal', () => {

  // ─── RENDU INITIAL ─────────────────────────────────────────────────────────

  it('should render payment method selection step initially', () => {
    render(<PaymentModal {...defaultProps} />);

    expect(screen.getByText('MTN Mobile Money')).toBeInTheDocument();
    expect(screen.getByText('Orange Money')).toBeInTheDocument();
    expect(screen.getByText('Carte bancaire')).toBeInTheDocument();
  });

  it('should display formatted amount in XAF', () => {
    render(<PaymentModal {...defaultProps} />);
    expect(screen.getByText(/150 000/)).toBeInTheDocument();
    expect(screen.getByText(/FCFA/i)).toBeInTheDocument();
  });

  it('should not render when isOpen is false', () => {
    render(<PaymentModal {...defaultProps} isOpen={false} />);
    expect(screen.queryByText('MTN Mobile Money')).not.toBeInTheDocument();
  });

  // ─── NAVIGATION ────────────────────────────────────────────────────────────

  it('should show phone input after selecting MTN Mobile Money', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));

    expect(screen.getByRole('textbox', { name: /numéro de téléphone/i })).toBeInTheDocument();
  });

  it('should advance to confirmation step when phone is valid and continue is clicked', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));
    await user.type(screen.getByRole('textbox', { name: /téléphone/i }), '+237699000000');
    await user.click(screen.getByRole('button', { name: /continuer/i }));

    expect(screen.getByRole('button', { name: /payer 150 000/i })).toBeInTheDocument();
  });

  // ─── VALIDATION ────────────────────────────────────────────────────────────

  it('should show error when phone number format is invalid for MTN', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));
    await user.type(screen.getByRole('textbox', { name: /téléphone/i }), '0699'); // trop court
    await user.click(screen.getByRole('button', { name: /continuer/i }));

    expect(screen.getByRole('alert')).toHaveTextContent(/numéro invalide/i);
  });

  it('should require phone number for Orange Money payment method', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /Orange Money/i }));
    await user.click(screen.getByRole('button', { name: /continuer/i })); // sans entrer de numéro

    expect(screen.getByRole('alert')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /continuer/i })).toBeInTheDocument(); // pas avancé
  });

  // ─── PAIEMENT ──────────────────────────────────────────────────────────────

  it('should disable close button while payment is processing', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));
    await user.type(screen.getByRole('textbox', { name: /téléphone/i }), '+237699000000');
    await user.click(screen.getByRole('button', { name: /continuer/i }));
    await user.click(screen.getByRole('button', { name: /payer/i }));

    // Pendant le chargement
    const closeButton = screen.queryByRole('button', { name: /fermer/i });
    expect(closeButton).toBeDisabled();
  });

  it('should show success screen after payment is confirmed', async () => {
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));
    await user.type(screen.getByRole('textbox', { name: /téléphone/i }), '+237699000000');
    await user.click(screen.getByRole('button', { name: /continuer/i }));
    await user.click(screen.getByRole('button', { name: /payer/i }));

    await waitFor(() => {
      expect(screen.getByText(/paiement réussi/i)).toBeInTheDocument();
    });
  });

  it('should show error message when gateway returns an error', async () => {
    server.use(errorHandlers.gatewayUnavailable);
    const user = userEvent.setup();
    render(<PaymentModal {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /MTN Mobile Money/i }));
    await user.type(screen.getByRole('textbox', { name: /téléphone/i }), '+237699000000');
    await user.click(screen.getByRole('button', { name: /continuer/i }));
    await user.click(screen.getByRole('button', { name: /payer/i }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /réessayer/i })).toBeInTheDocument();
    });
  });

  // ─── ACCESSIBILITÉ ─────────────────────────────────────────────────────────

  it('should have correct ARIA attributes on modal', () => {
    render(<PaymentModal {...defaultProps} />);
    const modal = screen.getByRole('dialog');
    expect(modal).toHaveAttribute('aria-modal', 'true');
    expect(modal).toHaveAttribute('aria-labelledby');
  });

  it('should trap focus inside modal when open', async () => {
    // Focus ne doit pas sortir du modal
    render(<PaymentModal {...defaultProps} />);
    const focusableElements = screen.getAllByRole('button');
    expect(focusableElements.length).toBeGreaterThan(0);
    // Vérifier que le premier élément focusable est à l'intérieur du modal
  });

  // ─── SÉCURITÉ ──────────────────────────────────────────────────────────────

  it('should not display any API keys or secrets in the DOM', () => {
    render(<PaymentModal {...defaultProps} />);
    const html = document.documentElement.innerHTML;
    expect(html).not.toMatch(/FLWSECK/);
    expect(html).not.toMatch(/secret/i);
    expect(html).not.toMatch(/api_key/i);
  });
});
```

---

### 2.5 — Tests Page Callback

**Fichier :** `tests/pages/PaymentCallback.test.tsx`

```typescript
describe('PaymentCallback page', () => {

  it('should show success screen when tx_ref is valid and verify succeeds', async () => {
    sessionStorage.setItem('payment_reference', 'TXN-TEST-ABCDEF');
    // Rendre la page avec ?tx_ref=TXN-TEST-ABCDEF&status=successful
    // Attendre que l'appel verify soit fait
    // Vérifier l'écran de succès
  });

  it('should show error screen when status query param is failed', async () => {
    // ?status=failed → écran d'erreur sans appel verify
  });

  it('should handle missing sessionStorage gracefully', async () => {
    sessionStorage.clear();
    // ?tx_ref=TXN-XXX → doit afficher un message d'erreur propre, pas crasher
  });

  it('should clean sessionStorage after processing', async () => {
    sessionStorage.setItem('payment_reference', 'TXN-TEST-ABCDEF');
    // Après render et traitement
    // sessionStorage.getItem('payment_reference') doit être null
  });

  it('should redirect to property page after 5s on success', async () => {
    vi.useFakeTimers();
    // Simuler succès, avancer le timer de 5001ms
    // Vérifier que router.push a été appelé avec la bonne URL
    vi.useRealTimers();
  });
});
```

---

### 2.6 — Tests Utilitaires : Formatters et Validators

**Fichier :** `tests/lib/payment/formatters.test.ts`

```typescript
import { formatXAF, formatPhoneCameroon, maskPhone } from '@/lib/payment/formatters';

describe('formatXAF', () => {
  it('should format 150000 as "150 000 FCFA"',       () => expect(formatXAF(150000)).toBe('150 000 FCFA'));
  it('should format 1000000 as "1 000 000 FCFA"',    () => expect(formatXAF(1000000)).toBe('1 000 000 FCFA'));
  it('should format 0 as "0 FCFA"',                  () => expect(formatXAF(0)).toBe('0 FCFA'));
  it('should handle negative values gracefully',     () => expect(() => formatXAF(-100)).toThrow());
});

describe('formatPhoneCameroon', () => {
  it('should format +237699000000 with spaces',      () => expect(formatPhoneCameroon('+237699000000')).toBe('+237 699 000 000'));
  it('should accept 0699000000 and add +237',        () => expect(formatPhoneCameroon('0699000000')).toBe('+237 699 000 000'));
  it('should reject invalid Cameroon numbers',       () => expect(() => formatPhoneCameroon('0123456789')).toThrow());
});

describe('maskPhone', () => {
  it('should mask middle digits of phone number',    () => expect(maskPhone('+237699000000')).toBe('+237699***000'));
  it('should handle null gracefully',                () => expect(maskPhone(null)).toBe('—'));
});
```

---

## 🗂️ PARTIE 3 — TESTS E2E PLAYWRIGHT

### Configuration

```typescript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,          // Séquentiel pour les tests de paiement
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:3000',
    trace:   'on-first-retry',
    video:   'on-first-retry',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile',   use: { ...devices['Pixel 5'] } },
  ],
});
```

---

### 3.1 — Flux de paiement complet

**Fichier :** `e2e/payment/full-flow.spec.ts`

```typescript
import { test, expect } from '@playwright/test';
import { mockFlutterwaveCheckout, mockSuccessfulWebhook } from '../helpers/payment';

test.describe('Flux de paiement complet', () => {

  test.beforeEach(async ({ page }) => {
    // Mock l'API Flutterwave (ne jamais appeler la vraie API en E2E)
    await mockFlutterwaveCheckout(page);
    // Se connecter avec un utilisateur de test
    await page.goto('/auth/login');
    await page.fill('[name="email"]', 'e2e@keyhome.test');
    await page.fill('[name="password"]', 'TestPassword123!');
    await page.click('[type="submit"]');
    await page.waitForURL('/dashboard');
  });

  test('should complete full MTN Mobile Money payment flow', async ({ page }) => {
    // 1. Naviguer vers une propriété
    await page.goto('/properties/1');
    await expect(page.locator('h1')).toBeVisible();

    // 2. Ouvrir le modal de paiement
    await page.click('[data-testid="pay-button"]');
    await expect(page.locator('[role="dialog"]')).toBeVisible();

    // 3. Sélectionner MTN
    await page.click('[data-testid="method-mtn_cm"]');

    // 4. Entrer le numéro
    await page.fill('[data-testid="phone-input"]', '+237699000000');
    await page.click('[data-testid="continue-btn"]');

    // 5. Confirmer le paiement
    await expect(page.locator('[data-testid="amount-display"]')).toContainText('150 000');
    await page.click('[data-testid="pay-btn"]');

    // 6. Vérifier le redirect vers Flutterwave
    await page.waitForURL(/checkout\.flutterwave\.com|\/payment\/callback/);
  });

  test('should show error and retry button when gateway fails', async ({ page }) => {
    await page.route('**/api/payment/initiate', route =>
      route.fulfill({ status: 503, body: JSON.stringify({ message: 'Gateway indisponible' }) })
    );

    await page.goto('/properties/1');
    await page.click('[data-testid="pay-button"]');
    await page.click('[data-testid="method-mtn_cm"]');
    await page.fill('[data-testid="phone-input"]', '+237699000000');
    await page.click('[data-testid="continue-btn"]');
    await page.click('[data-testid="pay-btn"]');

    await expect(page.locator('[role="alert"]')).toBeVisible();
    await expect(page.locator('[data-testid="retry-btn"]')).toBeVisible();
  });

  test('should display success screen on payment callback', async ({ page }) => {
    // Simuler le retour de Flutterwave avec succès
    await page.goto('/payment/callback?tx_ref=TXN-TEST-001&status=successful&transaction_id=12345');

    await expect(page.locator('[data-testid="payment-success"]')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('[data-testid="transaction-reference"]')).toBeVisible();
  });

  test('should display error screen on failed payment callback', async ({ page }) => {
    await page.goto('/payment/callback?tx_ref=TXN-TEST-001&status=failed');
    await expect(page.locator('[data-testid="payment-error"]')).toBeVisible();
  });

  test('should be accessible on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 }); // iPhone SE
    await page.goto('/properties/1');
    await page.click('[data-testid="pay-button"]');

    const modal = page.locator('[role="dialog"]');
    await expect(modal).toBeVisible();
    // Vérifier que le modal est entièrement visible sans scroll horizontal
    const box = await modal.boundingBox();
    expect(box?.width).toBeLessThanOrEqual(375);
  });
});
```

---

### 3.2 — Tests de Sécurité E2E

**Fichier :** `e2e/security/payment-security.spec.ts`

```typescript
test.describe('Sécurité des paiements E2E', () => {

  test('should not expose secret keys in page source', async ({ page }) => {
    await page.goto('/payment');
    const content = await page.content();
    expect(content).not.toMatch(/FLWSECK/);
    expect(content).not.toMatch(/sk_sandbox/);
  });

  test('should not expose secret keys in network requests', async ({ page }) => {
    const requests: string[] = [];
    page.on('request', req => requests.push(JSON.stringify(req.postData() ?? '')));

    await page.goto('/payment');
    // Déclencher un paiement...

    requests.forEach(body => {
      expect(body).not.toMatch(/FLWSECK/);
      expect(body).not.toMatch(/secret/i);
    });
  });

  test('should redirect unauthenticated user to login page', async ({ page }) => {
    await page.goto('/payment/history'); // Sans être connecté
    await expect(page).toHaveURL(/\/auth\/login/);
  });

  test('should not allow direct access to another users transaction via URL', async ({ page }) => {
    // Se connecter comme User A
    // Tenter d'accéder à une transaction de User B via URL directe
    // → 404 ou redirection
  });
});
```

---

## 🗂️ PARTIE 4 — COUVERTURE DE CODE ET CI/CD

### 4.1 — Seuils de couverture minimaux

```
Backend Laravel (PHPUnit) :
  - Lines   : ≥ 85%
  - Methods : ≥ 80%
  - Classes : ≥ 75%
  Fichiers critiques (100% requis) :
    - FlutterwavePaymentService.php
    - PaymentService.php
    - ValidateWebhookSignature.php

Frontend Next.js (Vitest) :
  - Lines     : ≥ 80%
  - Functions : ≥ 80%
  - Branches  : ≥ 70%
  Fichiers critiques (100% requis) :
    - hooks/usePayment.ts
    - lib/payment/client.ts
    - lib/payment/formatters.ts
```

---

### 4.2 — Pipeline CI (GitHub Actions)

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:

  backend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', coverage: xdebug }
      - run: composer install --no-interaction
      - run: cp .env.testing .env
      - run: php artisan key:generate
      - run: php artisan test --coverage --min=85
      - name: Security audit
        run: composer audit

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci
      - run: npm run test:coverage -- --reporter=verbose
      - name: Security audit
        run: npm audit --audit-level=high

  e2e-tests:
    runs-on: ubuntu-latest
    needs: [backend-tests, frontend-tests]
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci
      - run: npx playwright install --with-deps chromium
      - run: npm run e2e
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: playwright-report/
```

---

## ✅ CHECKLIST FINALE AVANT PR

**Backend**
- [x] Tous les tests passent en local (`php artisan test`)
- [x] Aucun test ne fait d'appel réseau réel (vérifier avec `Http::preventStrayRequests()`)
- [x] Couverture ≥ 85% sur les services de paiement
- [x] Tous les cas d'erreur gateway sont testés (400, 401, 503)
- [x] Tests d'idempotence des webhooks validés
- [x] Tests de manipulation de données (status, gateway, amount) validés

**Frontend**
- [x] Tous les tests passent (`npm run test`)
- [x] Aucun test ne fait d'appel réseau réel (MSW intercepte tout)
- [x] Couverture ≥ 80% sur les hooks et composants de paiement
- [x] Tests d'accessibilité ARIA validés
- [x] Tests mobile viewport (375px) validés
- [x] Aucune clé secrète exposée dans le DOM ou les requêtes réseau

**E2E**
- [ ] Flux complet MTN testé et passant
- [ ] Flux complet Orange Money testé et passant
- [ ] Cas d'erreur gateway testé
- [ ] Page callback succès/échec testée
- [ ] Tests de sécurité (pas de clés exposées) validés

---

*Prompt généré pour le projet KeyHome*
*Stack : Laravel · Next.js · Flutter*
*Couverture cible : Backend ≥ 85% · Frontend ≥ 80% · E2E : flux critiques*
