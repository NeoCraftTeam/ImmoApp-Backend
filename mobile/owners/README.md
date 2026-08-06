# KeyHome — Owners (mobile)

Application mobile **bailleur / agent** de KeyHome (Expo + React Native +
Tamagui + TanStack Query). Pendant que l'app `visitors/` est tournée vers
la consultation d'annonces (thème crimson), l'app `owners/` est l'espace
de gestion professionnel (thème **teal** `#0D9488`) : publier et gérer ses
annonces, suivre ses statistiques, gérer ses visites, locataires et baux,
et imprimer des pancartes avec QR code.

## Démarrage

```bash
cd mobile/owners
npm install
cp .env.example .env   # pointez EXPO_PUBLIC_API_BASE_URL vers votre backend
npm start              # puis 'i' (iOS), 'a' (Android), ou scan du QR
npm run typecheck      # tsc --noEmit
```

> Les fichiers `assets/*.png` fournis sont des placeholders 1×1. Remplacez
> `icon.png`, `adaptive-icon.png`, `splash-icon.png` et `favicon.png` par
> les visuels de marque avant tout build de production (EAS).

## Architecture

```
app/                         Routes (expo-router, typed routes)
  _layout.tsx                Providers + AuthGate (app auth-required)
  index.tsx                  Gate onboarding → login / dashboard
  onboarding.tsx
  (auth)/                    login · register (agent) · forgot · reset · verify-otp
  (tabs)/                    dashboard · ads · viewings · account
  ads/
    new.tsx                  Création (AdForm)
    [id]/index.tsx           Détail + actions (boost, statut, visibilité, …)
    [id]/edit.tsx            Édition (AdForm)
    [id]/placarde.tsx        Pancarte PDF (imprimer/partager) + QR code
  profile.tsx · subscriptions.tsx · analytics.tsx · tenants.tsx
  lease-contracts.tsx · reviews.tsx · parametres.tsx · aide.tsx

src/
  api/        client axios (Bearer Sanctum) + registre d'endpoints (/api/v1)
  auth/       SessionProvider (token SecureStore) + storage hooks
  components/ ScreenHeader, StatCard, StatusBadge, EmptyState, OwnerAdCard,
              ads/AdForm, ads/PickerField, ads/ImagePickerGrid, ads/MapPicker, ads/BoostSheet
  hooks/      useMyAds, useAd, useAdMutations, useOwnerStats, useBoost, useViewings,
              useReference, useTenants, useLeases, useSubscriptions, useAnalytics, …
  i18n/       fr (défaut) + en (fallback)
  theme/      tokens (palette teal + AD_STATUS_META)
  types/      ad, user, owner
  utils/      format (FCFA/dates) + documents (download/print/share PDF authentifié)
```

## Points clés

- **Auth requise** : `AuthGate` (dans `app/_layout.tsx`) redirige toute
  navigation non authentifiée vers `(auth)/login`. Inscription via
  `/auth/registerAgent`.
- **Brouillons d'annonce** : `AdForm` enregistre un brouillon
  (`is_draft`), avec autosave différé (`PATCH /ads/{id}/autosave`) en mode
  édition d'un brouillon, et publication via `POST /ads/{id}/publish`.
- **Pancartes / impression** : `app/ads/[id]/placarde.tsx` télécharge le
  PDF A5 authentifié (`GET /my/ads/{id}/placarde`) via
  `utils/documents.ts` puis l'envoie au dialogue d'impression / partage
  natif. Onglet QR code (data-URI) inclus.
- **Boost** : `BoostSheet` liste les packs, vérifie le solde de crédits et
  applique le boost (`POST /my/ads/{id}/boost`).
- **Token isolé** du visiteur (`keyhome.owners.session.token`) — les deux
  apps cohabitent sur un même appareil avec des sessions distinctes.
- **Cache chat chiffré on-device (modèle WhatsApp)** : le cache TanStack
  Query (conversations + fils) est persisté dans AsyncStorage **chiffré en
  AES-256** (`src/lib/secure-persister.ts`, `crypto-es`), clé dans
  `expo-secure-store` (`WHEN_UNLOCKED_THIS_DEVICE_ONLY`). Inbox et fils
  s'affichent instantanément au cold-start, puis resynchronisent en
  arrière-plan. Snapshot corrompu/legacy purgé silencieusement ; buster
  `kh-owners-v2-encrypted`. npm reste le package manager canonique —
  regénérer le lock avec `npm install --package-lock-only --legacy-peer-deps`
  après tout changement de `package.json`.

L'équivalent web est `keyhome-frontend-next/src/app/(owner)/…`.
