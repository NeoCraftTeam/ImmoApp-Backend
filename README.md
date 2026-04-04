# KeyHome Backend

Plateforme immobilière **multi-tenant** pour le marché **Afrique de l'Ouest** (XOF/XAF).
API REST Laravel 12 avec deux panels Filament, gestion des visites, contrats de bail,
tours virtuels 360°, intelligence artificielle, alertes de recherche, et plus.

## Architecture technique

| Couche | Stack |
|---|---|
| **Backend API** | Laravel 12, PHP 8.4, Sanctum |
| **Base de données** | PostgreSQL 15 + PostGIS 3.3 |
| **Recherche** | Meilisearch v1.10 |
| **Panels admin** | Filament 4 — 2 panels : Admin (`/admin`), Agency (`/agency`) |
| **Frontend web** | Next.js 16 dans `keyhome-frontend-next/` |
| **Paiement** | Flutterwave |
| **Files d'attente** | Redis — queues : `critical`, `payments`, `emails`, `default`, `tours` |
| **Stockage média** | Cloudflare R2 (prod) / local (dev) |
| **Monitoring** | Sentry, Laravel Pulse, Telescope, Laravel Nightwatch |
| **Proxy inverse** | Traefik (HTTPS Let's Encrypt) |

## Prérequis

- PHP 8.4+
- PostgreSQL 15 + extension PostGIS
- Redis
- Meilisearch
- Composer
- Node.js ≥ 18

## Installation locale

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan scout:import "App\Models\Ad"
```

## Développement

```bash
# Serveur + queue + logs + vite en parallèle
composer run dev

# Build des assets frontend
npm run build
```

## Tests

```bash
# Tous les tests (Pest v4, RefreshDatabase global)
php artisan test

# Fichier unique
php artisan test tests/Feature/AuthTest.php

# Filtre par nom
php artisan test --filter="it can login with valid credentials"

# Pipeline qualité complète (PHPStan + Rector + Pint + Tests)
./tests/quality.sh --fix
```

Nécessite une base PostgreSQL `testing` (voir `phpunit.xml`).
Driver Meilisearch : `null` en tests. Clés Flutterwave factices en tests.

## Linting & Analyse statique

```bash
vendor/bin/pint                      # Correction de style (Laravel Pint)
vendor/bin/pint --test               # Vérification uniquement
vendor/bin/phpstan analyse           # Analyse statique (Larastan niveau 5)
vendor/bin/rector process --dry-run  # Refactoring automatique (vérification)
```

## Variables d'environnement

Les variables marquées ⚠️ sont **obligatoires en production**.

### Application

```env
APP_NAME=keyhome
APP_ENV=production                      # ⚠️
APP_KEY=                                # ⚠️  php artisan key:generate
APP_DEBUG=false                         # ⚠️
APP_URL=https://api.keyhome.app         # ⚠️
APP_LOCALE=fr
FRONTEND_URL=https://app.keyhome.app    # ⚠️  URL du frontend Next.js
EMAIL_VERIFY_CALLBACK=https://app.keyhome.app
EMAIL_CALLBACK_URL=https://app.keyhome.app
```

### Base de données

```env
DB_CONNECTION=pgsql
DB_HOST=db                              # ⚠️  (nom du service Docker en prod)
DB_PORT=5432
DB_DATABASE=keyhome                     # ⚠️
DB_USERNAME=postgres                    # ⚠️
DB_PASSWORD=                            # ⚠️
DB_SSLMODE=prefer                       # prefer pour Docker ; require pour DB externe
```

### Cache / Session / Queue

```env
SESSION_DRIVER=redis                    # ⚠️  (cookie en dev)
SESSION_DOMAIN=.keyhome.app             # ⚠️  domaine de l'APP_URL
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=redis                  # ⚠️  (sync en dev)
CACHE_STORE=redis                       # ⚠️  (array en dev)
REDIS_HOST=redis                        # ⚠️  (127.0.0.1 en dev)
REDIS_PORT=6379
```

### Stockage média (Cloudflare R2)

```env
FILESYSTEM_DISK=r2                      # ⚠️  (local en dev)
# Structure R2 : avatars/ agency-logos/ lease-contracts/ ads/ tours/
AWS_ACCESS_KEY_ID=                      # ⚠️
AWS_SECRET_ACCESS_KEY=                  # ⚠️
AWS_DEFAULT_REGION=auto
AWS_BUCKET=                             # ⚠️
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com  # ⚠️
TOUR_STORAGE_DISK=r2
```

### Meilisearch

```env
SCOUT_DRIVER=meilisearch                # ⚠️  (null en dev/tests)
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=                        # ⚠️
```

### Mail

```env
MAIL_MAILER=smtp                        # ⚠️  (log en dev)
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=                          # ⚠️
MAIL_PASSWORD=                          # ⚠️
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@keyhome.app
MAIL_FROM_NAME="KeyHome"
MAIL_ASSET_BASE_URL=https://keyhome.app
```

### Clerk (authentification frontend)

```env
CLERK_PUBLISHABLE_KEY=pk_live_...       # ⚠️
CLERK_SECRET_KEY=sk_live_...            # ⚠️
CLERK_JWKS_URL=                         # ⚠️  ex : https://your-instance.clerk.accounts.dev/.well-known/jwks.json
CLERK_WEBHOOK_SECRET=whsec_...          # ⚠️
```

### OAuth (Socialite)

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/api/v1/auth/oauth/google/callback

FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=${APP_URL}/api/v1/auth/oauth/facebook/callback

APPLE_CLIENT_ID=
APPLE_CLIENT_SECRET=
APPLE_REDIRECT_URI=${APP_URL}/api/v1/auth/oauth/apple/callback

OAUTH_ALLOWED_REDIRECT_HOSTS=           # Hôtes OAuth supplémentaires (virgule)
```

### IA — LLM (recherche naturelle & amélioration de description)

```env
# Ordre de fallback pour la recherche naturelle (au moins un fournisseur actif)
AI_SEARCH_PROVIDERS=groq,openai,gemini  # ⚠️
AI_PROVIDER=groq                        # fournisseur pour AiDescriptionEnhancer

GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.0-flash
TOGETHER_API_KEY=
MISTRAL_API_KEY=
```

### Géolocalisation (OpenRouteService)

```env
ORS_API_KEY=    # Isochrones, directions, distances à pied — 2 000 req/jour (gratuit)
# https://openrouteservice.org/dev/#/home
```

### Monitoring

```env
SENTRY_LARAVEL_DSN=                     # ⚠️
NIGHTWATCH_TOKEN=                       # ⚠️
TELESCOPE_ENABLED=false                 # désactiver en prod si non nécessaire
```

### Sécurité

```env
SANCTUM_STATEFUL_DOMAINS=app.keyhome.app   # ⚠️  domaine(s) du frontend (virgule)
SANCTUM_TOKEN_PREFIX=kh_
MFA_API_SESSION_LIFETIME=480               # durée session MFA API (minutes)
TRUSTED_PROXIES=172.18.0.0/16             # ⚠️  adapter au réseau Docker
```

### Backups

```env
BACKUP_DISKS=local,backups
BACKUP_NOTIFICATION_MAIL=admin@example.com
BACKUP_SEND_BY_MAIL=true
BACKUP_MAIL_MAX_SIZE_MB=20
```

## Commandes Artisan utiles

### Créer un administrateur

```bash
# Interactif
php artisan app:create-admin

# Non-interactif
php artisan app:create-admin --email=admin@example.com --firstname=John --lastname=Doe --password=Secret123

# Docker
docker compose exec app php artisan app:create-admin
```

### Créer des utilisateurs de démo

```bash
php artisan app:create-demo-users
# Docker
docker compose exec app php artisan app:create-demo-users
```

### Import des attributs de biens

```bash
php artisan make:upload-attributes           # Ajoute/met à jour le catalogue
php artisan make:upload-attributes --fresh   # Réinitialise et réimporte
# Docker
docker compose exec app php artisan make:upload-attributes
```

### Meilisearch

```bash
php artisan app:sync-meilisearch-settings    # Synchroniser les paramètres d'index
php artisan scout:import "App\Models\Ad"     # Importer toutes les annonces
```

### Seeding

> Avant le seed, déposez 10–20 images par catégorie dans
> `resources/seeder-images/{maison,terrain,chambre,studio,appartement,commercial}/`.
> Formats acceptés : jpg, jpeg, png, webp.

```bash
php artisan migrate:fresh --seed              # Purge et re-seed complet
# SEED_FAST_MODE=true dans .env → 200 annonces (~10× plus rapide)
php artisan media-library:regenerate --force  # Régénérer conversions WebP
```

### Backups

```bash
php artisan backup:run            # DB + fichiers
php artisan backup:run --only-db  # DB uniquement
php artisan backup:list
php artisan backup:clean
# Docker
docker compose exec app php artisan backup:run
```

> Le scheduler cron requis :
> `* * * * * php /var/www/artisan schedule:run >> /dev/null 2>&1`

## Authentification

| Méthode | Description |
|---|---|
| **Sanctum Bearer** | Tokens API préfixés `kh_` — clients & mobile |
| **Clerk JWT** | OAuth frontend échangé via `POST /api/v1/auth/clerk/exchange` |
| **Socialite** | Google, Facebook, Apple (`/api/v1/auth/oauth/{provider}`) |
| **Magic link** | Sign-in & sign-up sans mot de passe |
| **MFA** | TOTP + email pour les panels Filament (session) |
| **Sessions** | Cookie sécurisé (`SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`) |

## Panels Filament

| Panel | Path | Provider | Description |
|---|---|---|---|
| **Admin** | `/admin` | `AdminPanelProvider` | Gestion globale de la plateforme |
| **Agency** | `/agency` | `AgencyPanelProvider` | Multi-tenant par agence |

## Fonctionnalités principales

1. **Annonces** — Machine à états (`pending → available → reserved/rent/sold`), boost, slug, expiration.
2. **Déblocage payant** — Paiement Flutterwave pour les coordonnées de contact (montant côté serveur).
3. **Abonnements agences** — Plans mensuel/annuel avec limites d'annonces et fonctionnalités premium.
4. **Crédits (points)** — Portefeuille `PointPackage` / `PointTransaction`, achat & consommation.
5. **Boost d'annonces** — Automatique via abonnement ou achat de crédits.
6. **Moteur de recommandation** — Score pondéré (type ×40, ville ×25, budget ×20, fraîcheur ×10, popularité ×5). Cold-start + 20% de diversité.
7. **Recherche avancée** — Meilisearch (full-text, facettes), PostGIS (nearby), langage naturel IA (Groq/OpenAI/Gemini avec failover).
8. **Tours virtuels 360°** — Upload de scènes, traitement asynchrone (`worker-tours`), proxy sécurisé.
9. **Visites** — Disponibilités (Zap), réservations tentatives, confirmation, expiration auto.
10. **Contrats de bail** — Création, signature électronique (`LeaseSignatureRequest`), gestion locataires.
11. **Alertes de recherche** — `SearchAlert` + `SearchAlertMatch`, digest email/push.
12. **IA** — Amélioration de descriptions, digest, recherche naturelle multi-LLM.
13. **Géolocalisation** — Isochrones, directions, score de quartier (OpenStreetMap Overpass + ORS).
14. **Analytics** — Vues, impressions, clics, favoris, partages. Dashboard admin.
15. **Notifications** — Database, email (45+ mailables), Web Push, WhatsApp.
16. **Newsletter** — Campagnes, abonnements, unsubscribe.
17. **Sondages** — Anonymes et authentifiés.
18. **RGPD** — Anonymisation, export des données utilisateur.
19. **Multi-tenant agence** — Équipes, invitations, rôles scopés.
20. **Codes promo** — `PromoCode` + `PromoCodeUsage`.

## Architecture des couches

| Couche | Répertoire | Règle |
|---|---|---|
| Controllers | `app/Http/Controllers/Api/V1/` | `final`, Form Requests, zéro logique métier |
| Services | `app/Services/` | `final readonly`, logique métier, injection DI |
| Actions | `app/Actions/` | Classes à usage unique |
| DTOs | `app/DTOs/` | Value objects immuables |
| Models | `app/Models/` | Eloquent, UUID, soft delete, Spatie Media Library |
| Support | `app/Support/` | Utilitaires (`ApiResponse`, `GeoLocation`, `PanelUrl`…) |

## Paiement (Flutterwave)

- Pattern Stratégie : `PaymentGatewayInterface` → `FlutterwavePaymentService`
- `PaymentService` : orchestrateur injecté via DI dans `AppServiceProvider`
- Montants résolus côté serveur depuis `PointPackage` / `SubscriptionPlan` — jamais côté client
- Verrous DB (`lockForUpdate`) contre la double-dépense
- Events : `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`
- `RefundService` : traitement des remboursements

## Infrastructure Docker

### Services (`docker-compose.yml`)

| Service | Image | Rôle |
|---|---|---|
| `app` | `keyhome-backend` (Dockerfile) | PHP-FPM (Laravel) |
| `worker` | `keyhome-backend` | Queue `critical,payments,emails,default` (tries=3, timeout=90s) |
| `worker-tours` | `keyhome-backend` | Queue `tours` (tries=2, timeout=900s, 512 MB) |
| `nightwatch-agent` | `laravelphp/nightwatch-agent` | Laravel Nightwatch |
| `web` | `nginx:alpine` | Nginx — port `WEB_PORT` (défaut 9090) |
| `db` | `postgis/postgis:15-3.3-alpine` | PostgreSQL + PostGIS |
| `redis` | `redis:alpine` | Cache / session / queue (AOF activé) |
| `meilisearch` | `getmeili/meilisearch:v1.10` | Moteur de recherche |
| `pgadmin` _(profil `debug`)_ | `dpage/pgadmin4` | Interface DB (port 5050) |
| `prometheus` _(profil `monitoring`)_ | `prom/prometheus` | Métriques |
| `grafana` _(profil `monitoring`)_ | `grafana/grafana` | Dashboards |
| `node-exporter` _(profil `monitoring`)_ | `prom/node-exporter` | Métriques VPS |
| `cadvisor` _(profil `monitoring`)_ | `gcr.io/cadvisor/cadvisor` | Métriques conteneurs |
| `postgres-exporter` _(profil `monitoring`)_ | `prometheuscommunity/postgres-exporter` | Métriques DB |
| `redis-exporter` _(profil `monitoring`)_ | `oliver006/redis_exporter` | Métriques Redis |

### Commandes Docker

```bash
# Services principaux
docker compose up -d

# Avec monitoring
docker compose --profile monitoring up -d

# Avec pgAdmin (debug)
docker compose --profile debug up -d

# Migrations en production
docker compose exec app php artisan migrate --force
```

### Environnement preprod

`docker-compose.preprod.yml` — déploiement dans `/opt/keyhome-preprod/`.
Partage la DB, Redis et Meilisearch de la prod via le réseau `keyhome_keyhome-network`.

## CI/CD

**GitHub Actions** (`.github/workflows/`) — branches `main`, `develop`, `preprod` :
1. Tests Pest (PostgreSQL + PostGIS + Redis en services)
2. Build Docker
3. Déploiement sur VPS

**GitLab CI/CD** (`.gitlab-ci.yml`) — stages :
`prepare → quality → build_and_test → deploy → smoke_test → notify → cleanup`
Runner self-hosted. GitLab Container Registry pour les images Docker.

Traefik assure le reverse proxy HTTPS (Let's Encrypt) devant le service `web`.

## Comptes de démo

| Rôle | Email | Mot de passe |
|---|---|---|
| Admin | test-admin-nc@proton.me | Password123! |
| Agence | test-prof-nc@proton.me | Password123! |
| Bailleur | test-student-nc@proton.me | Password123! |
| Client | test-client-nc@proton.me | Password123! |

## Conventions de code

- `declare(strict_types=1)` dans tous les fichiers PHP
- UUIDs comme clés primaires (`HasUuids`)
- `Model::preventLazyLoading()` hors production
- `Ad::status` exclu du `$fillable` — utiliser `transitionTo()` ou `forceFill()`
- Images : Spatie Media Library, conversions WebP (thumb, medium, large)
- Géolocalisation : PostGIS (colonne `location` sur `Ad` et `User`)
- Recherche full-text : Meilisearch
- Données sensibles masquées dans les logs : `MaskSensitiveDataProcessor`
- Politiques dans `app/Policies/` — une par modèle principal
- OpenAPI auto-généré : `darkaonline/l5-swagger` (`app/Docs/`, `app/Swagger/`)

## Documentation opérationnelle

Voir `.docs/` pour le guide complet :

- `00-conventions-linux.md` — Structure Linux (FHS)
- `01-migration-serveur.md` — Migration vers un nouveau VPS
- `02-gitlab-cicd.md` — Pipeline CI/CD GitLab
- `03-traefik-setup.md` — Reverse proxy & SSL
- `04-docker-compose-complet.md` — Référence Docker complète

## Licence

Projet privé — NeoCraft. Tous droits réservés.
