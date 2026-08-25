<?php

declare(strict_types=1);

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\StripeSavedCardServiceInterface;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Events\PaymentSucceeded;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\StripeCustomerMissingException;
use App\Models\PointPackage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use App\Services\Payment\StripeSavedCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stripe saved cards — list / delete / set-default / setup-intent / reuse
|--------------------------------------------------------------------------
|
| We never hit Stripe's real API. The `StripePaymentService` is rebound to
| a fake implementation that records call args and returns canned values,
| and the user is pre-seeded with a `stripe_id` so Cashier's
| `hasStripeId()` short-circuits before any HTTP call.
*/

/**
 * Build a fake Stripe gateway with configurable responses.
 *
 * @param  array{
 *     list?: array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>,
 *     initiate?: array{link: string, tx_ref: string, status: string, gateway: string},
 *     setup?: array{client_secret: string, id: string},
 *     throwOn?: array<string>
 * }  $config
 */
function fakeStripeService(array $config = []): PaymentGatewayInterface
{
    return new class($config) implements PaymentGatewayInterface, StripeSavedCardServiceInterface
    {
        /** @var array<string, array<int, array<string, mixed>>> */
        public array $calls = [
            'initiate' => [],
            'listSavedCards' => [],
            'detachSavedCard' => [],
            'setDefaultSavedCard' => [],
            'createSetupIntent' => [],
        ];

        /**
         * @param  array{
         *     list?: array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>,
         *     initiate?: array{link: string, tx_ref: string, status: string, gateway: string},
         *     setup?: array{client_secret: string, id: string},
         *     throwOn?: array<string>
         * }  $config
         */
        public function __construct(public array $config = []) {}

        public function getName(): string
        {
            return 'stripe';
        }

        public function initiate(array $payload): array
        {
            $this->calls['initiate'][] = $payload;

            // Simule un Customer périmé au PREMIER appel uniquement — le
            // PaymentService doit alors recréer un Customer et réussir au second.
            if (($this->config['missingCustomerOnce'] ?? false) && count($this->calls['initiate']) === 1) {
                throw new StripeCustomerMissingException('No such customer: '.($payload['customer_id'] ?? ''));
            }

            if (in_array('initiate', $this->config['throwOn'] ?? [], true)) {
                throw new PaymentGatewayException('Stripe initiate failed (forced).');
            }

            $paymentMethodId = $payload['payment_method_id'] ?? null;

            return $this->config['initiate'] ?? [
                'link' => $paymentMethodId !== null
                    ? 'pi_test_default_secret_xxx'
                    : 'cs_test_default_secret_xxx',
                'tx_ref' => (string) ($payload['tx_ref'] ?? 'KH-FAKE'),
                'status' => 'pending',
                'gateway' => 'stripe',
                'stripe_flow' => $paymentMethodId !== null ? 'payment_intent' : 'checkout_session',
            ];
        }

        public function verify(string $externalReference): array
        {
            return ['status' => 'pending', 'amount' => 0.0, 'currency' => 'XAF', 'payment_method' => null, 'paid_at' => null, 'raw' => []];
        }

        public function handleWebhook(array $payload, array $headers, ?string $rawBody = null): array
        {
            return ['event' => '', 'tx_ref' => '', 'status' => 'pending', 'amount' => 0.0, 'currency' => 'XAF', 'payment_method' => null, 'raw' => []];
        }

        public function refund(string $gatewayTransactionId, ?float $amount = null): array
        {
            return ['refund_id' => '', 'status' => 'pending', 'amount_refunded' => 0.0, 'raw' => []];
        }

        /**
         * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
         */
        public function listSavedCards(string $customerId): array
        {
            $this->calls['listSavedCards'][] = ['customer_id' => $customerId];

            if (in_array('listSavedCards', $this->config['throwOn'] ?? [], true)) {
                throw new PaymentGatewayException('Stripe list failed.');
            }

            return $this->config['list'] ?? [];
        }

        public function detachSavedCard(string $customerId, string $paymentMethodId): void
        {
            $this->calls['detachSavedCard'][] = [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
            ];

            if (in_array('detachSavedCard', $this->config['throwOn'] ?? [], true)) {
                throw new PaymentGatewayException('Stripe detach failed.');
            }
        }

        public function setDefaultSavedCard(string $customerId, string $paymentMethodId): void
        {
            $this->calls['setDefaultSavedCard'][] = [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
            ];

            if (in_array('setDefaultSavedCard', $this->config['throwOn'] ?? [], true)) {
                throw new PaymentGatewayException('Stripe set-default failed.');
            }
        }

        /** @return array{client_secret: string, id: string} */
        public function createSetupIntent(string $customerId): array
        {
            $this->calls['createSetupIntent'][] = ['customer_id' => $customerId];

            // Simule un Customer périmé au PREMIER appel uniquement — le
            // contrôleur doit alors recréer un Customer et réussir au second.
            if (($this->config['missingCustomerOnce'] ?? false) && count($this->calls['createSetupIntent']) === 1) {
                throw new StripeCustomerMissingException('No such customer: '.$customerId);
            }

            if (in_array('createSetupIntent', $this->config['throwOn'] ?? [], true)) {
                throw new PaymentGatewayException('Stripe setup-intent failed.');
            }

            return $this->config['setup'] ?? [
                'client_secret' => 'seti_test_secret_xxx',
                'id' => 'seti_test_xxx',
            ];
        }
    };
}

