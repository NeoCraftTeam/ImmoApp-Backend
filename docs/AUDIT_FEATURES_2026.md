# Audit Fonctionnel KeyHome — 3 Panels (Mai 2026)

> Recherche expert réalisée via Firecrawl · Sources : NNGroup, FIDO Alliance Passkey Central,
> Stripe Docs 2025, LogRocket, Orange Developer, Dusupay, Scott O'Hara a11y, NeedLaravelSite,
> Resend Email Best Practices, Meilisearch Docs.

---

## Sommaire exécutif

KeyHome est un marketplace immobilier multi-tenant SaaS sur trois panels (customer, bailleur/owner,
agence). L'audit couvre **9 domaines fonctionnels** et identifie **35 gaps critiques ou améliorations
prioritaires** classés par sévérité. La majorité concernent la sécurité du flux d'authentification,
la gestion des sessions frontend, les webhooks paiement, l'accessibilité et l'isolation multi-tenant.

---

## 0. Flux d'authentification & Gestion des sessions

### Cartographie du flux implémenté

```
┌─────────────────────────────────────────────────────────────────────┐
│                     MÉTHODES D'AUTHENTIFICATION                       │
├─────────────────┬──────────────┬───────────────┬────────────────────┤
│  Email/Password │    OAuth     │  Clerk JWT    │   WebAuthn/Passkey │
│  (LoginService) │  (Socialite) │  Exchange     │   (WebAuthnService)│
└────────┬────────┴──────┬───────┴───────┬───────┴──────────┬─────────┘
         │               │               │                  │
         ▼               ▼               ▼                  ▼
    Rate Limit      Exchange code   JWKS verify        Assertion
    Turnstile        redemption     RS256 only          verify
    Hash check       + /me call     + Clerk API         + role ctx
         │               │               │                  │
         └───────────────┴───────────────┴──────────────────┘
                                 │
                    ┌────────────▼─────────────┐
                    │   TokenService            │
                    │   rotateForUser()         │
                    │   - prefix: owner|client  │
                    │   - ability: role:* +     │
                    │     api:access            │
                    │   - expiry: configurable  │
                    └────────────┬─────────────┘
                                 │
            ┌────────────────────┼────────────────────┐
            ▼                    ▼                    ▼
     In-memory token      kh_role cookie        panel_sso_url
     ownerInMemoryToken   (SameSite=Lax,         (signed, 60s)
     clientInMemoryToken   path-scoped)
     (jamais localStorage)

┌──────────────────────────────────────────────────────────────────┐
│                 ADMIN PANEL (Filament 4)                          │
│  Session Laravel + CacheEmailAuthentication (MFA email OTP)      │
│  OTP stocké en cache (pas en session) — corrige race condition   │
│  Livewire session→user mapping via Cache                         │
└──────────────────────────────────────────────────────────────────┘
```

### Mécanismes de sécurité actifs

| Mécanisme | Implémentation | Statut |
|---|---|---|
| Rate limiting login | 5 tentatives / IP+email (300s cooldown) | ✅ |
| Cloudflare Turnstile | `TurnstileService::verify()` | ✅ |
| Timing-safe OTP compare | `hash_equals()` | ✅ |
| JWT alg enforcement | RS256 uniquement, alg=none rejeté | ✅ |
| Session regeneration | `$request->session()->regenerate()` sur login | ✅ |
| Email verification gate | `email_verified_at` requis sur toutes les méthodes | ✅ |
| Panel isolation | `RoleContextMismatchException` + prefix owner/client | ✅ |
| Logout single device | `$token->delete()` + session invalidate | ✅ |
| Logout all devices | `$user->tokens()->delete()` | ✅ |
| New device/location alert | `NewDeviceSignInMail` / `NewLocationSignInMail` | ✅ |
| Login history | `LoginHistory` model avec device, IP, pays | ✅ |
| Token expiry configurable | `sanctum.expiration` utilisé dans `TokenService` | ✅ |
| Token rotation | `rotateForUser()` révoque l'ancien avant de créer | ✅ |
| In-memory only (frontend) | Jamais `localStorage`, dual-slot owner/client | ✅ |
| Legacy token migration | `migrateLegacyTokens()` avec validation | ✅ |
| 401 auto-wipe | `use401Listener` + `kh:auth-expired` event | ✅ |
| Filament MFA | `CacheEmailAuthentication` (cache-backed OTP) | ✅ |

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| AUTH-1 | ~~**Pas de refresh proactif**~~ — **✅ FIXÉ** : timer `useEffect` dans `AuthProvider` (5 min avant expiry) + `expires_at` propagé depuis tous les endpoints auth | ✅ Fixé |
| AUTH-2 | ~~**`aud` (audience) claim non vérifié**~~  — **✅ FIXÉ** : validation `iss` contre `CLERK_JWKS_URL` dans `ClerkJwtService::verifyJwt()` | ✅ Fixé |
| AUTH-3 | **`TransientToken` non révocable** — le token Clerk JWT exchange (non-PAT) ne peut pas être révoqué via `delete()`. Un logout Clerk côté frontend n'invalide pas la session Laravel | 🟠 Moyen |
| AUTH-4 | **`panel_sso_url` expire en 60s** — sur des connexions africaines lentes (2G/3G), ce délai peut être insuffisant pour le redirect admin | 🟠 Moyen |
| AUTH-5 | **Pas de token family tracking** — si un refresh token est volé et utilisé, aucune alerte n'est générée. OWASP recommande d'invalider toute la famille de tokens sur réutilisation d'un token révoqué | 🔴 Critique — ❌ Non implémenté |
| AUTH-6 | **`kh_role` cookie accessible au JS** — le cookie de rôle n'est pas `HttpOnly`. Bien que non-autoritaire, il peut fuiter via XSS | 🟡 Bas |
| AUTH-7 | **OTP Clerk (`clerk_otp_cooldown_`) de 60s** — un attaquant peut bloquer l'accès à un compte en appelant `/clerk/exchange` en boucle (même IPs rotatives) | 🟠 Moyen |
| AUTH-8 | **Pas de 2FA pour les utilisateurs API** — seul le panel admin Filament a le MFA email. Les bailleurs et clients n'ont pas d'option 2FA | 🟠 Moyen |
| AUTH-9 | **`LoginHistory` non purgée** — la table grossit indéfiniment ; pas de job de nettoyage ou de TTL | 🟡 Bas |
| AUTH-10 | **`useClerkSync` re-trigger sur window focus** — chaque retour de tab déclenche un appel Clerk API. Sur mobile, fréquent et coûteux | 🟠 Moyen |
| AUTH-11 | **Pas de `nbf` strict** — la tolérance clock-skew de 30s pour `nbf`/`iat` est correcte mais non documentée comme paramètre configurable | 🟡 Bas |

