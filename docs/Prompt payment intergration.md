# 🏦 PROMPT TECHNIQUE COMPLET — Intégration Paiement Flutterwave + FedaPay
## Stack : Laravel (Backend) · Next.js (Frontend) · Flutter (Mobile)

> **Contexte :** Application immobilière avec deux agrégateurs de paiement en parallèle.
> FedaPay est déjà intégré. Flutterwave doit être intégré proprement, puis FedaPay sera retiré.
> Ce prompt couvre la totalité de l'implémentation : backend, frontend, mobile, sécurité, docs.

---

## 🎯 MISSION GLOBALE

Tu vas implémenter un système de paiement complet et sécurisé dans une application immobilière.
L'objectif est d'avoir **Flutterwave et FedaPay qui coexistent**, avec une architecture propre permettant
de retirer FedaPay en une seule opération sans toucher au reste du code.

---

## 📐 ARCHITECTURE CIBLE

```
┌─────────────────┐     ┌─────────────────┐     ┌──────────────────┐
│   Next.js App   │     │   Flutter App   │     │  Webhook Handler │
│   (Frontend)    │     │    (Mobile)     │     │   (External)     │
└────────┬────────┘     └────────┬────────┘     └────────┬─────────┘
         │                       │                        │
         │   REST API (HTTPS)    │                        │ HTTPS POST
         └───────────┬───────────┘                        │
                     ▼                                     ▼
         ┌───────────────────────────────────────────────────────┐
         │                  Laravel Backend                       │
         │  ┌─────────────────────────────────────────────────┐  │
         │  │           PaymentGatewayInterface                │  │
         │  │  ┌───────────────┐   ┌────────────────────────┐ │  │
         │  │  │  Flutterwave  │   │       FedaPay          │ │  │
         │  │  │   Provider    │   │       Provider         │ │  │
         │  │  └───────┬───────┘   └──────────┬─────────────┘ │  │
         │  └──────────┼──────────────────────┼───────────────┘  │
         │             ▼                       ▼                   │
         │  ┌──────────────────────────────────────────────────┐  │
         │  │     PaymentService (Orchestrateur central)        │  │
         │  └──────────────────────────────────────────────────┘  │
         └───────────────────────────────────────────────────────┘
                     │                        │
                     ▼                        ▼
         ┌──────────────────┐     ┌───────────────────────┐
         │   Flutterwave    │     │       FedaPay         │
         │   (production)   │     │    (production)       │
         └──────────────────┘     └───────────────────────┘
```

---

## 🗂️ PARTIE 1 — BACKEND LARAVEL

### 1.1 — Structure des fichiers à créer

```
app/
├── Contracts/
│   └── PaymentGatewayInterface.php          # Contrat abstrait
├── Services/
│   ├── Payment/
│   │   ├── PaymentService.php               # Orchestrateur
│   │   ├── FlutterwavePaymentService.php    # Implémentation Flutterwave
│   │   └── FedaPayPaymentService.php        # Implémentation FedaPay (existant, à adapter)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── PaymentController.php        # Controller principal
│   └── Requests/
│       ├── InitiatePaymentRequest.php       # Validation
│       └── VerifyPaymentRequest.php
├── Models/
│   └── Transaction.php                      # Modèle de transaction
├── Events/
│   ├── PaymentInitiated.php
│   ├── PaymentSucceeded.php
│   └── PaymentFailed.php
├── Listeners/
│   └── SendPaymentNotification.php
└── Enums/
    ├── PaymentGateway.php                   # Enum: FLUTTERWAVE, FEDAPAY
    ├── PaymentStatus.php                    # Enum: PENDING, SUCCESS, FAILED, CANCELLED
    └── PaymentType.php                      # Enum: RENT, PURCHASE, DEPOSIT, SUBSCRIPTION

database/migrations/
└── xxxx_create_transactions_table.php

config/
└── payment.php                              # Config centralisée
```

---

### 1.2 — Migration : Table `transactions`