/**
 * Rebind the Stripe gateway stub so `PaymentService` resolves it on the
 * next access (the service is a singleton snapshotting the registry).
 */
function bindStripeService(PaymentGatewayInterface $stub): PaymentGatewayInterface
{
    app()->instance(StripePaymentService::class, $stub);
    // The interface binding feeds `StripePaymentMethodController`. Both
    // bindings must point at the same anonymous instance so call args
    // recorded on the stub are visible to assertions below.
    app()->instance(StripeSavedCardServiceInterface::class, $stub);
    app()->forgetInstance(PaymentService::class);

    return $stub;
}

/**
 * Bind a duck-typed `StripeClient` whose `customers->create()` returns a
 * fresh Customer id — simule la création Cashier `createAsStripeCustomer()`
 * sans appeler la vraie API Stripe (utilisé par les tests d'auto-réparation).
 */
function bindFreshCustomerStripeClient(string $freshCustomerId): void
{
    // `extends StripeClient` : les propriétés publiques prennent le pas sur
    // le `__get` magique du SDK, et le typage `StripeClient` est respecté
    // (le vrai service a une propriété typée `private StripeClient $stripe`).
    // NB : `bind` (closure) et non `instance` — Cashier résout le client avec
    // `app(StripeClient::class, ['config' => …])` et le conteneur IGNORE une
    // instance partagée dès que des paramètres sont fournis.
    app()->bind(StripeClient::class, fn () => new class($freshCustomerId) extends StripeClient
    {
        public object $customers;

        public function __construct(string $freshCustomerId)
        {
            parent::__construct('sk_test_fake_FOR_TESTS_ONLY');

            $this->customers = new readonly class($freshCustomerId)
            {
                public function __construct(private string $freshCustomerId) {}

                /** @param array<string, mixed> $options */
                public function create(array $options = [], mixed $requestOptions = null): object
                {
                    return (object) ['id' => $this->freshCustomerId];
                }
            };
        }
    });
}

beforeEach(function (): void {
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'test_webhook_secret_123');
    config()->set('services.stripe.secret', 'sk_test_fake_FOR_TESTS_ONLY');
});

// ────────────────────────────────────────────────────────────────────
// GET /payments/stripe/payment-methods
// ────────────────────────────────────────────────────────────────────

it('returns an empty list when the user has no Stripe Customer yet', function (): void {
    $user = User::factory()->create();

    bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->getJson('/api/v1/payments/stripe/payment-methods')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('lists saved cards for a user with a Stripe Customer', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    bindStripeService(fakeStripeService([
        'list' => [
            ['id' => 'pm_card_1', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030, 'is_default' => true],
            ['id' => 'pm_card_2', 'brand' => 'mastercard', 'last4' => '4444', 'exp_month' => 6, 'exp_year' => 2031, 'is_default' => false],
        ],
    ]));

    $this->actingAs($user)
        ->getJson('/api/v1/payments/stripe/payment-methods')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'pm_card_1')
        ->assertJsonPath('data.0.brand', 'visa')
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonPath('data.1.id', 'pm_card_2')
        ->assertJsonPath('data.1.is_default', false);
});