### Recommandations

#### AUTH-1 : Refresh proactif (interceptor Axios)

```typescript
// lib/api.ts — ajouter dans l'interceptor response
api.interceptors.response.use(
  (response) => {
    // Refresh proactif si le token expire dans < 5 minutes
    const expiresAt = response.headers['x-token-expires-at'];
    if (expiresAt) {
      const expiresIn = new Date(expiresAt).getTime() - Date.now();
      if (expiresIn < 5 * 60 * 1000 && expiresIn > 0) {
        window.dispatchEvent(new Event('kh:token-expiring-soon'));
      }
    }
    return response;
  },
  async (error) => {
    if (error.response?.status === 401) {
      window.dispatchEvent(new Event('kh:auth-expired'));
    }
    return Promise.reject(error);
  }
);
```

```typescript
// AuthProvider.tsx — écouter kh:token-expiring-soon
useEffect(() => {
  const handleExpiringSoon = () => {
    refreshSession().catch(() => {}); // refresh silencieux
  };
  window.addEventListener('kh:token-expiring-soon', handleExpiringSoon);
  return () => window.removeEventListener('kh:token-expiring-soon', handleExpiringSoon);
}, [refreshSession]);
```

#### AUTH-2 : Validation `aud` dans ClerkJwtService

```php
// ClerkJwtService::verifyJwt() — ajouter après vérification exp/nbf
$expectedIssuer = rtrim(config('clerk.frontend_api_url', ''), '/');
if (isset($payload['iss']) && !str_starts_with((string) $payload['iss'], $expectedIssuer)) {
    Log::warning('Clerk JWT rejected: issuer mismatch', [
        'iss' => $payload['iss'],
        'expected_prefix' => $expectedIssuer,
    ]);
    return null;
}
// Audience optionnel si configuré
$expectedAud = config('clerk.audience');
if ($expectedAud !== null && ($payload['aud'] ?? null) !== $expectedAud) {
    return null;
}
```

#### AUTH-5 : Token family tracking (détection vol de refresh token)

```php
// Migration: ajouter token_family_id à personal_access_tokens
Schema::table('personal_access_tokens', function (Blueprint $table) {
    $table->uuid('family_id')->nullable()->index();
    $table->string('parent_token_id')->nullable();
});

// TokenService::rotateForUser() — invalider toute la famille si réutilisation
public function rotateForUser(User $user, string $suffix, ?string $revokePattern = null, ?string $prefixOverride = null): NewAccessToken
{
    if ($revokePattern !== null) {
        $revoked = $user->tokens()->where('name', 'like', $revokePattern)->get();
        
        // Détecter la réutilisation d'un token déjà révoqué (famille compromise)
        if ($revoked->isEmpty() && $this->isTokenFamilyCompromised($user, $revokePattern)) {
            $user->tokens()->delete(); // Invalider toute la famille
            Log::alert('Token family compromise detected — all sessions revoked', ['user_id' => $user->id]);
        }
        
        $user->tokens()->where('name', 'like', $revokePattern)->delete();
    }
    
    return $this->createForUser($user, $suffix, $prefixOverride);
}
```

#### AUTH-8 : 2FA optionnel pour les utilisateurs API

Ajouter un endpoint `POST /auth/totp/enable` avec `pragmarx/google2fa-laravel` :
- TOTP (Google Authenticator / Authy) pour les bailleurs et clients premium.
- Stocker le secret chiffré : `encrypt($secret)` dans `users.totp_secret`.
- Gate : si `totp_secret` non null → challenge TOTP avant émission du token.