Crée la migration suivante. Elle doit couvrir tous les cas d'usage immobiliers.

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->uuid('reference')->unique();               // Référence interne unique
    $table->string('external_reference')->nullable();  // Référence Flutterwave/FedaPay
    $table->foreignId('user_id')->constrained();
    $table->nullableMorphs('payable');                 // polymorphique: Property, Listing, etc.
    $table->string('gateway');                         // 'flutterwave' | 'fedapay'
    $table->string('status')->default('pending');      // pending|success|failed|cancelled
    $table->string('type');                            // rent|purchase|deposit|subscription
    $table->decimal('amount', 15, 2);
    $table->string('currency', 3)->default('XAF');
    $table->string('phone_number')->nullable();
    $table->string('payment_method')->nullable();      // mtn_cm | orange_cm | card
    $table->json('gateway_response')->nullable();      // Réponse brute du gateway
    $table->json('metadata')->nullable();              // Données métier libres
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Index pour performances
    $table->index(['user_id', 'status']);
    $table->index(['gateway', 'external_reference']);
    $table->index('status');
});
```

---

### 1.3 — Contrat `PaymentGatewayInterface`

```php
<?php
namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Initier un paiement. Retourne les données nécessaires au front
     * (url de redirection ou instructions USSD).
     */
    public function initiate(array $payload): array;

    /**
     * Vérifier le statut d'une transaction par sa référence externe.
     */
    public function verify(string $externalReference): array;

    /**
     * Valider et parser un webhook entrant.
     * Doit vérifier la signature et retourner les données normalisées.
     */
    public function handleWebhook(array $payload, array $headers): array;

    /**
     * Retourner l'identifiant du gateway (ex: 'flutterwave').
     */
    public function getName(): string;
}
```

---

### 1.4 — Configuration `config/payment.php`

```php
return [
    'default' => env('PAYMENT_GATEWAY', 'flutterwave'),

    'gateways' => [
        'flutterwave' => [
            'public_key'    => env('FLW_PUBLIC_KEY'),
            'secret_key'    => env('FLW_SECRET_KEY'),
            'encryption_key'=> env('FLW_ENCRYPTION_KEY'),
            'webhook_secret'=> env('FLW_WEBHOOK_SECRET'),
            'base_url'      => 'https://api.flutterwave.com/v3',
            'redirect_url'  => env('FLW_REDIRECT_URL'),
            'logo'          => env('APP_LOGO_URL'),
        ],
        'fedapay' => [
            'api_key'       => env('FEDAPAY_API_KEY'),
            'base_url'      => env('FEDAPAY_BASE_URL', 'https://api.fedapay.com'),
            'webhook_secret'=> env('FEDAPAY_WEBHOOK_SECRET'),
        ],
    ],

    'supported_currencies' => ['XAF', 'XOF', 'GHS', 'NGN'],
    'default_currency'     => env('PAYMENT_DEFAULT_CURRENCY', 'XAF'),

    'mobile_money_operators' => [
        'cameroon' => ['mtn_cm', 'orange_cm'],
        'senegal'  => ['orange_sn', 'free_sn'],
        'ghana'    => ['mtn_gh', 'vodafone_gh', 'airtel_tigo_gh'],
    ],
];
```

---

### 1.5 — Variables `.env` à ajouter

```dotenv
# ─── PAYMENT GATEWAY ───────────────────────────────────────────────
PAYMENT_GATEWAY=flutterwave
PAYMENT_DEFAULT_CURRENCY=XAF

# ─── FLUTTERWAVE ───────────────────────────────────────────────────
FLW_PUBLIC_KEY=FLWPUBK_TEST-xxxxxxxxxxxx
FLW_SECRET_KEY=FLWSECK_TEST-xxxxxxxxxxxx
FLW_ENCRYPTION_KEY=xxxxxxxxxxxxxxxx
FLW_WEBHOOK_SECRET=your_webhook_secret_here
FLW_REDIRECT_URL=https://your-domain.com/payment/callback