it('surfaces a 503 when Stripe is unreachable on list', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_999']);

    bindStripeService(fakeStripeService(['throwOn' => ['listSavedCards']]));

    $this->actingAs($user)
        ->getJson('/api/v1/payments/stripe/payment-methods')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Impossible de récupérer vos cartes. Veuillez réessayer.');
});

it('returns an empty list when the stored stripe_id points to a deleted Customer', function (): void {
    // Couvre le cas réel : stripe_id créé avec d'anciennes clés (test) alors
    // que le backend tourne en clés live — Stripe répond resource_missing et
    // le service renvoie [] au lieu d'une erreur (le profil affiche alors
    // « Aucune carte enregistrée » plutôt qu'un message d'échec).
    $user = User::factory()->create(['stripe_id' => 'cus_stale_123']);

    bindStripeService(fakeStripeService(['list' => []]));

    $this->actingAs($user)
        ->getJson('/api/v1/payments/stripe/payment-methods')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('rejects unauthenticated access to the saved-cards endpoints', function (): void {
    $this->getJson('/api/v1/payments/stripe/payment-methods')->assertUnauthorized();
    $this->deleteJson('/api/v1/payments/stripe/payment-methods/pm_x')->assertUnauthorized();
    $this->postJson('/api/v1/payments/stripe/payment-methods/pm_x/set-default')->assertUnauthorized();
    $this->postJson('/api/v1/payments/stripe/setup-intent')->assertUnauthorized();
});

// ────────────────────────────────────────────────────────────────────
// DELETE /payments/stripe/payment-methods/{pm}
// ────────────────────────────────────────────────────────────────────

it('detaches a saved card', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);
    $stub = bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->deleteJson('/api/v1/payments/stripe/payment-methods/pm_valid_123')
        ->assertNoContent();

    expect($stub->calls['detachSavedCard'])->toHaveCount(1)
        ->and($stub->calls['detachSavedCard'][0]['customer_id'])->toBe('cus_test_123')
        ->and($stub->calls['detachSavedCard'][0]['payment_method_id'])->toBe('pm_valid_123');
});

it('rejects a malformed payment method id on delete', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);
    bindStripeService(fakeStripeService());

    // The route constraint `pm_[A-Za-z0-9_]+` makes the malformed segment
    // return a 404 (no matching route). This is the desired defence in
    // depth on top of the FormRequest regex.
    $this->actingAs($user)
        ->deleteJson('/api/v1/payments/stripe/payment-methods/cus_evil')
        ->assertNotFound();
});

it('returns 404 when the user has no Stripe Customer for delete', function (): void {
    $user = User::factory()->create();
    bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->deleteJson('/api/v1/payments/stripe/payment-methods/pm_doesnt_matter')
        ->assertNotFound();
});

// ────────────────────────────────────────────────────────────────────
// POST /payments/stripe/payment-methods/{pm}/set-default
// ────────────────────────────────────────────────────────────────────

it('marks a saved card as default', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);
    $stub = bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->postJson('/api/v1/payments/stripe/payment-methods/pm_to_default/set-default')
        ->assertOk()
        ->assertJsonPath('message', 'Carte définie comme moyen de paiement par défaut.');

    expect($stub->calls['setDefaultSavedCard'])->toHaveCount(1)
        ->and($stub->calls['setDefaultSavedCard'][0]['payment_method_id'])->toBe('pm_to_default');
});

// ────────────────────────────────────────────────────────────────────
// POST /payments/stripe/setup-intent
// ────────────────────────────────────────────────────────────────────