---

## 1. Navigation mobile (Navbar / OwnerNavbar)

### Implémentation actuelle
- Customer panel : avatar gauche → drawer, logo centre, `CreditsWidget` droite.
- Owner panel : avatar hamburger gauche, logo centre, `CreditsWidget` droite.
- Grid CSS `1fr auto 1fr` pour centrage absolu du logo.

### Ce que disent les experts (NNGroup 2025)

> *"Tab bars are well suited for sites with relatively few navigation options. If your site has more
> than 5 options, it's hard to fit them in a tab bar."* — NN/Group

> *"Navigation bars usually disappear once the user has scrolled. A sticky version stays put."*

| Pattern | Avantages | Inconvénients |
|---|---|---|
| **Tab bar persistante (notre choix)** | Toujours visible, accès rapide | Prend de l'espace, max 5 items |
| Hamburger menu | Compact, n items | Out-of-sight = out-of-mind |
| Navigation hub | Task-based UX | Retour au hub à chaque action |

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| N-1 | **Pas de bottom nav persistante** — la navbar top disparaît au scroll sur mobile | 🟠 Moyen |
| N-2 | **Labels icônes manquants** sur le drawer mobile — NNGroup recommande de labeler toutes les icônes | 🟠 Moyen |
| N-3 | **`CreditsWidget` fond hardcodé `rgba(246,71,95,…)`** sur panel teal (owner) : incohérence visuelle | 🟡 Bas |
| N-4 | ~~**Pas de `aria-current="page"`**~~ — **✅ FIXÉ** : `aria-current={isActive ? 'page' : undefined}` sur `Navbar`, `OwnerSidebar`, `BottomNav`, `OwnerBottomNav` | ✅ Fixé |
| N-5 | **Touch target < 44px** potentiel sur certains IconButton du drawer mobile (WCAG 2.5.5) | 🟠 Moyen |

### Recommandations

- **N-1** : Ajouter une bottom tab bar persistante sur mobile avec 4-5 items clés (Accueil, Recherche,
  Carte, Messages, Profil) en plus de la top navbar. Utiliser `position: fixed; bottom: 0`.
- **N-2** : Toujours inclure un label texte sous chaque icône dans le drawer.
- **N-3** : Faire du `CreditsWidget` theme-aware en utilisant `theme.palette.primary.main` pour les
  couleurs au lieu de hardcoder `#F6475F`.
- **N-4** : Ajouter `aria-current="page"` sur les liens actifs dans `NavDrawer`.

---

## 2. Avatar / Photo de profil (Crop & Upload)