# ─── FEDAPAY (existant — garder tel quel) ──────────────────────────
FEDAPAY_API_KEY=sk_sandbox_xxxxxxxxxxxx
FEDAPAY_BASE_URL=https://api.fedapay.com
FEDAPAY_WEBHOOK_SECRET=your_fedapay_webhook_secret
```

---

### 1.6 — Service `FlutterwavePaymentService`

Implémente `PaymentGatewayInterface`. Le service doit :

**Méthode `initiate(array $payload)`**
- Valider les champs requis : `amount`, `currency`, `email`, `phone`, `name`, `payment_type`, `tx_ref`
- Construire le payload Flutterwave avec : `tx_ref`, `amount`, `currency`, `payment_options` (mobilemoneycameroon),
  `customer` (email, phonenumber, name), `customizations` (title, logo), `meta` (internal data)
- Appeler `POST /payments` sur l'API Flutterwave avec Bearer token `FLW_SECRET_KEY`
- Retourner : `{ link, tx_ref, status, gateway: 'flutterwave' }`

**Méthode `verify(string $externalReference)`**
- Appeler `GET /transactions/{id}/verify` OU `GET /transactions/verify_by_reference?tx_ref={ref}`
- Normaliser la réponse : `{ status, amount, currency, payment_method, paid_at, raw }`
- Valider que `amount` correspond à la transaction initiale (protection contre la falsification)

**Méthode `handleWebhook(array $payload, array $headers)`**
- Récupérer le header `verif-hash`
- Comparer avec `FLW_WEBHOOK_SECRET` via comparaison sécurisée `hash_equals()`
- Si invalide → lancer `UnauthorizedException`
- Parser les événements : `charge.completed`, `transfer.completed`
- Retourner données normalisées

---

### 1.7 — `PaymentService` (Orchestrateur)

```php
class PaymentService
{
    private PaymentGatewayInterface $gateway;

    public function __construct()
    {
        $gatewayName = config('payment.default');
        $this->gateway = match($gatewayName) {
            'flutterwave' => app(FlutterwavePaymentService::class),
            'fedapay'     => app(FedaPayPaymentService::class),
            default       => throw new \InvalidArgumentException("Gateway {$gatewayName} non supporté"),
        };
    }

    public function createTransaction(array $data): Transaction
    {
        // 1. Créer l'enregistrement en base avec status=pending
        // 2. Appeler $this->gateway->initiate(...)
        // 3. Mettre à jour la transaction avec l'external_reference
        // 4. Fire event PaymentInitiated
        // 5. Logger avec contexte (user_id, amount, gateway)
        // 6. Retourner la transaction avec le lien de paiement
    }

    public function verifyTransaction(string $reference): Transaction
    {
        // 1. Retrouver la transaction par référence interne
        // 2. Si déjà success → retourner directement (idempotence)
        // 3. Appeler $this->gateway->verify(external_reference)
        // 4. Mettre à jour le status en base
        // 5. Fire event PaymentSucceeded ou PaymentFailed
        // 6. Retourner la transaction mise à jour
    }

