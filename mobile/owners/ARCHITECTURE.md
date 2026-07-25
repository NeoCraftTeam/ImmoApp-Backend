# KeyHome Owner — Architecture & Design

> Application mobile **bailleur** (React Native / Expo / Tamagui) — destinée
> aux propriétaires et agences immobilières. Branding : **KeyHome Owner**
> (et non "Pro").
>
> **Version courante** : v0.3 — production-readiness ~90/100.

## Sommaire

1. [Stack & versions](#1-stack--versions)
2. [Arborescence](#2-arborescence)
3. [Provider stack](#3-provider-stack)
4. [Couche données (API → hooks → cache)](#4-couche-données)
5. [Session & persistance](#5-session--persistance)
6. [Mode offline & synchronisation](#6-mode-offline--synchronisation)
7. [Chat temps réel](#7-chat-temps-réel)
8. [Splash & boot](#8-splash--boot)
9. [Push notifications](#9-push-notifications)
10. [Paiements (priorité #1)](#10-paiements-priorité-1)
11. [Création / édition d'annonces](#11-création--édition-dannonces)
12. [Gestion des baux & locataires](#12-gestion-des-baux--locataires)
13. [Conformité SOLID](#13-conformité-solid)
14. [Historique versions](#historique-des-versions)

---

## 1. Stack & versions

| Couche | Choix | Notes |
|---|---|---|
| Runtime | Expo SDK 54 / React Native 0.81.5 / React 19.1 | New Architecture activée |
| Routing | expo-router 6 | typed routes, deep-link scheme `keyhomeowners://` |
| Design | Tamagui 1.122 | thème teal `#0D9488` (vs visiteur crimson) |
| Cache HTTP | TanStack Query 5 + PersistQueryClient + AsyncStorage | 5 min stale, 24 h gc |
| Auth | expo-secure-store + Sanctum bearer | header `Authorization: Bearer …` injecté par interceptor Axios |
| Realtime | pusher-js (Reverb compat) — **lazy-required** | fallback polling 4 s sans la dep |
| Paiement | Kpay (mobile money) + Stripe (card) via `expo-web-browser` | hosted checkout + deep-link return |
| Push | expo-notifications (lazy-import, no-op Expo Go) | FCM token sync via `POST /fcm/token` |
| Monitoring | Sentry lazy-init (no-op Expo Go ou sans DSN) | `services/monitoring.ts` |
| i18n | `i18n-js` | FR par défaut, fallback EN |
| Tests | Jest 29 + ts-jest (pure Node, pas jest-expo) | 64 tests / 11 suites |

## 2. Arborescence

```
mobile/owners/
├── app/                              # routes expo-router
│   ├── _layout.tsx                   # provider stack + AuthGate + SplashGate
│   ├── (auth)/                       # login, register, forgot, reset, verify-otp
│   ├── (tabs)/                       # bottom tabs : dashboard, ads, viewings, account
│   ├── ads/                          # CRUD annonces + placarde + edit
│   ├── messages/                     # chat liste + thread
│   ├── availability/                 # créneaux visites (index + détail par annonce)
│   ├── lease-contracts.tsx           # baux : list + generate/renew/terminate
│   ├── credits.tsx                   # boutique crédits + recharge
│   ├── subscriptions.tsx             # plans + souscription via PaymentSheet
│   ├── pro-services.tsx              # services premium (mention "Pro" enlevée)
│   ├── payments.tsx                  # historique paiements
│   ├── remboursements.tsx            # refunds
│   ├── financials.tsx                # KPIs revenus
│   ├── payment-success.tsx           # landing post-checkout (polling 3s/timeout 60s)
│   ├── payment-return.tsx            # alias legacy → success
│   ├── business-card.tsx             # carte de visite PDF + QR
│   ├── trust-score.tsx, equipe.tsx, security.tsx, notifications.tsx…
│   └── …
├── src/
│   ├── api/                          # client.ts + endpoints.ts + extract-error.ts
│   ├── auth/                         # SessionProvider + useStorageState
│   ├── components/
│   │   ├── ads/                      # AdForm, BoostSheet, ImagePickerGrid, MapPicker
│   │   ├── payments/                 # PaymentSheet, PaymentMethodPicker
│   │   ├── ErrorBoundary, FadeIn, EmptyState, ScreenHeader, StatCard, StatusBadge
│   │   └── SplashView, OfflineBanner
│   ├── hooks/                        # ~30 hooks data + UI
│   ├── providers/                    # QueryProvider (PersistQueryClient)
│   ├── services/                     # checkout, echo, monitoring
│   ├── theme/tokens.ts               # brand teal + AD_STATUS_META
│   ├── types/                        # ad, owner, payment, credits, conversation…
│   └── utils/                        # format (FCFA + dates)
├── __tests__/                        # 64 tests / 11 suites (Jest ts-jest)
├── app.json                          # KeyHome Owner + scheme keyhomeowners
├── eas.json, jest.config.js, tsconfig.json, .env.example
└── ARCHITECTURE.md                   # ce fichier
```

## 3. Provider stack

```
ErrorBoundary (réinitialisable, debug panel en DEV)
  └─ GestureHandlerRootView
      └─ SafeAreaProvider
          └─ TamaguiProvider (theme teal)
              └─ PortalProvider (shouldAddRootHost — pour Sheets)
                  └─ Theme (light/dark auto)
                      └─ QueryProvider (PersistQueryClient + AsyncStorage)
                          └─ SessionProvider
                              ├─ AuthGate (redirige login si pas auth)
                              ├─ usePushNotifications() (au sign-in)
                              ├─ OfflineBanner (NetInfo)
                              ├─ Slot (router outlet)
                              └─ SplashGate (SplashView jusqu'à hydration)
```

L'ordre est important : Theme **au-dessus** de PortalProvider sinon les Sheets
qui se téléportent perdent le theme contextuel.

## 4. Couche données

### 4.1 Axios singleton (`src/api/client.ts`)

- Base URL résolue depuis `EXPO_PUBLIC_API_BASE_URL` (env) puis fallback
  `apiBaseUrlDev` / `apiBaseUrl` dans `app.json`.
- Request interceptor : injecte `Authorization: Bearer {token}` depuis SecureStore.
- Response interceptor : 401 → `SecureStore.deleteItemAsync(SESSION_KEY)` → le
  SessionProvider détecte le changement et redirige vers login.
- `extractApiErrorMessage` extrait dans son propre module
  (`api/extract-error.ts`) sans deps natives pour être testé avec ts-jest pur.

### 4.2 Endpoints (`src/api/endpoints.ts`)

Sections principales :
- `auth.*`, `users.update`
- `ref.*` (cities, quarters, ad-types, property-attributes)
- `ads.*`, `my.*` (ads, stats, leases, tenants, expenses, viewings, QR, placarde)
- `availability.*`, `reservations.*`
- `payments.*` (initiate, verify, methods, history, receipt, refund-request,
  stripe.*)
- `credits.*` (balance, packages, purchase, verify-purchase)
- `subscriptions.*` (plans, current, subscribe, cancel, renew, upgrade,
  downgrade, auto-renew, history)
- `reviews.*`, `notifications.*`, `chat.*` (conversations, messages, typing,
  reactions)
- `refunds.*`, `proServices.*`, `trust.score`, `market.estimate`, `team.*`
- `echo.*` (broadcasting auth + channel naming)

### 4.3 Hooks data

Pattern uniforme TanStack Query + `select` normalisant les shapes
(`Array.isArray(data?.foo) ? data.foo : []`) — résilient aux payloads
incomplets. Liste non-exhaustive : `useMe`, `useMyAds` (cursor-paginé),
`useAd`, `useOwnerStats`, `useAnalytics`, `useViewings`, `useTenants`,
`useLeases`, `useGenerate/Renew/TerminateLease`, `useCreditsBalance`,
`useCreditPackages`, `usePurchaseCredits`, `useVerifyCreditPurchase`,
`useInitiatePayment`, `useVerifyPayment`, `usePublicPaymentStatus`,
`usePaymentMethods`, `useStripeMethods`, `useSubscribe`,
`useSubscriptionPlans`, `useCurrentSubscription`, `useConversations`,
`useConversation`, `useSendMessage`, `useMarkConversationRead`,
`useSetTyping`, `useConversationRealtime`, `useNotifications`,
`useUnreadNotificationCount`, `useAdAvailability`, `useTrustScore`,
`useTeam`, `useExpenses`, `useProfitLoss`, `useEnhanceTitle`,
`useEnhanceDescription`.

## 5. Session & persistance

- Token Sanctum stocké via expo-secure-store sous `SESSION_KEY`.
- Re-hydraté au boot ; `SessionProvider` expose `isLoading`, `isAuthenticated`,
  `signIn`, `signOut`, `setToken`.
- À la connexion : `setUserContext(user)` → tag Sentry. Au sign-out :
  `clearUserContext()` + `trackEvent('auth.signOut')`.

## 6. Mode offline & synchronisation

- **PersistQueryClient** (`@tanstack/react-query-persist-client`) avec
  `AsyncStorage` (1 s debounce) + `CACHE_BUSTER = 'kh-owners-v1'` pour
  invalidations groupées.
- 24 h gc, 5 min stale, retry 2.
- **NetInfo** branché à `onlineManager` → suspend les fetches offline,
  refetch automatique au reconnect.
- **AppState** branché à `focusManager` → refetch au retour foreground.
- Dehydrate exclusions (jamais persistés) :
  - `me`, `owner-stats`, `notifications-unread-count`
  - **`payment-status`**, **`credits-balance`**, **`subscription-current`**
    → garantit qu'on ne montre jamais un statut paiement obsolète.

## 7. Chat temps réel

- **Polling 4 s** par défaut sur `useConversation(id)` — fonctionne sans
  config Reverb.
- **Reverb** opt-in via env vars : `EXPO_PUBLIC_REVERB_APP_KEY`,
  `_HOST`, `_PORT`, `_SCHEME`. Le client `pusher-js/react-native` est
  **lazy-required** (`require()` dans un try/catch) — sans la dep
  installée, le service `echo.ts` retourne `null` proprement.
- `useConversationRealtime(id, onTyping)` subscribe à 4 events :
  `message.sent`, `messages.read`, `message.deleted`, `user.typing`.
  Le cache TanStack est patché in-place.
- UI : bulles teal (own) vs grise, check / double-check pour read receipts,
  3-dots animation (RN `Animated`, native driver) pour typing.
- Header thread affiche avatar + nom prélu depuis le cache des
  conversations (zéro flash).

## 8. Splash & boot

- `SplashView` (rendu absolute en overlay) — fade out 380 ms au moment
  où `SessionProvider` finit l'hydration.
- Animation : stagger 70 ms par lettre, halo blanc, dot pulsant en loop,
  tagline slide-up. **Durée totale ~1.4 s** — un peu plus long que le
  visiteur (~1.0 s) comme demandé par le brief.
- Aucun "Pro" dans le wordmark — c'est **KeyHome Owner**.

## 9. Push notifications

`usePushNotifications()` câblé dans `AuthGate` :

- No-op en Expo Go (`executionEnvironment === StoreClient`) et sur
  simulateur (`Constants.isDevice === false`) — pas de warnings.
- Lazy-import `expo-notifications` à l'exécution.
- Demande permission → récupère Expo push token → POST `/fcm/token`
  avec `{token, platform, provider: 'expo'}`.
- Channel Android `default` avec couleur teal `#0D9488`.
- Erreurs silencieuses — push est best-effort.

## 10. Paiements (priorité #1)

### 10.1 Architecture multi-gateway

Le backend supporte **Kpay** (mobile money) et **Stripe** (cartes). Le
client mobile reste agnostique : il appelle un endpoint **unifié** qui
renvoie une `payment_link` à ouvrir.

### 10.2 Flow standard

```
[User tap "Souscrire"]
        ↓
[PaymentSheet opens]
        ↓
  Charge /payments/methods
        ↓
  User pick méthode (mobile money / card / saved)
  + saisit phone si requis
        ↓
  POST /payments/initiate_payment {amount, type, payment_method, phone…}
        ↓
  Backend → renvoie {tx_ref, payment_link, gateway}
        ↓
  openHostedCheckout(payment_link, tx_ref)
   = WebBrowser.openAuthSessionAsync(link, "keyhomeowners://payment-success?tx_ref=…")
        ↓
  User paie sur la page hosted
        ↓
  Gateway redirect → deep-link keyhomeowners://payment-success?tx_ref=…
        ↓
  WebBrowser session close → on récupère tx_ref via Linking.parse
        ↓
  router.push('/payment-success?tx_ref=…')
        ↓
  usePublicPaymentStatus(txRef) → poll /payments/{txRef}/public-status
       (3 s tant que pending, stop terminal, timeout 60 s avec retry)
        ↓
  Sur success → invalidate balance/history/subscription-current
                + verify-purchase opportuniste (raccourcit la latence webhook)
```

### 10.3 Composants clés

- **`src/services/checkout.ts`** : `openHostedCheckout(link, txRef)` +
  `extractTxRef(url)` + `buildReturnUrl(txRef)` (scheme `keyhomeowners`).
- **`src/components/payments/PaymentSheet.tsx`** : sheet unifié pour
  subscription / pro_service / credit / boost / unlock.
- **`src/components/payments/PaymentMethodPicker.tsx`** : radio-cards
  avec icônes par channel (mobile_money, card, wallet).
- **`src/hooks/usePayments.ts`** : `usePayments`, `usePaymentMethods`,
  `useInitiatePayment`, `useVerifyPayment`, `useCancelPayment`,
  `usePublicPaymentStatus`, `useRequestPaymentRefund`, `useStripeMethods`,
  `useDeleteStripeMethod`, `useSetDefaultStripeMethod`.
- **`src/hooks/useCredits.ts`** : `useCreditsBalance`, `useCreditPackages`,
  `usePurchaseCredits`, `useVerifyCreditPurchase`. `extractBalance` est
  testé en pur unitaire (3 shapes possibles point_balance / balance /
  data.{balance|point_balance}).
- **`app/payment-success.tsx`** : landing avec polling + timeout 60 s +
  retry + appel opportuniste `verify-purchase`.
- **`app/payment-return.tsx`** : alias legacy `keyhomeowners://payment/return`
  → forward vers `/payment-success` avec query params préservés.
- **`app/credits.tsx`** : boutique avec hero balance + packages + bouton
  "Acheter" qui short-circuit le sheet pour les méthodes sans phone.
- Wiring dans **subscriptions** (PaymentSheet `type: 'subscription'`),
  **pro-services** (si `price > 0` → PaymentSheet, sinon confirmation
  crédits), **BoostSheet** (→ /credits si solde insuffisant).

### 10.4 Sécurité

- Aucune carte / identifiant ne transite par l'app — tout reste sur la
  page hosted-checkout du gateway.
- Le deep-link `keyhomeowners://` est **séparé** du scheme visiteur
  `keyhome://` pour éviter les conflits si les deux apps sont installées.
- `Authorization: Bearer …` n'est jamais loggé (Sentry breadcrumbs sont
  filtrés à `payment.checkout.{open,success,cancelled}`).

## 11. Création / édition d'annonces

- `app/ads/new.tsx` / `app/ads/[id]/edit.tsx` rendent `AdForm`.
- 5 sections : Informations, Localisation, Caractéristiques,
  Charges & conditions, Photos.
- **Autosave 1.5 s debounce** sur les drafts (mode edit) via
  `useAutosaveAd`.
- **IA enhancement** (parité web) : 2 boutons dorés (sparkle icon) à
  côté des labels Title + Description. Appellent `useEnhanceTitle` /
  `useEnhanceDescription`. Loading spinner intégré.
- `useCreateAd` / `useUpdateAd` (multipart FormData + `_method=PUT`) →
  `usePublishAd` final.
- **Placarde PDF** : `app/ads/[id]/placarde.tsx` télécharge la pancarte
  A5 + QR code via `useAdQr` (data-URI directement renderable).

## 12. Gestion des baux & locataires

- `app/lease-contracts.tsx` :
  - Liste des baux avec status badge (draft/active/expired/terminated/archived).
  - Action menu (3-dots) → renouveler / résilier.
  - FAB "Générer" → `GenerateLeaseSheet` (pick annonce + locataire +
    dates + loyer + caution).
  - `useGenerateLease`, `useRenewLease`, `useTerminateLease`.
- `app/tenants.tsx` : list + add/delete locataires.
- `app/business-card.tsx` : carte de visite digitale avec QR profil
  + bouton "Télécharger PDF" (expo-file-system/legacy) + "Partager"
  (expo-sharing).

## 13. Conformité SOLID

- **Single Responsibility** : API, hooks, composants, services
  séparés. `extractApiErrorMessage` extrait dans son propre module pur
  testable. `extractBalance` testable en unit (3 shapes backend).
- **Open/Closed** : `PaymentSheet` accepte `purpose: PaymentPurpose`
  + `extraPayload` → extensible à n'importe quel type sans modifier le
  composant.
- **Liskov** : `subscribePrivate` retourne toujours une fonction
  `cleanup` qu'on appelle dans useEffect cleanup — même contrat avec ou
  sans Reverb installé (no-op si pas dispo).
- **Interface Segregation** : Chaque hook expose un seul endpoint.
  `useInitiatePayment` ne sait rien du polling ; `usePublicPaymentStatus`
  ne sait rien du gateway.
- **Dependency Inversion** : `services/checkout.ts` dépend de
  `WebBrowser` + `Linking` (interfaces stables) ; les composants UI
  dépendent de `services/checkout` sans connaître les internes.

---

## Historique des versions

- **v0.3** (2026-06-22) : **Payments full integration** (PaymentSheet
  unifié + Kpay/Stripe via WebBrowser auth-session + tx_ref deep-link
  + polling 60s/3s + verify-purchase opportuniste) · **Splash teal**
  réanimé sans "Pro" + tagline slide-up + durée 1.4 s · **AI enhance**
  title + description dans AdForm · **Lease management** (generate from
  ad / renew / terminate) avec sheets dédiés · **Business card** screen
  (QR + PDF + share) · **Push notifications** wiring (FCM token, channel
  Android teal) · **Dashboard polish** (hero crédits, bell unread, 4
  quick actions, FadeIn) · **Messages teal** (read receipts double-check,
  typing dots animés, header avec avatar prélu) · **Offline polish**
  (paiement / balance / subscription jamais persistés) · **Sentry DSN**
  documenté dans `.env.example` · **app.json** rebranding "KeyHome
  Owner" + scheme `keyhomeowners` + expo-updates + Sentry plugin · Tests
  64 passants / 11 suites.
- **v0.2** (2026-06-22) : 10 nouvelles pages owner (messages, financials,
  payments, remboursements, prix-marche, pro-services, trust-score,
  equipe, availability, security) + 15 hooks + ErrorBoundary + tests
  (41 passants).
- **v0.1** (2026-06-21) : Scaffold initial — auth, tabs, ads CRUD,
  viewings, account, profile, subscriptions, tenants, leases (read-only),
  reviews stub.

---

_Maintenu à chaque feature majeure. Dernière mise à jour : 2026-06-22._