it('returns a SetupIntent client secret for an existing Stripe Customer', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);
    $stub = bindStripeService(fakeStripeService([
        'setup' => ['client_secret' => 'seti_abc_secret_def', 'id' => 'seti_abc'],
    ]));

    $this->actingAs($user)
        ->postJson('/api/v1/payments/stripe/setup-intent')
        ->assertOk()
        ->assertJsonPath('data.client_secret', 'seti_abc_secret_def')
        ->assertJsonPath('data.id', 'seti_abc');

    expect($stub->calls['createSetupIntent'])->toHaveCount(1)
        ->and($stub->calls['createSetupIntent'][0]['customer_id'])->toBe('cus_test_123');
});

it('self-heals a stale stripe_id on setup-intent and retries with a fresh Customer', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_stale_123']);

    bindFreshCustomerStripeClient('cus_fresh_789');
    $stub = bindStripeService(fakeStripeService(['missingCustomerOnce' => true]));

    $this->actingAs($user)
        ->postJson('/api/v1/payments/stripe/setup-intent')
        ->assertOk()
        ->assertJsonPath('data.client_secret', 'seti_test_secret_xxx');

    expect($stub->calls['createSetupIntent'])->toHaveCount(2)
        ->and($stub->calls['createSetupIntent'][0]['customer_id'])->toBe('cus_stale_123')
        ->and($stub->calls['createSetupIntent'][1]['customer_id'])->toBe('cus_fresh_789')
        ->and($user->fresh()->stripe_id)->toBe('cus_fresh_789');
});

it('self-heals a stale stripe_id during off-session payment and retries without the saved card', function (): void {
    Event::fake();

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_stale_123']);

    bindFreshCustomerStripeClient('cus_fresh_789');
    $stub = bindStripeService(fakeStripeService(['missingCustomerOnce' => true]));

    // La carte « pm_gone_123 » appartenait à l'ancien Customer : le retry
    // doit se faire en saisie classique (payment_method_id retiré).
    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
            'payment_method' => 'card',
            'payment_method_id' => 'pm_gone_123',
        ])
        ->assertOk()
        ->assertJsonPath('gateway', 'stripe');

    expect($stub->calls['initiate'])->toHaveCount(2)
        ->and($stub->calls['initiate'][0]['customer_id'])->toBe('cus_stale_123')
        ->and($stub->calls['initiate'][0]['payment_method_id'])->toBe('pm_gone_123')
        ->and($stub->calls['initiate'][1]['customer_id'])->toBe('cus_fresh_789')
        ->and($stub->calls['initiate'][1]['payment_method_id'])->toBeNull()
        ->and($user->fresh()->stripe_id)->toBe('cus_fresh_789');
});

// ────────────────────────────────────────────────────────────────────
// StripePaymentService (réel) — gestion resource_missing
// ────────────────────────────────────────────────────────────────────

/**
 * Bind a duck-typed `StripeClient` qui répond `resource_missing` partout —
 * simule un stripe_id périmé contre le VRAI service (pas le fake).
 */
function bindMissingCustomerStripeClient(): void
{
    $exception = new InvalidRequestException('No such customer: cus_stale');
    $exception->setStripeCode('resource_missing');

    // `bind` (closure) et non `instance` : voir bindFreshCustomerStripeClient().
    app()->bind(StripeClient::class, fn () => new class($exception) extends StripeClient
    {
        public object $paymentMethods;

        public object $customers;

        public object $setupIntents;

        public function __construct(private readonly InvalidRequestException $exception)
        {
            parent::__construct('sk_test_fake_FOR_TESTS_ONLY');

            $this->paymentMethods = new readonly class($exception)
            {
                public function __construct(private InvalidRequestException $e) {}

                /** @param array<string, mixed> $params */
                public function all(array $params): never
                {
                    throw $this->e;
                }
            };
            $this->customers = new readonly class($exception)
            {
                public function __construct(private InvalidRequestException $e) {}

                public function retrieve(string $id): never
                {
                    throw $this->e;
                }
            };
            $this->setupIntents = new readonly class($exception)
            {
                public function __construct(private InvalidRequestException $e) {}

                /** @param array<string, mixed> $params */
                public function create(array $params): never
                {
                    throw $this->e;
                }
            };
        }
    });
}

it('returns an empty card list from the real service when Stripe reports a missing customer', function (): void {
    bindMissingCustomerStripeClient();

    $service = new StripeSavedCardService;

    expect($service->listSavedCards('cus_stale'))->toBe([]);
});