    public function processWebhook(array $payload, array $headers, string $gatewayName): void
    {
        // 1. Résoudre le bon gateway (pas forcément le default)
        // 2. Valider la signature du webhook
        // 3. Retrouver la transaction
        // 4. Appliquer le nouveau statut (idempotent)
        // 5. Fire les events appropriés
    }
}
```

---

### 1.8 — `PaymentController`

Créer les endpoints suivants avec validation stricte :

```
POST   /api/v1/payments/initiate            → Initier un paiement
GET    /api/v1/payments/{reference}         → Statut d'une transaction
POST   /api/v1/payments/{reference}/verify  → Vérifier après callback
POST   /api/v1/webhooks/flutterwave         → Webhook Flutterwave (public, signé)
POST   /api/v1/webhooks/fedapay             → Webhook FedaPay (public, signé)
GET    /api/v1/payments/history             → Historique utilisateur connecté
```

**Sécurité sur tous les endpoints :**
- Rate limiting : `throttle:60,1` sur les endpoints publics
- Rate limiting plus strict : `throttle:5,1` sur `initiate` (anti-spam)
- Middleware `auth:sanctum` sur tous sauf webhooks
- Middleware custom `ValidateWebhookSignature` sur les routes webhook

---

### 1.9 — Middleware `ValidateWebhookSignature`

```php
// app/Http/Middleware/ValidateWebhookSignature.php
// - Lire le raw body AVANT que Laravel le parse
// - Récupérer la signature dans les headers
// - Comparer avec HMAC-SHA256 du raw body + secret
// - Rejeter avec 401 si invalide
// - Logger chaque tentative invalide avec l'IP source
```

---

### 1.10 — Routes

```php
// routes/api.php
Route::prefix('v1')->group(function () {

    // Webhooks — PAS d'auth, mais signature validée
    Route::post('/webhooks/flutterwave', [PaymentController::class, 'flutterwaveWebhook'])
        ->middleware('validate.webhook:flutterwave')
        ->withoutMiddleware(['throttle:api']);

    Route::post('/webhooks/fedapay', [PaymentController::class, 'fedapayWebhook'])
        ->middleware('validate.webhook:fedapay')
        ->withoutMiddleware(['throttle:api']);

    // Endpoints authentifiés
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/payments/initiate', [PaymentController::class, 'initiate'])
            ->middleware('throttle:5,1');
        Route::get('/payments/history', [PaymentController::class, 'history']);
        Route::get('/payments/{reference}', [PaymentController::class, 'show']);
        Route::post('/payments/{reference}/verify', [PaymentController::class, 'verify']);
    });
});
```

---

### 1.11 — Ressource API `TransactionResource`

La ressource doit exposer ces champs (jamais les clés API, jamais `gateway_response` brute) :

```json
{
  "id": "uuid",
  "reference": "TXN-2025-XXXXXXXX",
  "status": "success",
  "type": "rent",
  "amount": 150000,
  "currency": "XAF",
  "gateway": "flutterwave",
  "payment_method": "mtn_cm",
  "phone_number": "+237699000000",
  "paid_at": "2025-03-07T10:30:00Z",
  "property": { "id": 1, "title": "Appartement T3 Bastos" },
  "created_at": "2025-03-07T10:25:00Z"
}
```

---

### 1.12 — Tests

Créer les tests suivants avec PHPUnit :

```
tests/
├── Unit/
│   ├── FlutterwavePaymentServiceTest.php   # Test unitaire avec mock HTTP
│   └── PaymentServiceTest.php
└── Feature/
    ├── PaymentInitiateTest.php             # Test d'intégration endpoint
    ├── PaymentWebhookTest.php              # Test webhook avec signature
    └── PaymentVerifyTest.php
```

**Scénarios à couvrir :**
- Initiation réussie → transaction créée en base avec status=pending
- Initiation échouée (gateway error) → transaction marquée failed, exception propre
- Webhook valide → transaction mise à jour, event fired
- Webhook avec signature invalide → 401, rien en base
- Double webhook (idempotence) → pas de doublon en base
- Vérification d'une transaction déjà success → retourne directement sans appel API
- Rate limiting sur `/initiate` : 6ème requête → 429

---

## 🗂️ PARTIE 2 — FRONTEND NEXT.JS

### 2.1 — Structure des fichiers

```
src/
├── app/
│   ├── payment/
│   │   ├── page.tsx                        # Page de paiement principale
│   │   ├── callback/
│   │   │   └── page.tsx                    # Page de retour après paiement
│   │   └── history/
│   │       └── page.tsx                    # Historique des transactions
│   └── api/
│       └── payment/
│           ├── initiate/route.ts           # Proxy vers Laravel
│           └── verify/route.ts
├── components/
│   └── payment/
│       ├── PaymentModal.tsx                # Modal principal
│       ├── PaymentMethodSelector.tsx       # Choix MTN / Orange / Card
│       ├── PaymentAmountDisplay.tsx        # Affichage montant formaté XAF
│       ├── PaymentStatusBadge.tsx          # Badge statut coloré
│       ├── PaymentHistoryTable.tsx         # Tableau historique paginé
│       └── PaymentSuccessScreen.tsx        # Écran succès avec animation
├── hooks/
│   ├── usePayment.ts                       # Hook principal
│   └── useTransactionStatus.ts            # Polling du statut
├── lib/
│   └── payment/
│       ├── client.ts                       # Client API paiement
│       └── formatters.ts                  # Formatage montants XAF
└── types/
    └── payment.ts                          # Types TypeScript stricts
