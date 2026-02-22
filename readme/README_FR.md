# KeyHome — Documentation Technique Complète (Français)

> **Généré le :** 22 février 2026 | **Stack :** Laravel 12 · Next.js 16 · Expo 54 · PostgreSQL/PostGIS · MeiliSearch  
> **Racine du dépôt :** `/Users/feze/Developer/Laravel/ImmoApp-Backend`

---

## 1. IDENTITÉ DU PROJET

### Résumé en une phrase
**KeyHome est une plateforme SaaS immobilière full-stack pour l'Afrique francophone subsaharienne permettant aux propriétaires et agences de publier, promouvoir et monétiser leurs annonces, tout en offrant aux chercheurs une expérience de recherche personnalisée par IA.**

### Résumé en un paragraphe
KeyHome se compose d'une API REST backend Laravel 12, d'un frontend web Next.js 16 authentifié via Clerk, de trois panneaux d'administration Filament 4 (Admin, Agency, Bailleur/Owner), et de deux shells mobiles Expo React Native (un par panneau). Les annonces sont indexées dans MeiliSearch pour la recherche full-text et via PostGIS pour la géolocalisation. La monétisation s'effectue via le paiement à l'acte pour débloquer les coordonnées de contact (FedaPay, XOF), les abonnements agence et le boost d'annonces. Un moteur de recommandation à scoring pondéré personnalise le fil d'actualité. L'ensemble tourne sur Docker Compose avec le reverse proxy Traefik et un profil de monitoring Prometheus/Grafana optionnel.

### Utilisateurs cibles
| Type d'utilisateur | Point d'entrée |
|-------------------|----------------|
| Chercheurs de biens | App web Next.js (`keyhome-frontend-next`) |
| Bailleurs individuels | Panneau Filament `/owner` + shell Expo `mobile/bailleur` |
| Agences immobilières | Panneau Filament `/agency` + shell Expo `mobile/agency` |
| Administrateurs plateforme | Panneau Filament `/admin` |

### Problème résolu
Des portails d'annonces fragmentés et de faible qualité sur le marché africain → KeyHome propose des annonces vérifiées avec correspondances recommandées par IA, contacts protégés par paywall (anti-spam) et un back-office professionnel pour les agents.

### Type d'architecture
**Monorepo** hébergeant quatre applications distinctes partageant un seul backend API :
- API REST Laravel (monolithe)
- Next.js 16 SPA/SSR (frontend web)
- Deux shells Expo WebView (iOS/Android)
- Trois panneaux Filament (back-offices web)

---

## 2. INVENTAIRE DE LA STACK TECHNOLOGIQUE

### Backend (`/`)
| Outil | Version | Statut |
|-------|---------|--------|
| PHP | ^8.4 | ✅ Actuel |
| Laravel | ^12.0 | ✅ Actuel |
| Filament | ~4.0 | ✅ Actuel |
| Laravel Sanctum | ^4.0 | ✅ Actuel |
| Laravel Scout + MeiliSearch | ^10.21 + ^1.16 | ✅ Actuel |
| Laravel Socialite | ^5.24 | ✅ Actuel |
| Laravel Telescope | ^5.15 | ✅ Actuel |
| Laravel Pulse | * | ✅ Actuel |
| Laravel Nightwatch | ^1.21 | ✅ Actuel |
| Spatie MediaLibrary | ^11.14 | ✅ Actuel |
| Spatie Activitylog | ^4.11 | ✅ Actuel |
| Clickbar Magellan (PostGIS) | ^2.0 | ✅ Actuel |
| FedaPay PHP SDK | ^0.4.7 | ✅ Actuel |
| Sentry Laravel | ^4.20 | ✅ Actuel |
| Darkaonline L5-Swagger | ^9.0 | ✅ Actuel |
| Filament Socialite | ^3.1 | ✅ Actuel |
| Pest PHP | ^4.1 | ✅ Actuel |
| Larastan | ^3.0 | ✅ Actuel |
| Rector | ^2.1 | ✅ Actuel |
| PHP Pint | ^1.13 | ✅ Actuel |

### Frontend (`/keyhome-frontend-next`)
| Outil | Version | Statut |
|-------|---------|--------|
| Next.js | 16.1.6 | ✅ Actuel |
| React | 19.2.3 | ✅ Actuel |
| TypeScript | ^5 | ✅ Actuel |
| Clerk (Next.js) | ^6.38.1 | ✅ Actuel |
| MUI (Material UI) | ^7.3.7 | ✅ Actuel |
| TanStack React Query | ^5.90.21 | ✅ Actuel |
| Mapbox GL | ^3.18.1 | ✅ Actuel |
| React Hook Form + Zod | ^7 + ^4 | ✅ Actuel |
| Axios | ^1.13.5 | ✅ Actuel |
| Tailwind CSS | ^4 | ✅ Actuel |

