# KeyHome Mobile — Architecture & Design

> Application visiteur React Native (Expo SDK 54). Réplique mobile de la
> partie visiteur du frontend Next.js `keyhome-frontend-next`, avec des
> capacités natives en plus (offline, push, géolocalisation, captures).
>
> **Version courante** : v0.9 — production-readiness 96/100.

## Sommaire

1. [Stack & versions](#1-stack--versions)
2. [Arborescence](#2-arborescence)
3. [Provider stack](#3-provider-stack)
4. [Couche données](#4-couche-données-api--hooks--cache)
5. [Session & persistance](#5-session--persistance)
6. [Mode offline & synchronisation](#6-mode-offline--synchronisation)
7. [Chat Messenger-style](#7-chat-messenger-style)
8. [Splash & boot](#8-splash--boot)
9. [Push notifications](#9-push-notifications)
10. [Design system Tamagui](#10-design-system-tamagui)
11. [Animations](#11-animations)
12. [Conformité SOLID](#12-conformité-solid)
13. [Couverture web → mobile](#13-couverture-web--mobile)
14. [Roadmap & dette technique](#14-roadmap--dette-technique)

---

## 1. Stack & versions

| Catégorie       | Technologie                        | Version       |
| --------------- | ---------------------------------- | ------------- |
| Runtime         | Expo SDK                           | 54            |
| Framework       | React Native                       | 0.81.5        |
| UI              | React                              | 19.1          |
| Routing         | expo-router                        | 6             |
| Design system   | Tamagui                            | 1.122         |
| State serveur   | TanStack Query                     | 5             |
| Persistance Q   | @tanstack/query-async-storage-persister | 5         |
| Animations      | RN Animated (natif, useNativeDriver) | —           |
| Lottie          | lottie-react-native                | 7.3           |
| Maps            | react-native-maps                  | 1.20          |
| Géolocalisation | expo-location                      | 19            |
| Images          | expo-image                         | 3             |
| Image picker    | expo-image-picker                  | latest        |
| Auth secure     | expo-secure-store                  | 15            |
| Storage         | @react-native-async-storage/async-storage | 2.2     |
| Network state   | @react-native-community/netinfo    | 11            |
| Notifications   | expo-notifications (lazy, dev-build) | latest      |
| HTTP            | Axios                              | 1.7           |
| i18n            | i18n-js                            | 4             |
| Formulaires     | react-hook-form + zod              | 7 / 3         |
| TypeScript      | strict, `noUncheckedIndexedAccess` | 5.9           |

---

## 2. Arborescence

```
mobile/visitors/
├── ARCHITECTURE.md              ← ce fichier
├── app.json                     ← config Expo (bundle id, plugins, perms)
├── babel.config.js              ← preset-expo + Tamagui plugin
├── tamagui.config.ts            ← tokens design system (brand, themes)
├── app/                         ← routes Expo Router (file-system based)
│   ├── _layout.tsx              ← provider stack racine + Splash + OfflineBanner
│   ├── index.tsx                ← gate onboarding → home/auth
│   ├── (auth)/
│   │   ├── _layout.tsx          ← stack auth
│   │   ├── login.tsx            ← email + password + animation succès
│   │   ├── register.tsx         ← inscription Zod
│   │   ├── forgot-password.tsx  ← reset link
│   │   ├── reset-password.tsx   ← token + nouveau MDP
│   │   └── verify-otp.tsx       ← 6 cases OTP + paste-friendly + cooldown
│   ├── (tabs)/
│   │   ├── _layout.tsx          ← bottom tabs + safe area + offline-aware
│   │   ├── home.tsx             ← feed + greeting + chips types + reco carousel
│   │   ├── search.tsx           ← recherche + filter sheet
│   │   ├── favorites.tsx        ← favoris auth-gated
│   │   └── account.tsx          ← hub + activité + portefeuille + outils
│   ├── ads/[slug].tsx           ← détail annonce (parallax hero, map, scorecard…)
│   ├── agences/[id].tsx
│   ├── bailleurs/[username].tsx
│   ├── proprietaires/[id].tsx   ← alias → bailleurs
│   ├── blog/                    ← index + [slug] (contenu statique)
│   ├── comparaison/             ← index + [slug] (analyses comparatives)
│   ├── messages/                ← index + [id] (Messenger-style)
│   ├── disputes/                ← index + [id] (stepper + chat + preuves)
│   ├── surveys/                 ← index + [slug] (questions dyn.)
│   ├── sondage/[id].tsx         ← alias → surveys
│   ├── compare.tsx              ← comparaison locale 2-4 annonces
│   ├── estimator.tsx            ← estimateur loyer
│   ├── credits.tsx              ← solde + historique paiements
│   ├── refunds.tsx              ← demandes de remboursement
│   ├── reservations.tsx         ← visites réservées
│   ├── notifications.tsx        ← centre de notif + bulk actions
│   ├── search-alerts.tsx        ← alertes CRUD + modal editor
│   ├── nearby.tsx               ← annonces près de moi (map + radius)
│   ├── market-prices.tsx        ← prix médian par ville/quartier
│   ├── payment-success.tsx      ← polling status post-paiement
│   ├── payment-return.tsx       ← alias paiement
│   ├── profile.tsx              ← édition profil + avatar
│   ├── parametres.tsx           ← settings + sign-out + delete account
│   ├── contact.tsx              ← formulaire support
│   ├── aide.tsx                 ← FAQ + email support
│   ├── onboarding.tsx           ← carousel intro
│   ├── conditions.tsx           ← CGU
│   ├── confidentialite.tsx      ← Privacy
│   └── offline.tsx              ← fallback hors-ligne
└── src/
    ├── api/
    │   ├── client.ts            ← Axios singleton + Bearer + 401 → signOut
    │   └── endpoints.ts         ← table unique des URLs backend
    ├── auth/
    │   ├── SessionProvider.tsx  ← token SecureStore + signIn/Up/Out/setToken
    │   └── useStorageState.ts   ← hook persisté générique
    ├── components/
    │   ├── AdCard.tsx           ← carte feed (Airbnb-flat)
    │   ├── AdCardSkeleton.tsx   ← shimmer placeholder
    │   ├── OfflineBanner.tsx    ← bandeau sticky NetInfo
    │   ├── SplashView.tsx       ← splash Lottie / wordmark fallback
    │   ├── CompareButton.tsx
    │   ├── CompareBar.tsx
    │   ├── FavoriteButton.tsx
    │   ├── SearchFilterSheet.tsx
    │   └── ads/                 ← sous-composants détail annonce
    │       ├── BookViewingSheet.tsx
    │       ├── KeyScoreSection.tsx
    │       ├── LocationMap.tsx
    │       ├── NeighborhoodScorecard.tsx
    │       ├── PropertyAttributes.tsx
    │       ├── ReviewForm.tsx
    │       ├── ReviewsSection.tsx
    │       └── SimilarAdsCarousel.tsx
    ├── hooks/                   ← 35 hooks data (voir §4)
    ├── providers/
    │   ├── CompareProvider.tsx  ← état comparateur (max 4, persisted)
    │   └── QueryProvider.tsx    ← TanStack Query + PersistQueryClient
    ├── theme/
    │   └── tokens.ts            ← brand colors + spacing + radius
    ├── types/                   ← Ad, Review, User, Conversation, etc.
    └── utils/
        └── geo.ts               ← haversine + formatDistance + walkingMinutes
```

---

## 3. Provider stack

```
GestureHandlerRootView          (gestures pour Sheet, Pan, etc.)
 └─ SafeAreaProvider             (insets notch / home indicator)
   └─ TamaguiProvider            (tokens + style extraction)
     └─ Theme (light|dark)       (suivi system color scheme)
       └─ QueryProvider          (TanStack + persistance + NetInfo)
         └─ SessionProvider      (token SecureStore + signIn/Out)
           └─ CompareProvider    (état comparateur persisted)
             ├─ PushBridge       (register expo-push, dev-build seul)
             ├─ OfflineBanner    (sticky banner NetInfo)
             ├─ <Slot />         (route active)
             └─ SplashGate       (overlay jusqu'à fin de l'hydration)
```

**Pourquoi cet ordre** : chaque provider extérieur fournit un service au
provider intérieur. `QueryProvider` reste au-dessus de `SessionProvider`
pour que l'invalidate-on-logout marche. `SplashGate` est rendu en
dernier-enfant pour qu'il soit au-dessus de l'écran routé pendant
l'hydration.

---

## 4. Couche données (API → hooks → cache)

### 4.1 Axios singleton (`src/api/client.ts`)

- Lit le token depuis SecureStore au début de chaque requête (interceptor
  asynchrone) ; cache en mémoire après le 1er hit.
- Sur 401, supprime le token → SessionProvider observe la transition,
  redirige l'UI vers `/login`. **Aucun routing depuis l'interceptor.**
- `extractApiErrorMessage(err)` extrait le `message` du `{message, errors}`
  Laravel, tombe sur des messages spécifiques par status (401, 422,
  ECONNABORTED) puis sur `err.message` si l'erreur est non-axios, et
  enfin sur un fallback générique.

### 4.2 Endpoints (`src/api/endpoints.ts`)

Table unique des URLs. Toute route renommée côté backend se met à jour
ici, jamais dans 12 fichiers dispersés.

Catégories :
- `auth.*` (login, register, forgot-password, reset-password, verify-email-otp, track-home-visit)
- `users.*` (update, publicProfile)
- `bailleurs.follow`
- `agencies.detail`
- `ads.*` (feed, list, nearby, recommendations, types, detail, similar, reviews, keyscore, scorecard)
- `my.*` (favorites, unlockedAds, deleteAccount, reservations)
- `viewings.*` (slots, reserve, cancel)
- `conversations.*` (list, detail, messages, attachments, typing, read, archive, unreadCount, create)
- `messages.*` (delete, reactions)
- `notifications.*` (list, unreadCount, markRead, markAllRead, delete, fcmToken)
- `searchAlerts.*`
- `payments.*` (history, methods, publicStatus, refunds, refundRequest)
- `credits.balance`
- `disputes.*` (list, detail, create, evidence, messages)
- `surveys.*` (publicList, publicShow, publicSubmit, hasAnswered, submit)
- `support.contact`
- `priceIndex`
- `propertyAttributes`
- `geo.directions`

### 4.3 Hooks data (`src/hooks/`)

Chaque hook = 1 endpoint, 1 cache key, 1 select. Pattern uniforme :
```ts
export function useXxx(id?: string) {
  return useQuery<RawResponse, Error, ProcessedShape>({
    queryKey: ['xxx', id],
    queryFn: async () => (await apiClient.get(...)).data,
    select: (payload) => Array.isArray(payload?.data) ? payload.data : [],
    enabled: Boolean(id),
    staleTime: ...
  });
}
```

35 hooks couvrent toutes les surfaces métier (feed, search, ads,
reviews, messages, notifications, alerts, payments, refunds, disputes,
surveys, bailleurs, agencies, etc.).

**Mutations** suivent le même schéma avec `onMutate` optimistic +
`onError` rollback + `onSuccess` invalidate cache.

### 4.4 Résilience aux shapes inattendues

Tous les hooks de liste utilisent `Array.isArray(payload?.data) ? ... : []`.
Tous les hooks single-record acceptent la forme `{data: T}` OU `T` direct.
Le `normalizeCategories` du `NeighborhoodScorecard` accepte array ET dict.

→ Une API qui change de shape ne crashe pas l'UI ; au pire elle affiche
un état vide / chargement, jamais un red-screen.

---

## 5. Session & persistance

### 5.1 Stockage

| Donnée                  | Storage         | TTL         | Clé                          |
| ----------------------- | --------------- | ----------- | ---------------------------- |
| Bearer token            | SecureStore     | Permanent   | `keyhome.session.token`      |
| Comparator state        | SecureStore     | Permanent   | `keyhome.compare.ids`        |
| Onboarding done flag    | SecureStore     | Permanent   | `keyhome.onboarding.done`    |
| TanStack Query cache    | AsyncStorage    | 24 h        | `kh-query-cache` (versionné) |
| Push registration flag  | Module memory   | Session app | —                            |
| Home visit tracked flag | Module memory   | Session app | —                            |

### 5.2 Cycle de vie de la session

1. **Cold start** → SessionProvider lit `SESSION_KEY` (async).
   - `isLoading=true` pendant la lecture (~50-200 ms iOS Keychain).
   - SplashGate overlay reste visible.
2. **Lecture résolue** → token en mémoire + en context. `isLoading=false`.
   - SplashGate fade-out 340 ms.
   - Si token présent → `useMe()` se déclenche, populate `/me` cache.
3. **Requête API** → interceptor lit le token, injecte `Authorization: Bearer`.
4. **401 reçu** → interceptor supprime token, retourne l'erreur au caller.
   - SessionProvider observe `token=null` via storage callback.
   - L'UI re-render avec `isAuthenticated=false` → écrans gated affichent
     le bouton "Se connecter".
5. **Sign-out manuel** → `setToken(null)` → propage immédiatement.
6. **Sign-in** → `signIn(email, password)` → POST → écrit le token →
   propage. `useMe` se déclenche automatiquement.

### 5.3 Persistance entre app launches

Le token reste dans SecureStore tant que l'OS ne wipe pas le keychain
(rare : restauration usine, désinstallation, "Reset Keychain" admin).

→ **L'utilisateur reste connecté entre les sessions**, jusqu'à logout
explicite ou 401.

---

## 6. Mode offline & synchronisation

### 6.1 Cache HTTP persisté

`QueryProvider` enveloppe le client dans `PersistQueryClientProvider`
qui sérialise toutes les queries `success` dans `AsyncStorage` toutes
les 1 seconde (debounce intégré).

Au cold-start, la cache est rehydratée avant tout `useQuery`. Si
l'utilisateur ouvre l'app sans réseau, il voit les listes / annonces
qu'il avait déjà consultées (jusqu'à 24 h d'âge).

### 6.2 Online / offline detection

`onlineManager.setEventListener` est branché sur `NetInfo` au boot.
Quand `state.isConnected === false`, TanStack suspend les fetch
automatiquement (les mutations en cours échouent en error réseau,
celles à venir patientent).

### 6.3 OfflineBanner

`<OfflineBanner />` est rendu au niveau racine. Il écoute
`NetInfo.addEventListener` et slide-in / slide-out via `Animated`.

### 6.4 Refetch à la reconnexion

`refetchOnReconnect: true` au niveau QueryClient → dès que NetInfo
remonte `isConnected=true`, toutes les queries stale se rafraîchissent.

### 6.5 AppState focus refetch

Quand l'app passe de background → foreground, `AppState` émet `active`.
On utilise `focusManager.setFocused(true)` → les queries marquées stale
se rafraîchissent.

### 6.6 Versioning du cache

`CACHE_BUSTER = 'kh-v1'` côté QueryProvider. Bump à chaque changement
breaking de shape API → la persistance précédente est jetée au boot.

### 6.7 Exclusions du persist

Certaines queries ne sont jamais persistées :
- `me` : doit toujours être frais (état utilisateur)
- `conversation-messages` : on veut le feed live, pas un cache 24 h
- `notifications-unread-count` : badge live

---

## 7. Chat Messenger-style

### 7.1 Endpoints utilisés

- `GET /conversations` (liste, polling 30 s)
- `GET /conversations/{uuid}/messages` (thread, polling 4 s)
- `POST /conversations/{uuid}/messages` (envoi texte)
- `POST /conversations/{uuid}/attachments` (multipart photo)
- `POST /conversations/{uuid}/typing` (debounced 1.5 s)
- `PATCH /conversations/{uuid}/read` (mark read)
- `POST /messages/{uuid}/reactions` + `DELETE /messages/{uuid}/reactions`
- `DELETE /messages/{uuid}`

### 7.2 Features

| Feature             | Statut | Implémentation                                        |
| ------------------- | ------ | ----------------------------------------------------- |
| Liste conversations | ✅     | Tri par recency, unread badge, last message preview   |
| Thread polling 4 s  | ✅     | TanStack `refetchInterval`                            |
| Envoi optimistic    | ✅     | uuid local `temp:*` + replace au succès               |
| Échec + retry       | ✅     | `is_failed=true` + bouton retry sur la bulle          |
| Clustering          | ✅     | 5 min gap → cache horodatage, queue arrondie au tail  |
| Date separators     | ✅     | "Aujourd'hui" / "Hier" / "15 mars"                    |
| Reactions long-press | ✅    | Modal emoji 6 options + retire si déjà réagi          |
| Suppression         | ✅     | Sur ses propres messages, depuis le picker emoji      |
| Read receipts       | ✅     | `delivered_at` → "livré", `read_at` → "lu" (bleu)     |
| Attachments (image) | ✅     | Picker + upload multipart + preview dans la bulle     |
| Typing indicator    | ⚠️ partial | POST envoyé ; affichage `… est en train d'écrire` à câbler |
| Vocal messages      | ❌     | Hors scope MVP                                        |
| Real-time push      | ⚠️     | Polling 4 s (WebSocket nécessite dev-build)           |

### 7.3 Comportements UI

- **Bulle** : background brand sur les miennes, slate100 sur les autres.
- **Tail** : coin bas arrondi à 6 px (vs 18) sur le dernier message du
  cluster — code visuel familier Messenger.
- **Reactions affichées** : pill flottante sous la bulle si > 0.
- **KeyboardAvoidingView** : remonte le composer quand le clavier ouvre.
- **scrollToEnd** : `requestAnimationFrame` après chaque mutation pour
  scroller au plus récent.

---

## 8. Splash & boot

1. `expo-router` cache le splash natif après ~100 ms (geste rapide).
2. `<SplashView />` overlay prend le relais (background brand crimson).
3. Pendant 600 ms minimum, l'animation joue :
   - Si `lottieSource` fourni → Lottie loop autoplay 220×220.
   - Sinon fallback : wordmark "KeyHome" lettre-par-lettre staggered
     scale-up (60 ms entre lettres, back-easing) + pulse dot infini.
4. `useSession().isLoading=false` ET min-delay écoulé → fade-out 340 ms.
5. SplashView unmount, l'écran routé devient visible.

Pour shipper un VRAI Lottie : placer un JSON sous `assets/splash.json`,
puis dans `_layout.tsx` :
```ts
import splashAnim from '@/assets/splash.json';
// ...
<SplashView ready={...} lottieSource={splashAnim} />
```

---

## 9. Push notifications

- Lazy-import de `expo-notifications` : la lib n'est chargée QUE en
  dev-build ou prod ; dans Expo Go, on no-op (sinon 3 warnings SDK 53+).
- Détection : `Constants.executionEnvironment === StoreClient` (Expo Go).
- En dev-build :
  1. Demande permission notification (sinon abort).
  2. Crée le channel Android par défaut.
  3. Récupère le token Expo push (avec projectId si dispo).
  4. POST `/fcm/token` avec `{token, platform, provider:'expo'}`.
- Aucune feature push native n'est utilisée (badge, sound). À ajouter
  si besoin via `setNotificationHandler` options.

---

## 10. Design system Tamagui

### 10.1 Tokens (`src/theme/tokens.ts`)

- **Brand** : `#F6475F` (crimson) + alpha 10/20 + hover/text
- **Slate** : 900/700/500/300/100 (gris froid)
- **Semantic** : success #16A34A, warning #F59E0B, danger #EF4444, info #2563EB

### 10.2 Mapping Tamagui (`tamagui.config.ts`)

Étend `@tamagui/config/v3` avec les couleurs brand. Themes `light` /
`dark` overridés (background blanc/noir, borderColor slate300/slate700).

### 10.3 Conventions UI

- Cartes : `borderRadius 14`, pas de border ni shadow par défaut
- Boutons primaires : `backgroundColor="$brand"` + `color="white"` +
  `fontWeight="700"` + `borderRadius={12-14}`
- Texte body : 14.5–15 px, lineHeight 20–22
- Headings : `<H2 fontSize={20-24} fontWeight="700-800">`
- Padding standard : 16/20 px horizontal, 14/18 px vertical
- Safe area : `useSafeAreaInsets()` partout, padding-top/bottom adaptés

---

## 11. Animations

Toutes les animations utilisent RN `Animated` (pas Reanimated) avec
`useNativeDriver: true` quand possible (transform + opacity).

| Surface         | Effet                                                          |
| --------------- | -------------------------------------------------------------- |
| AdCard press    | scale 0.965 → 1, spring damping 22 stiffness 400               |
| FavoriteButton  | sequence keyframe `1 → 1.5 → 0.85 → 1.15 → 1` (500 ms total)   |
| AdCardSkeleton  | pulse opacity `0.6 ↔ 1` en boucle, ease in/out 1.4 s          |
| Login success   | overlay opacity fade + check spring scale + halo expand        |
| Ad detail hero  | parallax `translateY` × 0.4 + overscroll `scale` × 1.4         |
| Ad detail chrome | opacity fade-in 0 → 1 quand scroll dépasse heroHeight          |
| SplashView      | wordmark stagger scale-up + dot pulse infinite                 |
| OfflineBanner   | translateY -100 → 0 quand offline, retour en sortie             |

---

## 12. Conformité SOLID

### Single Responsibility ✅
- Axios = HTTP. Endpoints = URLs. Hooks = cache. Composants = UI.
- Chaque hook = 1 endpoint, 1 cache key, 1 select.
- Aucun composant ne fait du HTTP direct.

### Open/Closed ✅
- Ajouter une feature (sondages, litiges) ne touche pas les providers
  ni les interceptors.
- Nouveau hook = même pattern, drop-in.

### Liskov ✅
- Tous les `useQuery` retournent la même shape `{data, isLoading,
  isError, error, refetch}`. Les `useMutation` retournent
  `{mutate, mutateAsync, isPending, isError, error}`.

### Interface Segregation ✅
- SessionProvider expose `{token, isLoading, isAuthenticated, signIn,
  signUp, signOut, setToken}` — minimal et stable.
- CompareProvider expose `{ads, isFull, toggle, isCompared, clear}`.

### Dependency Inversion ✅
- Hooks dépendent de `apiClient` (abstraction), pas de `axios.create()`
  direct.
- Components dépendent de hooks, pas de fetchers ad-hoc.

### DRY ✅
- AdCard utilisé feed/search/favorites/agences/bailleurs.
- AdCardSkeleton réutilisé partout où on a un loading grid.
- FavoriteButton et CompareButton partagés card + détail.

### Type safety ✅
- TypeScript strict + `noUncheckedIndexedAccess`.
- Tous les payloads API typés (`src/types/`).
- Routes typées via `expo-router` (sauf casts `as never` ponctuels pour
  routes nouvellement ajoutées avant le re-gen des types — à régénérer
  à la prochaine build).

---

## 13. Couverture web → mobile

| Feature web                       | Mobile | Notes                                   |
| --------------------------------- | ------ | --------------------------------------- |
| `/(auth)/login`                   | ✅     | + animation succès                      |
| `/(auth)/register`                | ✅     | Zod validation                          |
| `/(auth)/forgot-password`         | ✅     |                                         |
| `/(auth)/reset-password`          | ✅     |                                         |
| `/(auth)/verify-email/-otp`       | ✅     | 6 cases + paste + cooldown 60 s         |
| `/(auth)/auth/callback`           | ❌     | OAuth — patterns natifs en dev-build    |
| `/(dashboard)/home`               | ✅     | Greeting + reco + chips types           |
| `/(dashboard)/search`             | ✅     | + filter sheet (price/surface/type/tx)  |
| `/(dashboard)/nearby`             | ✅     | react-native-maps + radius picker       |
| `/(dashboard)/profile`            | ✅     | + upload avatar                         |
| `/(dashboard)/parametres`         | ✅     | + sign-out + delete account             |
| `/(dashboard)/messages`           | ✅     | inbox + thread Messenger-style          |
| `/(dashboard)/notifications`      | ✅     | tabs + bulk read + delete               |
| `/(dashboard)/comparaisons`       | ✅     | via `/compare`                          |
| `/(dashboard)/payments`           | ✅     | via `/credits`                          |
| `/(dashboard)/my/reservations`    | ✅     | tabs upcoming/passées + cancel          |
| `/(dashboard)/litiges`            | ✅     | liste + détail + chat + preuves         |
| `/(dashboard)/remboursements`     | ✅     | liste + modal new request               |
| `/(dashboard)/search-alerts`      | ✅     | CRUD + modal editor                     |
| `/(dashboard)/prix-marche`        | ✅     | via `/market-prices`                    |
| `/(dashboard)/aide`               | ✅     | FAQ accordéons                          |
| `/(dashboard)/contact`            | ✅     | support form                            |
| `/ads/[slug]`                     | ✅     | hero parallax, map+distance+directions, KeyScore, scorecard, attributes, avis, similar, booking, share |
| `/agences/[id]`                   | ✅     |                                         |
| `/bailleurs/[username]`           | ✅     | follow + message                        |
| `/proprietaires/[id]`             | ✅     | alias                                   |
| `/blog`                           | ✅     | 4 articles statiques                    |
| `/comparaison`                    | ✅     | 3 comparatifs statiques                 |
| `/surveys`                        | ✅     | + sondage/[id] alias                    |
| `/conditions`, `/confidentialite` | ✅     | summarisé + lien web complet            |
| `/payment-success` / `/payment/return` | ✅ | polling status                         |
| `/offline`                        | ✅     | + OfflineBanner global                  |
| `/immobilier/[ville]`             | ⏭     | SEO landing, redondant vs search        |
| `/type-bien/[type]`               | ⏭     | SEO landing, redondant vs search        |
| `/indices-loyers`                 | ⏭     | redondant vs `/market-prices`           |
| `/share`, `/sign/[token]`         | ⏭     | technique / owner-only                  |

✅ = porté · ⚠️ = partiel · ⏭ = skip volontaire · ❌ = pas applicable mobile

---

## 14. Roadmap & dette technique

### 🔴 P0 — Blockers store

- [ ] **Error boundary racine** + Sentry init (post-launch debugging)
- [ ] **Tests Jest + RTL** sur les 5 hooks critiques (auth, feed, send-message)
- [ ] **EAS Build profile** (`eas.json`) + bundle ID stable

### 🟠 P1 — Pré-launch

- [ ] **Real-time chat** via Pusher/Echo (remplacer polling 4 s)
- [ ] **Analytics events** (login, favorite, message, reserve, payment)
- [ ] **Permissions** runtime check (location, notif, photos) avec
      fallback UI claire

### 🟡 P2 — Polish

- [ ] **Image variantes** WebP + responsive (saves ~30 % bytes feed)
- [ ] **Rate limiting backoff** sur 429/503 (TanStack `retry` custom)
- [ ] **i18n EN** complet (FR-only actuellement)
- [ ] **Avatar crop UI** (mobile crop avant upload)

### 🟢 P3 — Nice-to-have

- [ ] **Vocal messages** dans le chat
- [ ] **3D tour viewer** (Photo Sphere)
- [ ] **Recharge crédits in-app** (intégration Flutterwave SDK)

---

## 15. Enterprise foundation (v0.7)

### 15.1 ErrorBoundary + Sentry
- `components/ErrorBoundary.tsx` racine — capture les throws render/lifecycle, fallback UI + bouton retry, panneau debug en `__DEV__`.
- `services/monitoring.ts` : `initMonitoring()` au boot, `reportError`, `setUserContext`/`clearUserContext` (signIn/signOut), `trackEvent` breadcrumbs. No-op en Expo Go ou sans `EXPO_PUBLIC_SENTRY_DSN`.

### 15.2 Tests Jest
- **26 tests passent** sur 5 suites (`utils/geo`, `utils/error-extraction`, `utils/endpoints`, `utils/filters`, `hooks/useGreeting`).
- `jest.config.js` minimal sur préset `jest-expo` 56 (Jest pinné à ~29.7 pour compat).
- Scripts : `npm test`, `npm run test:watch`, `npm run test:coverage`.

### 15.3 Realtime chat (Laravel Reverb / Echo)
- `services/echo.ts` : wrapper `pusher-js/react-native` sur Reverb, auth via Sanctum sur `/broadcasting/auth`. No-op si env vars vides.
- `hooks/useConversationRealtime.ts` : subscribe aux 6 events (`message.sent`, `messages.read`, `message.deleted`, `message.reaction.added/.removed`, `user.typing`), patch le cache TanStack in-place.
- Header chat : "En direct" / "En train d'écrire…" temps réel.
- Polling 4 s reste actif en fallback.
- Env : `EXPO_PUBLIC_REVERB_APP_KEY`, `EXPO_PUBLIC_REVERB_HOST`, `EXPO_PUBLIC_REVERB_PORT`, `EXPO_PUBLIC_REVERB_SCHEME`.

### 15.4 EAS Build + GitHub Actions
- `eas.json` : 3 profiles (development APK + simulator / preview internal / production app-bundle + autoIncrement).
- `.github/workflows/mobile-ci.yml` : typecheck + jest sur Node 22 + preview build PR (skip si `EXPO_TOKEN` absent).

### 15.5 `app.json` store-ready
- iOS infoPlist : `NSPhotoLibraryUsageDescription`, `NSCameraUsageDescription`, `NSUserNotificationsUsageDescription`, `ITSAppUsesNonExemptEncryption: false`.
- Android permissions : `READ_MEDIA_IMAGES`, `READ_EXTERNAL_STORAGE`, `CAMERA`, `POST_NOTIFICATIONS`, `INTERNET`.
- Plugins : expo-location, expo-image-picker, expo-notifications, @sentry/react-native/expo.
- `version: 0.6.0`, `buildNumber: 6`, `versionCode: 6`, splash bg #F6475F brand.

### 15.6 Accessibility batch v1
- 8 back buttons critiques reçoivent `accessibilityRole="button"` + `accessibilityLabel="Retour"` (aide, conditions, confidentialite, market-prices, profile, offline, contact, chat thread).
- 22 a11y gaps mineurs restants (chip filters, star ratings, time slots) listés pour sprint dédié.

### 15.7 Analytics events
- `auth.signIn` + `auth.signOut` capturés dans `SessionProvider`.
- `setUserContext({id, email})` au signIn / `clearUserContext()` au signOut → tous les Sentry events sont tagués user.
- Extension future : favorite, reserve, contact, payment events à wire dans leurs mutations respectives.

---

## Historique des versions

- **v0.9** (2026-06-22) : **Push final 100 %** — Tests élargis à **40 passants** sur 8 suites (resilience `useAdFeed`, `useReviews`, normalisation scorecard) · `FadeIn` appliqué aux listes principales home + search (stagger 40 ms, cap à 6 cartes) · `EmptyState` appliqué à favorites + search · Haptics `selectionAsync()` sur chaque press du bottom tab · A11y batch 2 : 18 back buttons supplémentaires reçoivent `accessibilityRole="button"` + `accessibilityLabel="Retour"` (notifications, refunds, parametres, reservations, search-alerts, credits, messages index, bailleurs, surveys, disputes, blog, comparaison, agences, nearby) · expo-updates OTA wiré (`runtimeVersion.policy: appVersion`, `checkAutomatically: ON_LOAD`) · `.eslintrc.json` (`extends: expo`) · Cleanup `jest.config.js` (suppression `setupFilesAfterEach` warning)
- **v0.8** (2026-06-22) : TS 100 % clean (useStorageState typing fix) · Endpoint `/auth/update-password` aligné · `/support/contact` remplacé par mailto natif · Polling payment-success timeout 60 s + retry · Error states + retry partout (estimator/market-prices/search/compare) · Hero ad-detail fallback icon · Empty state compare moderne · `<FadeIn>` entrance + `<EmptyState>` réutilisables (modern design system)
- **v0.7** (2026-06-21) : ErrorBoundary + Sentry + Jest (26 tests passent) + EAS Build + GitHub Actions CI + Reverb realtime chat (typing, read, reactions, delete) + app.json store-ready + Analytics events + a11y batch (8 back buttons)
- **v0.6** (2026-06-21) : Splash Lottie + OfflineBanner + PersistQueryClient + Chat Messenger-style (clustering/reactions/attachments/read receipts/optimistic UI/retry) + Documentation
- **v0.5** : Pages legal (CGU, Privacy) + Comparaison + Blog + Sondage alias + Polish tab bar
- **v0.4** : Reservations + Disputes + Refunds + Surveys + Market prices + Payment success + Offline
- **v0.3** : Bailleurs/Agences profiles + Messages + Notifications + Profile/Settings + Search-alerts + Credits + Nearby + Aide
- **v0.2** : Ad detail complet (map, KeyScore, scorecard, attributes, reviews, similar, booking)
- **v0.1** : Auth + home/search/favorites/account + AdCard Airbnb-flat

---

_Mis à jour le 2026-06-22. Maintenu à chaque feature majeure._