```

---

### 2.2 — Types TypeScript `types/payment.ts`

```typescript
export type PaymentGateway = 'flutterwave' | 'fedapay';
export type PaymentStatus  = 'pending' | 'success' | 'failed' | 'cancelled';
export type PaymentMethod  = 'mtn_cm' | 'orange_cm' | 'card' | 'ussd';
export type PaymentType    = 'rent' | 'purchase' | 'deposit' | 'subscription';

export interface Transaction {
  reference:      string;
  status:         PaymentStatus;
  type:           PaymentType;
  amount:         number;
  currency:       string;
  gateway:        PaymentGateway;
  payment_method: PaymentMethod | null;
  phone_number:   string | null;
  paid_at:        string | null;
  property?:      { id: number; title: string };
  created_at:     string;
}

export interface InitiatePaymentPayload {
  amount:         number;
  currency:       'XAF' | 'XOF';
  type:           PaymentType;
  payment_method: PaymentMethod;
  phone_number?:  string;  // requis si mobile money
  property_id?:   number;
  metadata?:      Record<string, unknown>;
}

export interface InitiatePaymentResponse {
  reference:      string;
  payment_link:   string;  // URL de redirection Flutterwave
  tx_ref:         string;
  gateway:        PaymentGateway;
  expires_at:     string;
}
```

---

### 2.3 — Hook `usePayment`

Le hook doit exposer :

```typescript
const {
  initiatePayment,   // (payload: InitiatePaymentPayload) => Promise<void>
  isLoading,         // boolean
  error,             // string | null
  transaction,       // Transaction | null
  resetPayment,      // () => void
} = usePayment();
```

**Comportement :**
- `initiatePayment` appelle l'API Laravel, puis redirige vers le `payment_link` Flutterwave
- Stocker le `reference` en `sessionStorage` pour le récupérer au callback
- Gérer les erreurs réseau, timeout, et erreurs métier (montant invalide, etc.)
- Ne jamais exposer les clés API côté client

---

### 2.4 — Hook `useTransactionStatus`

```typescript
// Polling toutes les 3 secondes pour vérifier le statut
// S'arrête automatiquement quand status = success | failed | cancelled
// Timeout global après 5 minutes avec message explicite
const { transaction, isPolling } = useTransactionStatus(reference: string);
```

---

### 2.5 — Composant `PaymentModal`

Interface complète avec les étapes suivantes :

**Étape 1 — Sélection du moyen de paiement**
- Carte MTN Mobile Money (fond jaune #FFCC00, logo MTN)
- Carte Orange Money (fond orange #FF6600, logo Orange)
- Carte bancaire Visa/Mastercard (fond bleu #1A1F71)
- Chaque carte : logo opérateur, nom, description courte, frais indicatifs
- Animation de sélection (border highlight + checkmark animé)

**Étape 2 — Saisie des informations**
- Si mobile money : champ numéro de téléphone avec format `+237 6XX XXX XXX`
  - Validation en temps réel du format camerounais (regex E.164)
  - Indicateur pays (drapeau CM 🇨🇲)
- Si carte : redirection directe vers Flutterwave (pas de saisie custom)
- Récapitulatif : propriété, montant en XAF formaté (`150 000 FCFA`), frais inclus

**Étape 3 — Confirmation + chargement**
- Bouton "Payer 150 000 FCFA" bien visible (couleur primaire, taille large)
- État loading avec spinner et message "Connexion à [MTN/Orange] en cours..."
- Désactivation complète du modal pendant traitement (no backdrop click)

**Étape 4 — Statut final**
- Succès : animation check vert (Lottie ou CSS), détails transaction, bouton "Voir le reçu"
- Échec : icône X rouge, message d'erreur clair et actionnable, bouton "Réessayer"
- Timeout : message "Le paiement prend du temps", lien support

**Règles UI obligatoires :**
- Mobile-first, 100% responsive
- ARIA labels complets sur tous les éléments interactifs
- Pas de fermeture accidentelle (no `onBackdropClick`) pendant paiement en cours
- Tailwind CSS uniquement, pas de CSS inline

---

### 2.6 — Page `payment/callback/page.tsx`

```typescript
// Cette page est appelée après redirection depuis Flutterwave
// Query params attendus: ?tx_ref=xxx&transaction_id=xxx&status=xxx