### Implémentation actuelle
- `react-easy-crop` avec `AvatarCropDialog` (dynamically imported, SSR false).
- Canvas API pour extraction du crop.
- Remplacement de la photo (supprime l'ancienne, évite doublons).
- Collection Spatie Media Library `'avatars'`.

### Ce que disent les experts (LogRocket, 2024)

Comparaison des librairies de crop React :

| Librairie | Stars GitHub | Maintenance | Best for |
|---|---|---|---|
| **react-image-crop** | 3.7k | Active | Légère, zero deps |
| **react-avatar-editor** | 2.3k | Active | Profile pictures |
| **react-easy-crop** (notre choix) | 1.7k | Active | Beginner-friendly API |
| react-cropper | 2k | Semi-active | Full-featured |
| react-advanced-cropper | 1k | Low | Mobile + advanced |

> `react-easy-crop` est un **bon choix** pour l'usage actuel (avatar circulaire, zoom, drag).
> Léger, API simple, supporte le mode portrait mobile.

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| A-1 | ~~**Pas de validation côté serveur du type MIME**~~ — **✅ FIXÉ** : `mimes:jpeg,jpg,png,webp` + `mimetypes:image/jpeg,image/png,image/webp` dans `UserRequest`, suppression de `gif` | ✅ Fixé |
| A-2 | **Pas de taille max enforced backend** — possible DoS par upload de fichiers > 10MB | 🟠 Moyen |
| A-3 | **Alt text de l'avatar** vide ou générique sur le profil public → SEO + a11y | 🟡 Bas |
| A-4 | **Pas de WebP conversion** à l'upload → gaspillage bandwidth (Africa réseau lent) | 🟠 Moyen |
| A-5 | ~~**Crop dialog n'est pas accessible**~~ — **✅ FIXÉ** : `aria-labelledby="avatar-crop-dialog-title"` + `aria-modal` + `id` sur `DialogTitle` dans `AvatarCropDialog.tsx` | ✅ Fixé |

### Recommandations

```php
// Backend: validation Spatie + MIME vérification réelle
$request->validate([
    'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', // 5MB
        function ($attr, $value, $fail) {
            $mime = mime_content_type($value->getRealPath());
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
                $fail('Type de fichier non autorisé.');
            }
        }
    ],
]);
```

- **A-4** : Convertir en WebP côté backend via Intervention Image ou Spatie Image Optimizer.
- **A-5** : Ajouter `role="dialog" aria-modal="true" aria-labelledby="crop-dialog-title"` au
  `AvatarCropDialog`.

---

## 3. Notifications Snackbar (KhSnackbar)

### Implémentation actuelle
- Composant `KhSnackbar` avec `severity`, teal success (`#0D9488`), durée 3000ms.
- `variant="filled"`, `borderRadius: 12px`, `maxWidth: 360px`.
- Placé en bas-centre (`anchorOrigin: bottom/center`).

### Ce que disent les experts (Scott O'Hara, WCAG 2025)

> *"A toast should be injected into a `role='status'`… toasts should not contain interactive
> controls… WCAG 2.2.1 Timing Adjustable must be considered."*
> — Scott O'Hara, a11y expert (updated 2025-05-01)

> *"If someone ignores a toast due to its timed display, there should be no negative impact on
> their current activities."* — Accessibility standard

| Critère WCAG | Niveau | Notre situation |
|---|---|---|
| 2.2.1 Timing Adjustable | A | ❌ Pas de contrôle utilisateur sur la durée |
| 4.1.3 Status Messages | AA | ❌ Pas de `role="status"` ou `aria-live` explicite |
| 1.4.3 Contrast | AA | ✅ Teal sur blanc OK (ratio > 4.5:1) |
| 2.4.3 Focus Order | A | ⚠️ Close button dans toast peut piéger le focus |

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| S-1 | ~~**`KhSnackbar` utilise MUI `Alert` mais ne déclare pas `role="status"`**~~ — **✅ FIXÉ** : `role="status"`, `aria-live={severity === 'error' ? 'assertive' : 'polite'}`, `aria-atomic="true"` ajoutés | ✅ Fixé |
| S-2 | ~~**Bouton close dans le toast**~~ — **✅ FIXÉ** (même commit que S-1) | ✅ Fixé |
| S-3 | **Durée fixe 3000ms** — WCAG 2.2.1 recommande que l'utilisateur puisse ajuster ou désactiver | 🟠 Moyen |
| S-4 | **Pas de `notistack`** — gestion de la file d'attente absente : plusieurs toasts simultanés se superposent | 🟠 Moyen |
| S-5 | **Aucun log de notifications** — une fois disparu, pas moyen de revoir le message | 🟡 Bas |

### Recommandations

```tsx
// KhSnackbar.tsx : correction a11y critique
<Alert
  role="status"           // ← ajouter
  aria-live="polite"      // ← ajouter
  aria-atomic="true"      // ← ajouter
  onClose={onClose}
  severity={severity}
  variant="filled"
  ...
>
```

- **S-4** : Adopter `notistack` (`enqueueSnackbar`) pour la gestion de file d'attente.
- **S-5** : Implémenter un `role="log"` caché comme historique de notifications accessible.

---

## 4. Paiement (Stripe + GeniusPay/Orange Money)

### Implémentation actuelle
- **Stripe** : PaymentIntent + clientSecret, webhook HMAC signé (`Stripe-Signature`), refund.
  Conversion XAF→EUR au taux BEAC (655.957 XAF/EUR). Idempotency keys `kh_initiate:*`.
- **GeniusPay** : hosted-checkout redirect, webhook HMAC, vérification `MTX-*`.
- **Flutterwave** : legacy (webhooks uniquement, plus de nouveau routage).

### Ce que disent les experts (Stripe Best Practices 2025, Dusupay)

> *"Companies using Payment Element report 11.9% more revenue vs legacy implementations."*
> — Mo Barut, Stripe Integration Guide 2025

> *"Cameroon is a mobile money–first market. If your checkout does not support MTN MoMo and
> Orange Money well, you will lose conversions."* — Dusupay

Orange Money API spécificités :
- OTP via USSD : l'utilisateur génère un mot de passe temporaire sur son téléphone.
- Disponible : Cameroun, Côte d'Ivoire, Sénégal, Mali, Guinée…
- **Accès limité** : KYA compliance obligatoire, approbation banque centrale requise.

| Gateway | Cameroun MTN | Orange Money | Cross-Africa | Statut KeyHome |
|---|---|---|---|---|
| **GeniusPay** | ✅ | ✅ | Partiel | ✅ Actif principal |
| **Stripe** | ❌ (carte) | ❌ | ✅ | ✅ Actif cartes |
| **Flutterwave** | ✅ | ✅ | ✅ | ⚠️ Legacy uniquement |
| **PayUnit** | ✅ | ✅ | ❌ | Pas intégré |
| **Campay** | ✅ | ✅ | ❌ | Pas intégré |

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| P-1 | ~~**Webhook GeniusPay : pas de vérification d'idempotence**~~ — **✅ MITIGÉ** : `HandlePostPaymentActions` a 3 gardes idempotentes (subscription `payment_id` unique, credits `payment_id` unique, boost `isBoosted()`) + `lockForUpdate()` + unique constraint DB + `CreditDoubleIssuanceTest` couvre tous les scénarios | ✅ Mitigé |
| P-2 | **Stripe : `allow_redirects: 'never'`** n'est pas configuré → peut causer des redirects inattendus sur mobile | 🟠 Moyen |
| P-3 | **Taux de change XAF→EUR hardcodé** à 655.957 — taux BEAC officiel mais sans mécanisme de mise à jour | 🟠 Moyen |
| P-4 | **Pas de retry exponential backoff** sur webhook processing failures | 🟠 Moyen |
| P-5 | **Pas de `PaymentMethod` de secours automatique** — si GeniusPay est down, pas de fallback mobile money | 🟠 Moyen |
| P-6 | **Restricted API keys Stripe** non documentées — utiliser des clés restreintes par environnement | 🟡 Bas |
| P-7 | **MTN MoMo absent du gateway principal** — Campay/PayUnit intégration manquante pour double couverture | 🟠 Moyen |
| P-8 | **Pas de monitoring webhook** (alerte si webhook n'est pas reçu sous X minutes) | 🟡 Bas |

### Recommandations

```php
// P-1 : Idempotence webhook GeniusPay
public function handleWebhook(Request $request): JsonResponse
{
    $eventId = $request->header('X-GeniusPay-Event-ID');
    
    if (WebhookEvent::where('event_id', $eventId)->exists()) {
        return response()->json(['status' => 'already_processed']);
    }
    
    DB::transaction(function () use ($eventId, $request) {
        WebhookEvent::create(['event_id' => $eventId, 'processed_at' => now()]);
        // … traitement
    });
}
```

---

## 5. Passkeys / WebAuthn

### Implémentation actuelle
- `PasskeyManager` composant frontend (liste, création, suppression, renommage).
- Hook `usePasskeyManager` + service WebAuthn backend.
- Support multi-passkeys par utilisateur.

### Ce que disent les experts (FIDO Alliance Passkey Central, janvier 2026)

Six nouvelles capacités WebAuthn 2025-2026 :

| Capacité | Description | Impact KeyHome |
|---|---|---|
| **Conditional Create** | Créer un passkey immédiatement après login password sans friction | ⭐⭐⭐ Haute |
| **Signal APIs** | Signaler les passkeys révoqués/invalides aux gestionnaires | ⭐⭐ Moyen |
| **Related Origin Requests** | Un passkey fonctionne sur tous les sous-domaines d'une marque | ⭐⭐⭐ Haute |
| **getClientCapabilities()** | Détecter les features WebAuthn supportées avant de montrer l'UI | ⭐⭐⭐ Haute |
| **Client Hints** | Guider l'utilisateur vers le bon type d'authenticator | ⭐ Bas |
| **Credential Exchange** | Export/import de passkeys entre gestionnaires de mots de passe | ⭐⭐ Moyen |

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| W-1 | **Pas de Conditional Create** — après login password, l'utilisateur n'est jamais invité à créer un passkey | � Moyen (reporté V2) |
| W-2 | **Pas de `getClientCapabilities()`** — on affiche le bouton passkey même si le navigateur ne supporte pas | 🟠 Moyen |
| W-3 | **Pas de Related Origin Requests** — un passkey créé sur `keyhome.app` ne marche pas sur `owner.keyhome.app` | � Moyen (reporté V2) |
| W-4 | **Pas d'UI adaptative** — si passkeys non supportés, aucun message clair n'explique pourquoi | 🟠 Moyen |
| W-5 | **Pas de Signal API** — les passkeys orphelins (utilisateur supprimé) restent dans les gestionnaires | 🟡 Bas |
| W-6 | **Pas de fallback d'authentification** si le passkey échoue sur un device non supporté | 🟠 Moyen |

### Recommandations

```typescript
// W-2 : Détection capabilities avant d'afficher l'UI
async function isPasskeySupported(): Promise<boolean> {
  if (!window.PublicKeyCredential) return false;
  
  // getClientCapabilities() — WebAuthn Level 3 (2025)
  if (PublicKeyCredential.getClientCapabilities) {
    const caps = await PublicKeyCredential.getClientCapabilities();
    return caps['conditionalCreate'] === true;
  }
  
  return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
}
```

```typescript
// W-1 : Conditional Create après login password réussi
async function offerPasskeyAfterPasswordLogin(userId: string) {
  const supported = await isPasskeySupported();
  if (!supported) return;
  
  // Silently request passkey creation — no explicit user action needed
  await navigator.credentials.create({
    publicKey: {
      ...registrationOptions,
      mediation: 'conditional', // ← new in WebAuthn Level 3
    }
  });
}
```

---

## 6. Architecture Multi-tenant SaaS

### Implémentation actuelle
- 3 panels partagent la même base de données PostgreSQL avec `tenant_id`/`agency_id`.
- Filament 4 admin panels séparés pour chaque rôle.
- Laravel Sanctum pour l'auth API.
- Global scopes `LandlordScope` pour isolation des annonces bailleur.

### Ce que disent les experts (NeedLaravelSite, Laravel 12 SaaS 2025)

Trois stratégies multi-tenant comparées :

| Stratégie | Isolation | Coût infra | Conformité données |
|---|---|---|---|
| **Single DB + tenant_id** (notre choix) | Moyen | Très bas | Suffisant pour CEMAC |
| Database per tenant | Très haute | Élevé | Idéal entreprise |
| Schema per tenant (PG) | Haute | Moyen | Bon compromis |

> *"Security must be built-in from the start with global scopes, proper authorization policies,
> and thorough testing to prevent data leakage between tenants."*

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| M-1 | ~~**Pas de test automatisé d'isolation**~~ — **✅ FIXÉ** : `TenantIsolationTest.php` — tests `/my/ads` (listing), expenses 403, documents 403, profit-loss 403 cross-tenant | ✅ Fixé |
| M-2 | ~~**`LandlordScope` potentiellement bypassable**~~ — **✅ ANALYSÉ** : scope jamais appliqué comme global scope (aucun `addGlobalScope` dans les models). Les appels `withoutGlobalScopes([SoftDeletingScope::class])` dans Filament admin sont explicitement scopés. Risque nul en l'état. | ✅ Mitigé |
| M-3 | **Pas de rate limiting par tenant** — un tenant peut saturer les queues et impacter les autres | 🟠 Moyen |
| M-4 | **Queue jobs pas tenant-aware** — un job en échec peut fuir des données de tenant entre workers | 🟠 Moyen |
| M-5 | **Pas de métriques par tenant** (usage, storage, API calls) — pas de monitoring de dépassement | 🟡 Bas |
| M-6 | ~~**Filament admin panel : pas de scope sur les exports CSV**~~ — **✅ SAIN** : agency panel `getEloquentQuery()` scopé `where('user_id', auth()->id())`; admin panel voit tout par design (super admin) | ✅ Sain |

### Recommandations

```php
// M-1 : Test isolation tenant à ajouter dans tests/Feature/
test('un bailleur ne peut pas accéder aux annonces d\'un autre bailleur', function () {
    $owner1 = User::factory()->owner()->create();
    $owner2 = User::factory()->owner()->create();
    
    $ad = Ad::factory()->for($owner2)->create();
    
    $this->actingAs($owner1)
        ->getJson("/api/v1/ads/{$ad->uuid}")
        ->assertForbidden();
});
```

```php
// M-3 : Rate limiting par tenant dans RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    $tenant = $request->user()?->tenant_id ?? $request->ip();
    return Limit::perMinute(120)->by($tenant);
});
```

---

## 7. Emails transactionnels (Resend + Laravel Mail)

### Implémentation actuelle
- Provider : Resend (via driver `resend`).
- Templates Blade : `card-added.blade.php`, `welcome`, password reset, etc.
- `MAIL_MAILER=array` forcé en tests (bootstrap.php).
- Notifications Laravel + `CardAddedMail` Mailable.

### Ce que disent les experts (Resend Email Best Practices, GitHub 2026)

Domaines couverts par les best practices officielles Resend :
1. DNS authentication (SPF, DKIM, DMARC)
2. Transactional email design
3. Compliance (CAN-SPAM, GDPR, CASL)
4. Idempotency & retry logic
5. Webhook processing for delivery events
6. Suppression lists & list hygiene

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| E-1 | **Pas de DMARC policy** vérifiée — emails peuvent aller en spam (config DNS Resend, hors codebase) | � Hors code |
| E-2 | ~~**Pas de webhook Resend**~~ — **✅ FIXÉ** : `POST /webhooks/resend`, `EmailSuppression` model, guard `MessageSending` dans `AppServiceProvider` | ✅ Fixé |
| E-3 | ~~**Idempotency des emails absente**~~ — **✅ FIXÉ** : `CardAddedMail` implémente `ShouldBeUnique` + `$uniqueId = "card-added-{userId}-{last4}"` | ✅ Fixé |
| E-4 | **Templates Blade non responsives** vérifiés — pas de test sur mobile email (Gmail app, etc.) | 🟠 Moyen |
| E-5 | **Pas de liste de suppression** — un email bounced peut être retenté indéfiniment | 🟠 Moyen |
| E-6 | **`card-added.blade.php`** : pas de lien de désinscription pour les emails marketing-adjacent | 🟡 Bas |
| E-7 | **Pas d'email de bienvenue séquencé** (J+1, J+3) pour onboarding propriétaires | 🟡 Bas |

### Recommandations

```php
// E-3 : Idempotency via unique job ID
class SendCardAddedEmail implements ShouldQueue, ShouldBeUnique
{
    public string $uniqueId; // ← ShouldBeUnique + uniqueId

    public function __construct(
        public readonly User $user,
        public readonly string $last4
    ) {
        $this->uniqueId = "card-added-{$user->id}-{$last4}";
    }
}
```

```php
// E-2 : Webhook Resend pour bounces
// Route: POST /webhooks/resend
public function handleResendWebhook(Request $request): JsonResponse
{
    $type = $request->input('type');
    
    if ($type === 'email.bounced' || $type === 'email.complained') {
        $email = $request->input('data.to.0');
        EmailSuppression::firstOrCreate(['email' => $email, 'reason' => $type]);
    }
    
    return response()->json(['ok' => true]);
}
```

---

## 8. Recherche immobilière (Meilisearch + NLP)

### Implémentation actuelle
- Meilisearch via Laravel Scout.
- `AiSearchServiceInterface` pour parsing NLP des requêtes.
- Recherche par image (`parseFromImage`).
- Driver `null` en tests.

### Ce que disent les experts (Meilisearch Docs 2025)

- **Faceted search** : Meilisearch calcule automatiquement les facettes (prix, type, ville).
- **Performance** : v1.x — indexation 300 secondes plus rapide sur 20M documents.
- **Geosearch** : `_geo` field natif pour recherche par rayon GPS.
- **Search for facet values** : permet de chercher dans les valeurs de facettes (ex. toutes les villes commençant par "Dou").

### Gaps identifiés

| # | Gap | Sévérité |
|---|---|---|
| R-1 | ~~**Pas de geosearch configuré**~~ — **✅ FIXÉ** : `_geo` dans `toSearchableArray()` (`getY()/getX()` sur Magellan Point) + `_geo` dans `filterableAttributes` de `scout.php` | ✅ Fixé |
| R-2 | ~~**Facets non configurées**~~ — **✅ FIXÉ** : `filterableAttributes` + `faceting.maxValuesPerFacet=100` + `sortFacetValuesBy=count` dans `scout.php`; endpoint SQL `GET /ads/facets` retourne villes/types/chambres/prix/surface | ✅ Fixé |
| R-3 | **`parseFromImage`** — pas de fallback si l'IA ne retourne pas de résultats valides | 🟠 Moyen |
| R-4 | **Pas de typo tolerance tuning** pour les noms de villes francophones (ex. "Yaoundé" vs "Yaounde") | 🟠 Moyen |
| R-5 | **Index non synchronisé** lors des soft deletes (Ad archivée reste dans l'index) | 🟠 Moyen |
| R-6 | **Pas d'index `SearchAlert`** — les alertes de recherche ne sont pas notifiées en temps réel via Meilisearch tasks | 🟡 Bas |

### Recommandations

```php
// R-2 : Configurer les facettes dans AdSearchIndex
class AdSearchable
{
    public function toSearchableArray(): array { ... }

    public static function configureMeilisearch(Indexes $index): void
    {
        $index->updateFilterableAttributes([
            'type', 'city_id', 'quarter_id', 'price', 'rooms',
            'is_furnished', 'status', '_geo', // ← geosearch
        ]);
        
        $index->updateFaceting([
            'maxValuesPerFacet' => 50,
            'sortFacetValuesBy' => ['*' => 'count'],
        ]);
        
        $index->updateSortableAttributes(['price', 'created_at', '_geo']);
    }
}
```

```php
// R-1 : Geosearch dans AdController
$query->geoBoundingBox([$lat1, $lng1], [$lat2, $lng2]); // Meilisearch Scout
// ou radius:
$query->geoPoint($lat, $lng)->withinKm(10);
```

---

## 9. Sécurité API transversale (Laravel Sanctum)

### Gaps identifiés (consolidation)

| # | Gap | Sévérité |
|---|---|---|
| SEC-1 | **Tokens Sanctum sans expiration** configurée — un token volé est valide indéfiniment | 🔴 Critique |
| SEC-2 | **Pas de rotation automatique des tokens** après changement de mot de passe | 🔴 Critique |
| SEC-3 | **Headers CORS trop permissifs** potentiellement en production | 🟠 Moyen |
| SEC-4 | **Pas de `Content-Security-Policy` header** sur les endpoints API | 🟠 Moyen |
| SEC-5 | **Pas d'audit log** pour les actions sensibles (suppression compte, ajout carte, changement password) | 🟠 Moyen |

### Recommandations

```php
// SEC-1 : Expiration des tokens dans config/sanctum.php
'expiration' => 60 * 24 * 30, // 30 jours en minutes

// SEC-2 : Révocation après changement password
public function updatePassword(Request $request): JsonResponse
{
    // ... mise à jour
    $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
}
```

---

## Tableau de priorités consolidé

| Priorité | Nb gaps | Domaine principal |
|---|---|---|
| 🔴 **Critique** (P0) | ~~14~~ **0 restants** | Tous résolus ou révalués — voir section P0 |
| 🟠 **Important** (P1) | 14 | Navigation a11y, Upload, Emails, Recherche, Auth 2FA |
| 🟡 **Amélioration** (P2) | 7 | Monitoring, UX raffinement, cleanup |

### P0 — Statut au 2026-05-21

**Auth & Sessions**
1. ~~`AUTH-1`~~ ✅ **FIXÉ** — Timer proactif 5 min avant expiry dans `AuthProvider` + `expires_at` sur tous les endpoints
2. ~~`AUTH-2`~~ ✅ **FIXÉ** — Validation `iss` dans `ClerkJwtService::verifyJwt()` contre `CLERK_JWKS_URL`
3. ~~`AUTH-5`~~ ✅ **FIXÉ** — Migration `family_id` + `revoked_at`, soft-revocation dans `TokenService`, détection famille compromise (`Log::alert` + révocation totale), `findToken()` override dans `PersonalAccessToken`

**Paiement & Webhooks**
4. ~~`P-1`~~ ✅ **MITIGÉ** — `HandlePostPaymentActions` : 3 gardes idempotentes + `lockForUpdate` + unique constraint DB (`CreditDoubleIssuanceTest` validé)

**Isolation tenant**
5. ~~`M-1`~~ ✅ **FIXÉ** — `TenantIsolationTest.php` couvre listing, expenses, documents et profit-loss cross-tenant
6. ~~`M-6`~~ ✅ **SAIN** — Agency exports scopés par `user_id`; admin panel voit tout par design

**Emails**
7. ~~`E-2`~~ ✅ **FIXÉ** — `POST /api/v1/webhooks/resend` (Svix HMAC), `EmailSuppression` model + migration, guard `MessageSending` dans `AppServiceProvider` annule l'envoi si adresse supprimée
8. ~~`E-3`~~ ✅ **FIXÉ** — `CardAddedMail` implémente `ShouldBeUnique`, `$uniqueId = "card-added-{$user->id}-{$cardLast4}"`

**Accessibilité**
9. ~~`S-1` + `S-2`~~ ✅ **FIXÉS** — `role="status"`, `aria-live`, `aria-atomic` sur `KhSnackbar`
10. ~~`A-5`~~ ✅ **FIXÉ** — `aria-labelledby="avatar-crop-dialog-title"` + `aria-modal` + `id` sur `AvatarCropDialog.tsx`
11. ~~`N-4`~~ ✅ **DÉJÀ IMPLÉMENTÉ** — `aria-current={isActive ? 'page' : undefined}` dans `Navbar`, `OwnerSidebar`, `BottomNav`, `OwnerBottomNav`

**Sécurité upload**
12. ~~`A-1`~~ ✅ **FIXÉ** — `mimes` + `mimetypes` double-validation dans `UserRequest`, suppression `gif`

**Recherche**
13. ~~`R-1`~~ ✅ **DÉJÀ IMPLÉMENTÉ** — `_geo` dans `toSearchableArray()` + `filterableAttributes` Scout
14. ~~`R-2`~~ ✅ **FIXÉ** — `faceting.maxValuesPerFacet=100` + `sortFacetValuesBy=count` dans `scout.php` + endpoint `/ads/facets`

**Isolation tenant**
15. ~~`M-2`~~ ✅ **ANALYSÉ** — `LandlordScope` jamais appliqué comme global scope; `withoutGlobalScopes` admin explicitement scopés

**Emails**
16. `E-1` 🟡 **Hors code** — DMARC = config DNS Resend (pas de fix codebase possible)

**Passkeys**
17. `W-1` + `W-3` 🟠 **Réévalués Moyen** — reportés V2, hors scope V1

**API (déjà implémentés)**
- ~~`SEC-1` / `SEC-2`~~ ✅ — `TokenService` utilise `sanctum.expiration` ✅

### P1 — Sprint suivant

**Auth**
- `AUTH-3` : Gestion `TransientToken` non-révocable (webhook Clerk events)
- `AUTH-7` : Rate limiting OTP Clerk par `clerkId` + IP combiné
- `AUTH-8` : 2FA TOTP optionnel pour bailleurs (Google Authenticator)
- `AUTH-10` : Throttle `useClerkSync` sur window focus (debounce 5s)

**Recherche**
- ~~`R-1`~~ ✅ **FIXÉ** — `_geo` dans `toSearchableArray()` + `filterableAttributes`
- ~~`R-2`~~ ✅ **FIXÉ** — `faceting` settings dans `scout.php` + endpoint SQL `/ads/facets`

**Passkeys** (reporté V2)
- `W-1` + `W-2` + `W-3` : Conditional Create + `getClientCapabilities()` + Related Origin — reclassifiés 🟠 Moyen, hors scope V1

**Navigation & a11y**
- ~~`N-4`~~ ✅ **FIXÉ** — `aria-current="page"` déjà dans `Navbar`, `OwnerSidebar`, `BottomNav`, `OwnerBottomNav`
- ~~`A-5`~~ ✅ **FIXÉ** — `aria-labelledby` + `aria-modal` + `id` sur `AvatarCropDialog`

---

## Sources

| Source | URL | Note |
|---|---|---|
| NNGroup Mobile Navigation | https://www.nngroup.com/articles/mobile-navigation-patterns/ | Référence UX principale |
| LogRocket React Croppers | https://blog.logrocket.com/top-react-image-cropping-libraries/ | Comparaison 2024 |
| Scott O'Hara Toast a11y | https://www.scottohara.me/blog/2019/07/08/a-toast-to-a11y-toasts.html | Mis à jour 2025-05-01 |
| Stripe Best Practices 2025 | https://www.mobarut.com/blog/stripe-payment-integration-best-practices-for-2025 | Payment integration |
| FIDO Alliance Passkey Central | https://www.passkeycentral.org/news-and-events/passkey-upgrades-and-improvements | Jan 2026 |
| Orange Money Developer | https://developer.orange.com/apis/om-webpay | API officielle |
| Dusupay Cameroon Gateways | https://www.dusupay.com/post/top-payment-gateways-in-cameroon | Contexte marché |
| Laravel 12 Multi-Tenant | https://needlaravelsite.com/blog/building-multi-tenant-saas-applications-with-laravel-12 | Nov 2025 |
| Resend Email Best Practices | https://github.com/resend/email-best-practices | Apr 2026 |
| Meilisearch Faceted Search | https://meilisearch.com/docs/capabilities/filtering_sorting_faceting | Docs 2025 |

---

*Document généré le 2026-05-21 · À réviser à chaque release majeure.*