### Apps mobiles (`/mobile/agency` & `/mobile/bailleur`)
| Outil | Version | Statut |
|-------|---------|--------|
| Expo SDK | ~54.0.33 | ✅ Actuel |
| React Native | 0.81.5 | ✅ Actuel |
| react-native-webview | 13.15.0 | ✅ Actuel |
| expo-notifications | ~0.32.16 | ✅ Actuel |
| expo-image-picker | ~17.0.10 | ✅ Actuel |
| expo-location | ~19.0.8 | ✅ Actuel |
| expo-haptics | ~15.0.8 | ✅ Actuel |

### Infrastructure
| Outil | Version |
|-------|---------|
| Docker / Docker Compose | Stable récent |
| Nginx | Alpine |
| PostgreSQL + PostGIS | 15-3.3-alpine |
| Redis | Alpine |
| MeiliSearch | v1.10 |
| Traefik | Externe (reverse proxy) |
| Prometheus + Grafana | (profil monitoring optionnel) |
| GitLab CI/CD | `.gitlab-ci.yml` |

### Gestionnaires de paquets
- **Backend :** Composer 2
- **Frontend :** pnpm (principal), npm
- **Mobile :** npm

### Variables d'environnement

**Backend (`.env`)**
```
APP_NAME, APP_KEY, APP_ENV, APP_URL, APP_DEBUG
DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
REDIS_HOST, REDIS_PORT
MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
MEILISEARCH_HOST, MEILISEARCH_KEY
FEDAPAY_PUBLIC_KEY, FEDAPAY_SECRET_KEY, FEDAPAY_ENVIRONMENT
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
FACEBOOK_CLIENT_ID, FACEBOOK_CLIENT_SECRET, FACEBOOK_REDIRECT_URI
APPLE_CLIENT_ID, APPLE_CLIENT_SECRET, APPLE_REDIRECT_URI
CLERK_PUBLISHABLE_KEY, CLERK_SECRET_KEY, CLERK_JWKS_URL, CLERK_WEBHOOK_SECRET
SENTRY_LARAVEL_DSN, NIGHTWATCH_TOKEN
FRONTEND_URL, EMAIL_VERIFY_CALLBACK
SANCTUM_STATEFUL_DOMAINS, SANCTUM_TOKEN_PREFIX=kh_
TRUSTED_PROXIES
```

**Frontend (`.env.local`)**
```
NEXT_PUBLIC_API_URL, NEXT_PUBLIC_MAPBOX_TOKEN
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY, CLERK_SECRET_KEY
NEXT_PUBLIC_CLERK_SIGN_IN_URL=/login, NEXT_PUBLIC_CLERK_SIGN_UP_URL=/register
```

**Mobile (`.env`)**
```
EXPO_PUBLIC_BASE_URL  (ex: https://api.keyhome.neocraft.dev/agency)
```

---