// Algorithme :
// 1. Lire les query params (tx_ref, status, transaction_id)
// 2. Récupérer la référence interne depuis sessionStorage
// 3. Appeler POST /api/v1/payments/{reference}/verify via Laravel
// 4. Afficher PaymentSuccessScreen ou écran d'erreur selon réponse
// 5. Nettoyer sessionStorage après traitement
// 6. Rediriger vers la page propriété après 5s si succès
// 7. Gérer le cas où sessionStorage est vide (onglet fermé et rouvert)
```

---

### 2.7 — Sécurité côté Next.js

- **Jamais de clé secrète Flutterwave côté client** — tout passe via les API Routes
- Les API Routes font proxy vers Laravel avec le JWT/Sanctum token de l'utilisateur
- Validation Zod sur tous les payloads avant envoi
- CSP header déjà en place dans `next.config.ts` — **ajouter** :
  ```typescript
  // Dans frame-src : ajouter https://checkout.flutterwave.com
  // Dans connect-src : ajouter https://api.flutterwave.com
  ```
- Utiliser la clé publique `FLW_PUBLIC_KEY` uniquement (prefixée `NEXT_PUBLIC_`)

---

## 🗂️ PARTIE 3 — MOBILE FLUTTER

### 3.1 — Dépendances `pubspec.yaml`

```yaml
dependencies:
  flutterwave_standard: ^1.0.4          # SDK officiel Flutter
  http: ^1.2.0
  provider: ^6.1.0
  url_launcher: ^6.2.0
  flutter_secure_storage: ^9.0.0        # Stockage sécurisé des tokens
```

---

### 3.2 — Structure Flutter

```
lib/
├── features/
│   └── payment/
│       ├── data/
│       │   ├── datasources/
│       │   │   └── payment_remote_datasource.dart
│       │   ├── models/
│       │   │   └── transaction_model.dart
│       │   └── repositories/
│       │       └── payment_repository_impl.dart
│       ├── domain/
│       │   ├── entities/
│       │   │   └── transaction.dart
│       │   ├── repositories/
│       │   │   └── payment_repository.dart
│       │   └── usecases/
│       │       ├── initiate_payment.dart
│       │       └── verify_payment.dart
│       └── presentation/
│           ├── pages/
│           │   ├── payment_page.dart
│           │   └── payment_history_page.dart
│           ├── widgets/
│           │   ├── payment_method_card.dart
│           │   ├── payment_status_chip.dart
│           │   └── transaction_tile.dart
│           └── providers/
│               └── payment_provider.dart
```

---

### 3.3 — Implémentation Flutter

Le `PaymentRemoteDatasource` doit :
1. Appeler `POST /api/v1/payments/initiate` sur Laravel avec le Bearer token
2. Recevoir le `payment_link` Flutterwave
3. Ouvrir le SDK Flutterwave natif avec `FlutterwaveStandard`
4. Écouter le callback de succès/échec
5. Appeler `POST /api/v1/payments/{reference}/verify` pour confirmer côté serveur

```dart
// Exemple d'initialisation SDK Flutterwave
FlutterwaveStandard flutterwave = FlutterwaveStandard(
  context: context,
  publicKey: Env.flwPublicKey,   // UNIQUEMENT la clé publique
  currency: Currency.XAF,
  amount: amount.toString(),
  customer: Customer(
    name: user.fullName,
    phoneNumber: user.phone,
    email: user.email,
  ),
  paymentOptions: "mobilemoneycameroon",
  customization: Customization(title: "KeyHome - Paiement"),
  txRef: txRef,
  isTestMode: !kReleaseMode,     // Auto test/prod selon le build
);