it('throws StripeCustomerMissingException from the real service on setup-intent for a missing customer', function (): void {
    bindMissingCustomerStripeClient();

    $service = new StripeSavedCardService;

    $service->createSetupIntent('cus_stale');
})->throws(StripeCustomerMissingException::class);

// ────────────────────────────────────────────────────────────────────
// POST /credits/purchase/{package} — save_payment_method=true
// ────────────────────────────────────────────────────────────────────

it('forwards save_payment_method=true to the Stripe gateway when paying for a credit pack', function (): void {
    Event::fake();

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_save_card_123']);

    $stub = bindStripeService(fakeStripeService([
        'initiate' => [
            'link' => 'pi_save_secret_yyy',
            'tx_ref' => 'KH-WILL-BE-OVERWRITTEN',
            'status' => 'pending',
            'gateway' => 'stripe',
        ],
    ]));

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'payment_method' => 'card',
            'save_payment_method' => true,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('gateway', 'stripe');

    expect($stub->calls['initiate'])->toHaveCount(1)
        ->and($stub->calls['initiate'][0]['customer_id'])->toBe('cus_save_card_123')
        ->and($stub->calls['initiate'][0]['save_payment_method'])->toBeTrue()
        ->and($stub->calls['initiate'][0]['payment_method_id'])->toBeNull();
});

it('returns stripe_flow checkout_session for a new card initiate', function (): void {
    Event::fake();

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_new_card_123']);

    bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
            'payment_method' => 'card',
            'save_payment_method' => true,
        ])
        ->assertOk()
        ->assertJsonPath('gateway', 'stripe')
        ->assertJsonPath('stripe_flow', 'checkout_session')
        ->assertJsonPath('status', 'pending');
});

// ────────────────────────────────────────────────────────────────────
// POST /credits/purchase/{package} — reuse saved card off-session
// ────────────────────────────────────────────────────────────────────

it('marks the payment SUCCESS immediately when reusing a saved card off-session', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_reuse_123']);

    $stub = bindStripeService(fakeStripeService([
        'initiate' => [
            'link' => 'pi_reuse_secret_xxx',
            'tx_ref' => 'KH-REUSE',
            'status' => 'success',
            'gateway' => 'stripe',
        ],
    ]));

    $response = $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'payment_method' => 'card',
            'payment_method_id' => 'pm_saved_visa_4242',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('gateway', 'stripe');

    $txRef = (string) $response->json('tx_ref');

    $this->assertDatabaseHas('payments', [
        'transaction_id' => $txRef,
        'user_id' => $user->id,
        'status' => PaymentStatus::SUCCESS->value,
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::CARD->value,
        'type' => PaymentType::CREDIT->value,
    ]);

    expect($stub->calls['initiate'][0]['payment_method_id'])->toBe('pm_saved_visa_4242');

    Event::assertDispatched(PaymentSucceeded::class);
});

it('marks the payment FAILED when the reused saved card is declined', function (): void {
    Event::fake();

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_decline_123']);

    bindStripeService(fakeStripeService([
        'initiate' => [
            'link' => 'pi_failed_secret_zzz',
            'tx_ref' => 'KH-FAILED',
            'status' => 'failed',
            'gateway' => 'stripe',
        ],
    ]));

    $response = $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'payment_method' => 'card',
            'payment_method_id' => 'pm_declined_card',
        ]);

    $response->assertOk()->assertJsonPath('status', 'failed');

    $this->assertDatabaseHas('payments', [
        'transaction_id' => (string) $response->json('tx_ref'),
        'status' => PaymentStatus::FAILED->value,
    ]);
});

// ────────────────────────────────────────────────────────────────────
// Form Request validation
// ────────────────────────────────────────────────────────────────────

it('rejects save_payment_method=true outside the card flow', function (): void {
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'payment_method' => 'mobile_money',
            'save_payment_method' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['save_payment_method']);
});

it('rejects a malformed payment_method_id format', function (): void {
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    bindStripeService(fakeStripeService());

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'payment_method' => 'card',
            'payment_method_id' => 'cus_smuggled_in',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_method_id']);
});