## 3. CARTE D'ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────┐
│                        COUCHE CLIENT                                │
│                                                                     │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Web Next.js 16 │  │ App Expo Agency  │  │ App Expo Bailleur│  │
│  │  (Auth Clerk)   │  │ (Bridge WebView) │  │ (Bridge WebView) │  │
│  │  port 3000      │  │ → panneau /agency│  │ → panneau /owner │  │
│  └────────┬────────┘  └────────┬─────────┘  └────────┬─────────┘  │
└───────────┼────────────────────┼─────────────────────┼────────────┘
            │ HTTPS              │ HTTPS               │ HTTPS
            ▼                   ▼                     ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   REVERSE PROXY TRAEFIK                             │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
            ┌────────────────┬┴───────────────┐
            │                │                │
            ▼                ▼                ▼
     /api/v1/*           /admin           /agency + /owner
     └──────┬────────────────┴────────────────┘
            │
    ┌───────▼───────┐
    │  PHP-FPM App  │
    │  (Laravel 12) │
    └───────┬───────┘
            │
  ┌─────────┼──────────┐
  │         │          │
  ▼         ▼          ▼
PostgreSQL MeiliSearch Redis
+PostGIS   v1.10      (cache+queue)
  │
  ├──► FedaPay (paiements XOF)
  ├──► Clerk (auth JWT + webhooks)
  ├──► Google / Facebook / Apple OAuth
  ├──► Sentry (suivi d'erreurs)
  └──► Nightwatch (agent monitoring)
```

### Points d'entrée
| Surface | Point d'entrée |
|---------|----------------|
| API REST | `routes/api.php` → `public/index.php` |
| Filament Admin | `app/Providers/Filament/AdminPanelProvider.php` → `/admin` |
| Filament Agency | `app/Providers/Filament/AgencyPanelProvider.php` → `/agency` |
| Filament Bailleur | `app/Providers/Filament/BailleurPanelProvider.php` → `/owner` |
| Next.js | `keyhome-frontend-next/src/app/layout.tsx` |
| Mobile Agency | `mobile/agency/index.js` → `App.js` |
| Mobile Bailleur | `mobile/bailleur/index.js` → `App.js` |

### Flux de données (un utilisateur consulte des annonces)
```
Utilisateur ouvre Next.js → session Clerk vérifiée → middleware.ts injecte token
→ GET /api/v1/ads (Axios, Bearer token)
→ Laravel AdController@index (middleware auth optionnel)
→ Requête Eloquent sur table `ad` (PostgreSQL)
→ MeiliSearch pour la recherche full-text via Scout
→ Réponse JSON AdResource
→ TanStack Query met en cache → React affiche les cartes d'annonces
```

### Modèle d'authentification et d'autorisation
| Couche | Mécanisme |
|--------|-----------|
| Frontend web | Clerk (JWT) → échangé contre token Sanctum via `/auth/clerk/exchange` |
| Panneaux Filament | Session (cookie), 2FA (TOTP + Email) |
| OAuth mobile | Expo `expo-auth-session` → OAuth natif Google → `/auth/oauth/google` |
| Autorisation API | Laravel Policies par modèle (Ad, Agency, Payment, Subscription…) |
| Limitation de débit | Middleware throttle sur tous les endpoints sensibles (5–60 req/min) |

---

## 4. STRUCTURE DES RÉPERTOIRES

```
ImmoApp-Backend/
├── app/                     # Code applicatif Laravel
│   ├── Actions/             # Classes d'actions mono-responsabilité
│   ├── Console/             # Commandes Artisan et planificateur
│   ├── Enums/               # Enums PHP 8.1 (AdStatus, UserRole, PaymentType…)
│   ├── Exceptions/          # Exceptions personnalisées (InvalidStatusTransition)
│   ├── Filament/            # Ressources, pages, widgets pour 3 panneaux
│   │   ├── Admin/           # 46 ressources + 9 widgets
│   │   ├── Agency/          # Ressources côté agence
│   │   ├── Bailleur/        # Ressources côté bailleur
│   │   ├── Exports/         # Classes d'export CSV/Excel
│   │   └── Imports/         # Classes d'import en masse
│   ├── Http/
│   │   ├── Controllers/Api/V1/  # 14 contrôleurs REST
│   │   ├── Middleware/          # 5 middlewares personnalisés
│   │   ├── Requests/            # 15 validateurs FormRequest
│   │   └── Resources/           # 11 ressources API (transformateurs JSON)
│   ├── Mail/                # 20 classes Mailable
│   ├── Models/              # 17 modèles Eloquent
│   ├── Notifications/       # 4 classes de notification
│   ├── Observers/           # 2 observers de modèles
│   ├── Policies/            # 9 politiques d'autorisation
│   ├── Providers/           # Fournisseurs de services + 4 providers Filament
│   ├── Services/            # 8 services (RecommendationEngine, FedaPay…)
│   └── Swagger/             # Annotations OpenAPI
├── database/
│   ├── migrations/          # 57 fichiers de migration (août 2025 – fév. 2026)
│   ├── seeders/             # 10 seeders
│   └── factories/           # 10 factories de modèles
├── routes/
│   ├── api.php              # Toutes les routes API (v1), 250 lignes
│   └── web.php              # Routes Filament + health-check
├── resources/
│   └── js/filament-native-bridge.js  # Bridge JS injecté dans les WebViews
├── keyhome-frontend-next/   # Frontend web Next.js 16
│   └── src/
│       ├── app/             # Pages App Router Next.js
│       ├── components/      # Composants React réutilisables
│       ├── services/        # Services API clients (Axios)
│       ├── lib/             # Utilitaires et helpers
│       ├── providers/       # Context providers React
│       └── types/           # Types TypeScript
├── mobile/
│   ├── agency/              # App Expo pour le panneau agence
│   └── bailleur/            # App Expo pour le panneau bailleur
├── tests/
│   ├── Feature/             # 25 tests feature/intégration
│   └── Unit/                # 3 tests unitaires
├── .docker/                 # Configurations Docker (nginx, monitoring)
├── .gitlab-ci.yml           # Pipeline CI/CD (lint, test, build, deploy)
├── docker-compose.yml       # 7 services core + 6 monitoring
└── Dockerfile               # Image PHP-FPM
```

### Fichiers les plus importants
| Fichier | Rôle |
|---------|------|
| `app/Models/Ad.php` | Modèle central d'annonce : FSM stateful, boost, géo, médias, indexation Scout |
| `app/Models/User.php` | Utilisateur multi-rôle : contrats Filament, 2FA, OAuth, soft-delete |
| `app/Services/RecommendationEngine.php` | Moteur IA à scoring pondéré avec décroissance temporelle |
| `app/Services/FedaPayService.php` | Abstraction passerelle de paiement (unlock + abonnement) |
| `routes/api.php` | Surface API complète, versionnée, rate-limitée |
| `docker-compose.yml` | Définition complète de l'infrastructure |
| `.gitlab-ci.yml` | Pipeline CI/CD |
| `resources/js/filament-native-bridge.js` | JS injecté dans les WebViews mobiles |
| `keyhome-frontend-next/src/middleware.ts` | Middleware auth Next.js (Clerk) |

---

## 5. BASE DE DONNÉES & MODÈLE DE DONNÉES

**ORM :** Eloquent (Laravel)  
**Driver :** PostgreSQL 15 + PostGIS 3.3  
**Total migrations :** 57  
**Stratégie :** Migrations séquentielles horodatées ; pas de `down()` sur les migrations tardives

### Tables (~24 au total)

| Table | Colonnes clés | Relations |
|-------|---------------|-----------|
| `users` | id(uuid), firstname, lastname, email, role, type, agency_id, clerk_id, google_id… | → agencies, ads, payments, reviews |
| `ad` | id(uuid), title, slug, price, surface_area, bedrooms, location(Point), status, is_boosted… | → users, quarters, ad_type, media |
| `ad_type` | id, name | ← ads |
| `city` | id, name | ← quarters, users |
| `quarter` | id, name, city_id | ← ads |
| `payments` | id(uuid), user_id, ad_id, amount, type, status, fedapay_transaction_id | → users, ads |
| `invoices` | id, payment_id, amount, issued_at | → payments |
| `subscriptions` | id, agency_id, plan_id, status, billing_period, starts_at, ends_at | → agencies, plans |
| `subscription_plans` | id, name, price_monthly, price_yearly, features(json) | ← subscriptions |
| `agencies` | id, name, description, logo | ← users, ads |
| `reviews` | id, user_id, ad_id, rating, comment | → users, ads |
| `ad_interactions` | id, user_id, ad_id, type(view/favorite/share…), created_at | → users, ads |
| `unlocked_ads` | id, user_id, ad_id | → users, ads |
| `property_attributes` | id, name, label, icon | (table de référence globale) |
| `settings` | key, value | (store clé-valeur) |
| `activity_log` | id, log_name, description, subject_type, causer_type… | (Spatie activitylog) |
| `media` | id, model_type, model_id, collection_name, file_name… | (Spatie MediaLibrary) |
| `notifications` | id, type, notifiable_id, data, read_at | (Notifications Laravel) |
| `personal_access_tokens` | id, tokenable_id, name, token, abilities | (Sanctum) |
| `socialite_users` | user_id, provider, provider_user_id | → users |
| `telescope_entries` | (debug Laravel Telescope) | — |
| `pulse_*` | (métriques Laravel Pulse) | — |
| `imports` / `exports` | (jobs Filament) | — |
| `cache` / `jobs` | Tables système Laravel | — |

### Index et contraintes notables
- Index unique sur `ad_interactions(user_id, ad_id, type)` (déduplication)
- Index composite sur `ad(status, is_visible, available_from, available_to)` (requêtes filtrées)
- Index spatial PostGIS sur `ad.location`
- Index de soft-delete sur `users` et `ad`

---

## 6. SURFACE API

**URL de base :** `/api/v1`  
**Auth :** Token Bearer (Sanctum) — `Authorization: Bearer kh_*`  
**Total endpoints :** ~55

### Authentification (`/auth`)
| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| POST | `/auth/registerCustomer` | Non | Inscription compte client |
| POST | `/auth/registerAgent` | Non | Inscription bailleur/agent agence |
| POST | `/auth/login` | Non | Connexion email/mot de passe → token Sanctum |
| POST | `/auth/logout` | Oui | Révoquer le token actuel |
| POST | `/auth/refresh` | Oui | Actualiser le token |
| GET | `/auth/me` | Oui | Profil de l'utilisateur authentifié |
| POST | `/auth/forgot-password` | Non | Envoyer email réinitialisation |
| POST | `/auth/reset-password` | Non | Réinitialiser le mot de passe |
| GET | `/auth/email/verify/{id}/{hash}` | Non | Vérifier l'adresse email |
| POST | `/auth/update-password` | Oui | Changer le mot de passe |
| POST | `/auth/clerk/exchange` | Non | Échanger JWT Clerk contre token Sanctum |
| POST | `/auth/clerk/verify-otp` | Non | Vérifier OTP Clerk |
| POST | `/auth/clerk/complete-profile` | Non | Finaliser profil après inscription Clerk |
| POST | `/auth/oauth/{provider}` | Non | Échange token OAuth (google/facebook/apple) |
| GET | `/auth/oauth/{provider}/redirect` | Non | Redirection OAuth |
| GET | `/auth/oauth/{provider}/callback` | Non | Callback OAuth |
| POST | `/auth/oauth/{provider}/link` | Oui | Lier un provider OAuth au compte |
| DELETE | `/auth/oauth/{provider}/unlink` | Oui | Délier un provider OAuth |

### Annonces
| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| GET | `/ads` | Optionnel | Lister les annonces disponibles |
| GET | `/ads/search` | Optionnel | Recherche full-text (MeiliSearch) |
| GET | `/ads/autocomplete` | Optionnel | Auto-complétion de recherche |
| GET | `/ads/facets` | Optionnel | Facettes pour filtres dynamiques |
| GET | `/ads/nearby` | Optionnel | Recherche géographique par coordonnées |
| GET | `/ads/{id}` | Optionnel | Détail d'une annonce |
| POST | `/ads` | Oui | Créer une nouvelle annonce |
| PUT | `/ads/{ad}` | Oui | Mettre à jour une annonce |
| DELETE | `/ads/{id}` | Oui | Supprimer une annonce (soft) |
| POST | `/ads/{ad}/toggle-visibility` | Oui | Afficher/masquer une annonce |
| POST | `/ads/{ad}/set-status` | Oui | Changer le statut de l'annonce |
| POST | `/ads/{ad}/set-availability` | Oui | Définir les dates de disponibilité |

### Interactions & Analytique
| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| POST | `/ads/{ad}/view` | Oui | Tracker une vue |
| POST | `/ads/{ad}/favorite` | Oui | Basculer favori |
| POST | `/ads/{ad}/impression` | Oui | Tracker une impression |
| POST | `/ads/{ad}/share` | Oui | Tracker un partage |
| POST | `/ads/{ad}/contact-click` | Oui | Tracker un clic contact |
| POST | `/ads/{ad}/phone-click` | Oui | Tracker un clic téléphone |
| GET | `/my/favorites` | Oui | Annonces favorites de l'utilisateur |
| GET | `/my/unlocked-ads` | Oui | Annonces débloquées par l'utilisateur |
| GET | `/my/ads/analytics` | Oui | Dashboard analytique global |
| GET | `/my/ads/{ad}/analytics` | Oui | Analytique par annonce |

### Paiements & Abonnements
| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| POST | `/payments/initialize/{ad}` | Oui | Créer transaction FedaPay (déblocage) |
| POST | `/payments/verify/{ad}` | Oui | Vérifier paiement de déblocage |
| POST | `/payments/webhook` | Non | Récepteur webhook FedaPay |
| GET | `/payments/callback` | Non | Callback redirection FedaPay |
| GET | `/payments/unlock-price` | Non | Prix de déblocage actuel (settings) |
| GET | `/subscriptions/plans` | Non | Lister les plans d'abonnement |
| GET | `/subscriptions/current` | Oui | Abonnement actuel de l'agence |
| POST | `/subscriptions/subscribe` | Oui | Souscrire à un plan |
| POST | `/subscriptions/cancel` | Oui | Annuler l'abonnement |
| GET | `/subscriptions/history` | Oui | Historique des abonnements |

### Autres endpoints
| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| GET | `/recommendations` | Oui | Recommandations IA personnalisées |
| GET | `/cities` | Non | Lister les villes |
| GET | `/quarters` | Non | Lister les quartiers |
| GET | `/agencies` | Non | Lister les agences |
| GET | `/notifications` | Oui | Notifications de l'utilisateur |
| POST | `/notifications/read-all` | Oui | Marquer tout comme lu |
| GET | `/ads/{ad}/reviews` | Non | Avis sur une annonce |
| POST | `/reviews` | Oui | Soumettre un avis |
| GET | `/property-attributes` | Non | Attributs de bien disponibles |
| POST | `/clerk/webhook` | Non | Webhook Clerk |

---

## 7. STRUCTURE FRONTEND

### Next.js (`/keyhome-frontend-next/src`)

#### Pages (App Router)
| Route | Page | Auth requise |
|-------|------|--------------|
| `/` | Landing / redirection | Non |
| `/login` | Connexion Clerk | Non |
| `/register` | Inscription Clerk | Non |
| `/forgot-password` | Demande réinitialisation | Non |
| `/reset-password` | Formulaire nouveau mot de passe | Non |
| `/verify-email` | Portail vérification email | Non |
| `/verify-otp` | Vérification OTP | Non |
| `/complete-profile` | Formulaire profil post-OAuth | Non |
| `/auth/callback` | Callback SSO Clerk | Non |
| `/home` | Fil d'actualité + recommendations | Oui |
| `/ads/[id]/[slug]` | Détail annonce, contact, avis | Oui |
| `/search` | Résultats de recherche full-text | Oui |
| `/nearby` | Recherche géographique carte | Oui |
| `/profile` | Gestion du profil | Oui |
| `/payments` | Historique des paiements | Oui |
| `/payment-success` | Confirmation post-paiement | Oui |
| `/conditions` | Conditions d'utilisation | Non |
| `/confidentialite` | Politique de confidentialité | Non |

#### Gestion d'état
- **État serveur :** TanStack React Query (tous les appels API, cache, invalidation)
- **État authentification :** Clerk (`useUser`, `useAuth`)
- **État formulaires :** React Hook Form + validation Zod
- **État UI local :** `useState` / `useReducer` (pas de Redux/Zustand)

#### Composants clés (`/src/components`)
- `ads/` — Cartes d'annonces, détail, liste
- `auth/` — Formulaires connexion/inscription, wrapper route protégée
- `layout/` — Navbar, sidebar, footer
- `maps/` — Carte Mapbox avec géo-recherche
- `reviews/` — Liste et formulaire d'avis
- `ui/` — Primitives du système de design (boutons, inputs…)
- `ErrorBoundary.tsx` — Frontière d'erreur React globale

#### Approche de style
- **Tailwind CSS v4** (classes utilitaires)
- **MUI v7** (bibliothèque de composants : inputs, modals, snackbars)
- **Emotion** (CSS-in-JS, requis par MUI)
- **globals.css** — resets globaux et propriétés personnalisées

#### Internationalisation
Aucune bibliothèque i18n installée. L'interface est en français ; pas de support multilingue.

### Apps mobiles (`/mobile/agency` & `/mobile/bailleur`)
Les deux apps suivent le **pattern WebView-bridge** :
- Un unique `App.js` affiche une `react-native-webview` pointant vers l'URL du panneau Filament correspondant
- Un bridge JavaScript (`INJECTED_JS`) est injecté avant le chargement, exposant `window.KeyHomeBridge` : `pickImage`, `takePhoto`, `getLocation`, `registerPush`, `signInGoogle`
- `NativeService.js` traite les messages entrants de la WebView et dispatche vers les capacités natives (caméra, localisation, notifications Expo)

| Fonctionnalité | App Agency | App Bailleur |
|----------------|------------|--------------|
| Couleur principale | Bleu `#2563eb` | Vert `#10b981` |
| URL panneau | `/agency` | `/owner` |
| Retour Android | ✅ | ✅ |
| Bannière hors-ligne | ✅ | ✅ |
| Retour haptique | ✅ | ✅ |
| Écran erreur/retry | ✅ | ✅ |
| Notifications push | ✅ (Expo) | ✅ (Expo) |

---

## 8. TESTS

**Framework :** Pest PHP v4 (backend)  
**Tests frontend :** Aucun (pas de Jest/Vitest/Playwright)  
**Tests mobile :** Aucun

### Inventaire des tests backend
| Fichier de test | Type | Domaine couvert |
|-----------------|------|-----------------|
| `AuthTest.php` | Feature | Connexion, déconnexion, token |
| `AuthEndpointsTest.php` | Feature | Suite complète endpoints auth |
| `OAuthAuthenticationTest.php` | Feature | OAuth Google/Facebook/Apple + Clerk |
| `ClerkExchangeTest.php` | Feature | Échange JWT Clerk → Sanctum |
| `EmailVerificationFlowTest.php` | Feature | Cycle de vie vérification email |
| `PasswordResetTest.php` | Feature | Mot de passe oublié/réinitialisation |
| `AdCrudTest.php` | Feature | Créer/lire/mettre à jour/supprimer annonces |
| `AdStatusTransitionTest.php` | Feature | Validation transitions FSM |
| `AdPolicyTest.php` | Feature | Politiques d'autorisation |
| `AdAnalyticsTest.php` | Feature | Analytics tableau de bord |
| `PaymentTest.php` & `PaymentFlowTest.php` | Feature | Paiement FedaPay complet |
| `SubscriptionTest.php` | Feature | Souscrire, annuler, historique |
| `RecommendationTest.php` | Feature | Cold-start + personnalisé |
| `BailleurIsolationTest.php` | Feature | Isolation des données tenant |
| `CriticalSecurityTest.php` | Feature | Contournement auth, injection SQL… |
| `SecurityTest.php` | Feature | Rate limiting, CSRF |
| `PerformanceTest.php` | Feature | Comptage requêtes / N+1 |
| `MailTemplatesTest.php` | Feature | Rendu des emails |
| `MfaConfigurationTest.php` | Feature | Configuration 2FA |
| `OAuthAuthenticationTest.php` | Feature | Flux complet OAuth |
| `Unit/` (3 fichiers) | Unitaire | Logique modèles, helpers |

**Total :** ~28 fichiers, ~150–200 assertions estimées  
**Couverture estimée :** ~45–55 % (solide sur auth + paiements, faible sur UI Filament + frontend)

### Zones critiques non testées
1. Panneaux Filament (pas de tests UI/navigateur)
2. Frontend Next.js (zéro test)
3. Bridge WebView des apps mobiles
4. Vérification signature webhook FedaPay
5. Synchronisation d'indexation MeiliSearch

---

## 9. QUALITÉ DU CODE

### Backend
- **Types stricts :** `declare(strict_types=1)` dans chaque fichier ✅
- **PHPStan :** configuré via `phpstan.neon` (Larastan ~niveau 5–6)
- **Formatage :** PHP Pint (PSR-12 + preset Laravel) — appliqué en CI
- **Refactoring :** Rector avec règles `driftingly/rector-laravel`
- **TODOs :** Quelques commentaires `// TODO` dans les ressources Filament (non critiques)
- **Note qualité : 7,5/10** — Excellente architecture et types stricts ; quelques closures inline dans `api.php` violent le SRP

### Frontend (Next.js)
- **TypeScript :** Activé ; probablement quelques `any` implicites dans les services
- **ESLint :** Configuré (`eslint.config.mjs`, next/recommended)
- **Aucune couverture de test** — risque significatif
- **Note qualité : 6/10** — Bonne structure des composants, mais zéro test et typage potentiellement incomplet

### Apps mobiles
- **Pas de TypeScript** (JavaScript pur `.js`)
- `console.warn/error` utilisés intentionnellement pour les événements WebView
- **Note qualité : 7/10** — Apps monofichier propres et bien commentées ; architecture intentionnellement simple

---

## 10. BUILD & DÉPLOIEMENT

### Développement local
```bash
# Backend (depuis la racine)
composer install
php artisan migrate --seed
composer run dev   # lance php artisan serve + queue + pail + vite en parallèle

# Frontend
cd keyhome-frontend-next
pnpm install
pnpm dev           # serveur dev Next.js sur :3000

# Mobile (Agency)
cd mobile/agency
npm install
npx expo start --ios   # ou --android
```

### Docker (production)
```bash
docker compose up -d                        # Stack core (7 services)
docker compose --profile monitoring up -d   # + Prometheus/Grafana
docker compose --profile debug up pgadmin   # + PgAdmin
```

**Services Docker:**
| Conteneur | Image | Rôle |
|-----------|-------|------|
| `keyhome-backend` | PHP-FPM personnalisé | Application Laravel |
| `keyhome-worker` | Même image | Worker file d'attente (emails, notifs) |
| `keyhome-web` | nginx:alpine | Serveur web |
| `keyhome-db` | postgis/postgis:15-3.3-alpine | Base de données |
| `keyhome-redis` | redis:alpine | Cache + files d'attente |
| `keyhome-meilisearch` | getmeili/meilisearch:v1.10 | Moteur de recherche |
| `keyhome-nightwatch-agent` | laravelphp/nightwatch-agent | Agent monitoring |

### Pipeline CI/CD (GitLab CI — `.gitlab-ci.yml`)
**Étapes :** `lint → test → build → deploy`
- Lint : PHP Pint + PHPStan
- Test : Pest avec service PostgreSQL
- Build : Build image Docker + push dans le registre
- Deploy : SSH vers VPS avec `docker compose pull && up -d`

### Prérequis d'environnement
- PHP 8.4+ (Docker : Dockerfile personnalisé)
- Node.js 20+ (build Next.js)
- PostgreSQL 15 + PostGIS 3.3
- Redis (version récente)
- MeiliSearch v1.10

---

## 11. RISQUES CLÉS & DETTE TECHNIQUE

| # | Problème | Sévérité | Description | Correction suggérée |
|---|----------|----------|-------------|---------------------|
| 1 | **Passerelle de paiement unique** | 🔴 Critique | FedaPay uniquement ; pas de fallback MTN/Orange Money | Abstraire `PaymentGatewayInterface` ; adapter MTN MoMo |
| 2 | **Zéro test frontend** | 🔴 Critique | Next.js sans aucun test ; régressions indétectables | Ajouter Vitest + Playwright E2E |
| 3 | **Zéro test mobile** | 🔴 Critique | Les apps Expo sans tests | Detox ou Jest + RNTL |
| 4 | **Stockage médias local** | 🟠 Élevé | Images sur volume Docker, pas de CDN | Migrer vers S3-compatible + Cloudflare R2 |
| 5 | **Push notifications non câblées** | 🟠 Élevé | Infrastructure Expo présente dans les deps mais non connectée au backend | Implémenter enregistrement token FCM/APNs |
| 6 | **Closures inline dans les routes** | 🟡 Moyen | `api.php` contient des closures pour `/my/unlocked-ads` et `/payments/unlock-price` | Extraire vers des contrôleurs dédiés |
| 7 | **Pas d'i18n** | 🟡 Moyen | Interface codée en dur en français | Installer `next-intl` ; ajouter `lang/en/` |
| 8 | **Risque N+1 recommandation** | 🟡 Moyen | `RecommendationEngine` score toutes les annonces visibles en mémoire | Paginer ou pré-calculer les scores via job |
| 9 | **Étalement du monorepo** | 🟡 Moyen | 4 codebases, 4 `package.json` dans le même dépôt | Envisager nx/turborepo ou dépôts séparés |
| 10 | **Pas de rate-limit sur Filament** | 🟡 Moyen | Routes des panneaux Filament sans rate-limit explicite | Ajouter throttle middleware aux providers |
| 11 | **`clerk_id` ajouté tardivement** | 🟢 Faible | Intégration Clerk boulonnée (migration 2026-02-21) | Documenté, pas d'action requise |
| 12 | **Migrations sans `down()`** | 🟢 Faible | Les migrations tardives omettent la méthode `down()` | Ajouter les migrations inverses |

---

## 12. TABLEAU RÉCAPITULATIF

| Métrique | Valeur |
|----------|--------|
| **Langages** | PHP 8.4, TypeScript 5, JavaScript (ES2022+) |
| **Frameworks** | Laravel 12, Next.js 16, Expo 54, Filament 4 |
| **Tables de base de données** | ~24 (17 domaine + 7 système) |
| **Migrations** | 57 |
| **Endpoints API** | ~55 |
| **Pages Next.js** | 19 |
| **Panneaux Filament** | 3 (Admin, Agency, Bailleur) |
| **Apps mobiles** | 2 (shells Expo WebView) |
| **Composants réutilisables (Next.js)** | ~15–20 |
| **Tests backend (fichiers)** | 28 (25 Feature + 3 Unit) |
| **Tests frontend** | 0 |
| **Tests mobile** | 0 |
| **Services externes** | 7 (FedaPay, Clerk, Google, Facebook, Apple, Sentry, Nightwatch) |
| **Services Docker** | 7 core + 6 monitoring optionnels |
| **CI/CD** | GitLab CI (4 étapes) |
| **Qualité backend** | **7,5 / 10** |
| **Qualité frontend** | **6 / 10** |
| **Qualité mobile** | **7 / 10** |
| **Qualité globale** | **7 / 10** |

---

*Documentation générée par Antigravity — Architecte Logiciel Senior | 22 février 2026*