ChargeResponse response = await flutterwave.charge();
// ⚠️ NE PAS marquer comme payé ici — toujours vérifier via Laravel
if (response.status == "successful") {
  await paymentRepository.verifyTransaction(internalReference);
}
```

**⚠️ RÈGLE CRITIQUE :** Ne jamais marquer une transaction comme payée uniquement
sur la réponse Flutter. Toujours confirmer via Laravel qui re-vérifie auprès de Flutterwave.

---

## 🗂️ PARTIE 4 — DOCUMENTATION

### 4.1 — `PAYMENT_INTEGRATION.md` (à générer)

Générer un fichier Markdown complet avec ces sections :

```
1. Vue d'ensemble de l'architecture
2. Diagramme de flux de paiement (mermaid)
3. Configuration (env vars, dashboard Flutterwave)
4. API Reference complète (chaque endpoint)
5. Format des webhooks (Flutterwave + FedaPay)
6. Codes d'erreur et leur signification
7. Modes de test (données de test par opérateur)
8. Guide de migration FedaPay → Flutterwave (étape par étape)
9. Troubleshooting courant
10. Checklist mise en production
```

**Section migration FedaPay → Flutterwave doit contenir :**
- Conditions de déclenchement (zéro pending FedaPay + 100 txn Flutterwave valides)
- Les 5 étapes de retrait dans l'ordre exact
- Ce qu'il faut garder (historique BDD = données légales)
- Rollback procedure si problème

---

### 4.2 — `payment-api.yaml` OpenAPI 3.0 (à générer)

```yaml
openapi: 3.0.3
info:
  title: "KeyHome Payment API"
  version: "1.0.0"
  description: "API de paiement immobilier — Flutterwave + FedaPay"

servers:
  - url: https://api.keyhome.app/api/v1
    description: Production
  - url: https://api.keyhome.neocraft.dev/api/v1
    description: Staging

components:
  securitySchemes:
    BearerAuth: { type: http, scheme: bearer }
  schemas:
    Transaction:           # Schéma complet avec tous les champs
    InitiateRequest:       # Avec validations et exemples
    WebhookFlutterwave:    # Format payload webhook Flutterwave
    WebhookFedaPay:        # Format payload webhook FedaPay
    ErrorResponse:         # Format d'erreur standardisé

paths:
  /payments/initiate:         # POST
  /payments/{ref}:            # GET
  /payments/{ref}/verify:     # POST
  /payments/history:          # GET avec pagination
  /webhooks/flutterwave:      # POST
  /webhooks/fedapay:          # POST
```

Chaque endpoint doit documenter :
- Description et cas d'usage
- Tous les paramètres avec types et contraintes
- Exemples de request body complets
- Toutes les réponses : 200, 400, 401, 422, 429, 500
- Exemples de réponses success ET error

---

## 🗂️ PARTIE 5 — SÉCURITÉ

### 5.1 — Checklist sécurité à implémenter

**Validation des webhooks**
- [ ] Vérification de signature via `hash_equals()` — résistante aux timing attacks
- [ ] IP whitelist optionnelle (IPs Flutterwave officielles)
- [ ] Raw body préservé avant parsing JSON (ne pas utiliser `$request->all()`)
- [ ] Replay attack prevention : rejeter les webhooks de plus de 5 minutes

**Protection des endpoints**
- [ ] Rate limiting par IP ET par `user_id` sur `/initiate`
- [ ] Validation des montants (min: 100 XAF, max: configurable)
- [ ] Vérification que le `property_id` appartient à l'utilisateur authentifié
- [ ] Logs de sécurité sur toutes les tentatives d'accès non autorisé

**Validation des données**
- [ ] Montant re-vérifié côté backend (jamais faire confiance au client)
- [ ] Idempotence : transaction déjà `success` → retourner directement sans re-appel API
- [ ] Phone number : validation format E.164 avec `libphonenumber-for-php`
- [ ] Sanitisation de tous les inputs (pas d'injection dans les `metadata`)

**Stockage sécurisé**
- [ ] Clés API jamais dans les logs applicatifs
- [ ] `gateway_response` stockée telle quelle (Flutterwave gère la conformité PCI)
- [ ] Audit trail : chaque changement de statut loggé avec timestamp + source (webhook/API/manual)

**Headers HTTP**
- [ ] HTTPS obligatoire sur les endpoints webhook
- [ ] Webhook endpoint : rejeter les requêtes non `POST` et non `application/json`
- [ ] `Strict-Transport-Security` déjà en place côté Next.js

---

### 5.2 — Alertes Sentry à configurer

```php
// Alertes à configurer dans Sentry (déjà intégré au projet) :
// - Webhook avec signature invalide (possible attaque en cours)
// - Plus de 10 transactions failed en 1h pour un même user_id
// - Montant > 5 000 000 XAF (seuil configurable)
// - Timeout de vérification répétés (> 3 fois en 10 minutes)
// - Gateway indisponible (HTTP 5xx de Flutterwave)
```

---

## 🗂️ PARTIE 6 — MIGRATION FEDAPAY → FLUTTERWAVE

### Phase 1 — Coexistence (maintenant)

```
# .env production
PAYMENT_GATEWAY=flutterwave   # Flutterwave pour TOUS les nouveaux paiements
```

- Les transactions FedaPay existantes restent consultables via l'historique
- Le webhook FedaPay reste actif pour les paiements en attente
- Les deux providers instanciés, seul Flutterwave initie de nouvelles transactions

### Phase 2 — Retrait FedaPay (après validation)

**Conditions obligatoires avant retrait :**
1. Zéro transaction FedaPay avec `status=pending` depuis 48h
2. Flutterwave validé sur au moins 100 transactions réelles en production
3. Webhooks Flutterwave fonctionnels et monitorés

**Commandes de retrait dans l'ordre :**
```bash
# 1. Vérifier les pending FedaPay
php artisan tinker
Transaction::where('gateway','fedapay')->where('status','pending')->count();
# DOIT retourner 0 avant de continuer

# 2. Désactiver le webhook FedaPay dans leur dashboard

# 3. Supprimer du codebase
#    - FedaPayPaymentService.php
#    - Case 'fedapay' dans PaymentService
#    - Route POST /webhooks/fedapay
#    - Variables FEDAPAY_* du .env

# 4. NE PAS supprimer les transactions FedaPay en base
#    (données légales / comptables à conserver)
```

---

## ✅ CHECKLIST MISE EN PRODUCTION

**Backend Laravel**
- [ ] Tous les tests passent (`php artisan test`)
- [ ] Variables d'env production configurées (vraies clés Flutterwave live)
- [ ] URL Webhook enregistrée dans le dashboard Flutterwave
- [ ] Monitoring Sentry actif sur les erreurs de paiement
- [ ] Backup BDD effectué avant déploiement

**Frontend Next.js**
- [ ] CSP mis à jour avec les domaines Flutterwave (`checkout.flutterwave.com`)
- [ ] Aucune clé secrète dans le bundle client (vérifier avec `next build && grep -r "FLWSECK" .next`)
- [ ] Page callback testée : succès, échec, annulation, onglet fermé
- [ ] Timeout handling testé (utilisateur ferme le tab pendant le paiement)

**Mobile Flutter**
- [ ] `isTestMode: !kReleaseMode` en place (auto)
- [ ] Clé publique uniquement dans l'app (`FLW_PUBLIC_KEY`)
- [ ] Vérification serveur obligatoire avant tout affichage de confirmation

**Dashboard Flutterwave**
- [ ] Webhook URL : `https://api.keyhome.app/api/v1/webhooks/flutterwave`
- [ ] Événements activés : `charge.completed`
- [ ] Webhook secret généré et copié dans `.env` production
- [ ] Test webhook envoyé depuis le dashboard et reçu avec succès

---

## 🧪 DONNÉES DE TEST FLUTTERWAVE (Cameroun)

```
MTN Mobile Money Cameroun — Succès :
  Numéro : 0677777777  |  OTP : 12345

Orange Money Cameroun — Succès :
  Numéro : 0699999999  |  OTP : 12345

Carte test — Succès :
  Numéro : 5531 8866 5214 2950
  CVV    : 564  |  Expiry : 09/32  |  PIN : 3310  |  OTP : 12345

Carte test — Échec :
  Numéro : 5258 5859 2266 6506
  CVV    : 883  |  Expiry : 09/31
```

---

*Prompt généré pour le projet KeyHome*
*Stack : Laravel · Next.js · Flutter*
*Agrégateurs : Flutterwave (primaire) + FedaPay (transitoire)*