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
| **Paiement** | Kpay (mobile money, défaut) + Stripe (cartes) — multi-passerelle |
| **Files d'attente** | Redis — queues : `critical`, `payments`, `notifications`, `emails`, `default`, `tours` |
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
- `osm2pgsql` 2.x et `curl` pour importer les villes/quartiers OpenStreetMap

## Documentation

Le présent README est le point d’entrée pour installer le projet et reconstruire
une base. L’index complet se trouve dans le
[centre de documentation](docs/README.md).

| Sujet | Documentation |
|---|---|
| Architecture générale | [Vue d’ensemble](docs/architecture/overview.md) · [Couches backend](docs/architecture/backend-layers.md) |
| Authentification et isolation des sessions | [Flux d’authentification](docs/architecture/auth-flows.md) · [Correctif d’isolation](docs/architecture/SESSION_ISOLATION_FIX.md) |
| Villes, quartiers et coordonnées OSM/PostGIS | [Import géographique OSM](docs/architecture/osm-geography-import.md) |
| Paiements | [Architecture des paiements](docs/architecture/payment-system.md) · [Guide d’intégration](docs/integrations/payment-integration.md) |
| Panel bailleur | [Spécification de l’acteur bailleur](docs/Actors/owner.md) |
| Sondages | [Plan du module sondage](docs/features/5_Survey_Module_Backend_Plan.md) |
| Attributs immobiliers | [Recherche et catalogue professionnel](docs/research/property-attribute-catalog-2026.md) |
| Visites et réservations | [Spécification des visites](docs/features/VIEWING_SCHEDULING_SPEC.md) |
| Tours virtuels 360° | [Guide d’implémentation](docs/features/KeyHome_360_Tour_Implementation_Guide.md) |
| Déploiement et exploitation | [Documentation des opérations](docs/operations/README.md) · [Installation VPS](docs/infrastructure/DEPLOYEMENT_SETUP_GUIDE.md) |
| Sécurité | [Vue d’ensemble](docs/security/overview.md) · [Checklist avant déploiement](docs/security/checklist.md) |
| Fonctionnalités disponibles | [Inventaire KeyHome](docs/LiveDocs/keyhome_feature_inventory.md) |

## Installation locale

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Configurez PostgreSQL/PostGIS dans `.env`, puis suivez la procédure
[Base fraîche / après une purge](#base-fraîche--après-une-purge). Le frontend
Next.js possède ses propres dépendances dans `keyhome-frontend-next/`.

## Base fraîche / après une purge

> `migrate:fresh` supprime toutes les tables et toutes leurs données. Ne lancez
> cette commande que sur une base que vous avez explicitement décidé de
> reconstruire. En production, utilisez normalement `php artisan migrate
> --force`, jamais `migrate:fresh`.

### Procédure recommandée

Après avoir créé une base PostgreSQL vide avec PostGIS, lancez les commandes
suivantes depuis la racine du backend, sans Laravel Sail :

```bash
# 1. Recréer le schéma et installer les catalogues de base
php artisan migrate:fresh --seed

# 2. Importer les villes et quartiers des marchés actifs
# --cleanup supprime chaque fichier PBF après une synchronisation réussie
php artisan geo:refresh-osm cameroon germany-bremen --force-download --cleanup

# 3. Vérifier/réconcilier les catalogues administrables (idempotent)
php artisan catalog:sync-attributes
php artisan survey:install-default

# 4. Préparer le stockage public
php artisan storage:link

# 5. Configurer puis reconstruire l’index de recherche
php artisan meilisearch:sync-settings
php artisan scout:import "App\Models\Ad"
```

`migrate:fresh --seed` installe déjà les types d’annonces, abonnements, packs de
crédits, boosts, attributs et sondage. Les deux commandes de l’étape 3 sont
volontairement relancées : elles sont idempotentes et garantissent que les
catalogues professionnels correspondent à la version actuelle du code.

L’import OSM doit être exécuté après les migrations. Il est cumulatif : importer
un nouveau pays ne supprime pas les villes déjà synchronisées. Pour afficher les
régions disponibles :

```bash
php artisan geo:refresh-osm --list
```

Exemples selon l’environnement :

```bash
# Cameroun uniquement
php artisan geo:refresh-osm cameroon --force-download --cleanup

# Test allemand léger (~20 Mo), recommandé en développement
php artisan geo:refresh-osm germany-bremen --force-download --cleanup

# Marchés complets — fichiers volumineux, prévoir disque et durée d’import
php artisan geo:refresh-osm cameroon france germany --force-download --cleanup

# Même opération en production (autorisation explicite requise)
php artisan geo:refresh-osm cameroon france germany --force-download --cleanup --force
```

La documentation des fichiers téléchargés, du nettoyage, de PostGIS et de
l’ordre latitude/longitude est disponible dans le
[guide OSM/PostGIS](docs/architecture/osm-geography-import.md).

### Données facultatives après reconstruction

```bash
# Annonces et utilisateurs de démonstration : nécessite d’abord villes/quartiers
php artisan db:seed --class=MassiveAdSeeder

# Comptes techniques de test (admin, agence, bailleur, client)
php artisan app:create-test-users

# Administrateur réel, assistant interactif
php artisan app:create-admin
```

`MassiveAdSeeder` est volontairement séparé du premier seed lorsque la base ne
contient pas encore de géographie. Ne l’utilisez pas sur une base de production
contenant de vraies annonces.

### Référence rapide

| Besoin | Commande | Peut être relancée ? |
|---|---|---|
| Purger et reconstruire le schéma | `php artisan migrate:fresh --seed` | Oui, mais détruit toutes les données |
| Lister les régions OSM | `php artisan geo:refresh-osm --list` | Oui, lecture seule |
| Importer villes et quartiers | `php artisan geo:refresh-osm <régions> --cleanup` | Oui, synchronisation idempotente |
| Forcer un nouveau téléchargement OSM | ajouter `--force-download` | Oui |
| Synchroniser les attributs | `php artisan catalog:sync-attributes` | Oui |
| Prévisualiser les attributs | `php artisan catalog:sync-attributes --dry-run` | Oui, lecture seule |
| Remplacer tout le catalogue d’attributs | `php artisan catalog:sync-attributes --fresh` | Destructif pour le catalogue |
| Installer/actualiser le sondage | `php artisan survey:install-default` | Oui |
| Créer les annonces de démonstration | `php artisan db:seed --class=MassiveAdSeeder` | Réservé au développement |
| Recréer l’index des annonces | `php artisan scout:import "App\Models\Ad"` | Oui |

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
Driver Meilisearch : `null` en tests. Clés Kpay/Stripe factices en tests.

## Référentiel géographique OpenStreetMap

Les villes et quartiers ne sont plus injectés par des seeders ou par l'ancien
fichier `cities.sql`. Le catalogue réel provient des extraits Geofabrik importés
dans PostgreSQL/PostGIS avec `osm2pgsql`.

```bash
# Afficher les pays/régions configurés
php artisan geo:refresh-osm --list

# Télécharger, importer et synchroniser un pays
php artisan geo:refresh-osm cameroon

# Traiter plusieurs pays successivement sans supprimer les précédents
php artisan geo:refresh-osm cameroon france germany

# Télécharger à nouveau les PBF avant l'import
php artisan geo:refresh-osm france germany --force-download

# Supprimer les PBF après chaque synchronisation réussie
php artisan geo:refresh-osm france germany --force-download --cleanup
```

En production, ajouter `--force`. Les sous-commandes `geo:download-osm`,
`geo:import-osm` et `geo:sync-osm` sont réservées au diagnostic et à la reprise
d'une étape. Documentation détaillée :
[`docs/architecture/osm-geography-import.md`](docs/architecture/osm-geography-import.md).

### Catalogues administrables

```bash
# Attributs professionnels des studios, appartements et maisons
php artisan catalog:sync-attributes --dry-run
php artisan catalog:sync-attributes

# Sondage KeyHome par défaut avec questions et options
php artisan survey:install-default
```

Ces commandes sont idempotentes. `catalog:sync-attributes --fresh` remplace le
catalogue d’attributs et doit uniquement être utilisé lors d’une reconstruction
assumée de la base.

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

### WebAuthn / Passkeys

```env
WEBAUTHN_NAME="KeyHome Admin"                                        # Nom affiché dans le dialog du navigateur
WEBAUTHN_ID=keyhome.app                                              # ⚠️  Relying Party ID (domaine racine)
WEBAUTHN_ORIGINS=https://admin.keyhome.app,https://app.keyhome.app   # ⚠️  Origines autorisées (virgule) — include frontend origin!
```

> **API passkey endpoints** (for frontend/PWA): `POST /api/v1/auth/webauthn/login/options`, `POST /api/v1/auth/webauthn/login`, etc.
> See `WebAuthnApiController` and `AGENTS.md` for full details.

### Sécurité

```env
SANCTUM_STATEFUL_DOMAINS=app.keyhome.app   # ⚠️  domaine(s) du frontend (virgule)
SANCTUM_TOKEN_PREFIX=kh_
MFA_API_SESSION_LIFETIME=480               # durée session MFA API (minutes)
TRUSTED_PROXIES=172.18.0.0/16             # ⚠️  adapter au réseau Docker
```

### Backups

```env
BACKUP_DISKS=backups                 # `local,backups` pour doubler sur le VPS
BACKUP_NOTIFICATION_MAIL=admin@example.com
BACKUP_SEND_BY_MAIL=true             # email + lien R2 signé 48h après chaque succès
BACKUP_KEEP_DAYS=30                  # rétention plate ; au-delà `backup:clean` purge
BACKUP_MAX_STORAGE_MB=5000           # plafond de stockage du bucket
BACKUP_ARCHIVE_PASSWORD=             # ⚠️  chiffrement AES-256 de l'archive
BACKUP_VERIFY=true                   # ouvre le zip après création
```

Planification (`routes/console.php`, heures UTC) : `backup:run --only-db` toutes
les six heures (02:00, 08:00, 14:00, 20:00), `backup:run --only-files` le dimanche
à 02:20, `backup:clean` à 03:00, `backup:monitor` à 04:00. Les sorties sont
tracées dans `storage/logs/backup.log`. Ces expressions cron sont verrouillées
par `tests/Feature/BackupRetentionTest.php` : les modifier sans mettre le test à
jour fait échouer la suite.

L'archive de fichiers ne contient que `storage/app` : le code vient de l'image
Docker et les médias de production vivent sur R2. `.env` est donc volontairement
exclu — aucun secret ne part sur le bucket. Sont également exclus
`firebase-credentials.json`, `seeder-images`, `private/osm` (extrait Geofabrik
re-téléchargeable) et `private/backups`.

Seuls les échecs déclenchent un email Spatie. Le signal de bonne santé est
l'email de `SendBackupByEmailListener`, qui prouve à la fois que l'archive existe
et qu'elle est téléchargeable. Les quatre dumps quotidiens ne produisent qu'**un
seul** message par jour civil : le verrou (`Cache::add`, atomique) n'est posé
qu'après l'obtention du lien signé, donc si R2 est indisponible à 02:00,
l'exécution de 08:00 peut encore prévenir. Un canal saturé de succès est un canal
où l'échec passe inaperçu.

#### Restaurer une archive

Les chemins du zip sont relatifs à la racine du projet (`storage/app/…`), donc
une archive de fichiers se restaure en la dépliant dans `/var/www`.

`BACKUP_ARCHIVE_PASSWORD` produit un chiffrement **AES-256 (WinZip)** que
l'`unzip` d'Info-ZIP — celui de macOS — ne sait pas déchiffrer
(`need PK compat. v5.1`). Utiliser `7z`, ou PHP, disponible dans le conteneur :

```bash
7z x 2026-09-04-02-00-01.zip -p"$BACKUP_ARCHIVE_PASSWORD" -o/var/www

php -r '$z=new ZipArchive; $z->open($argv[1], ZipArchive::RDONLY);
$z->setPassword(getenv("BACKUP_ARCHIVE_PASSWORD")); var_dump($z->extractTo($argv[2]));' \
  2026-09-04-02-00-01.zip /var/www

# puis, pour une archive de base :
psql "$DATABASE_URL" -f /var/www/db-dumps/postgresql-keyhome.sql
```

Trois pièges, vérifiés lors du test de restauration du 2026-09-05 (base montée
dans un conteneur `postgis/postgis` jetable, sur le réseau Docker de la prod) :

1. **Utiliser le client `psql` du conteneur applicatif** (18.6), pas celui du
   serveur PostgreSQL cible (15). Le dump est produit par `pg_dump` 18.6 et
   contient `\restrict` et `SET transaction_timeout`, que `psql` 15 rejette.
2. **Créer le rôle propriétaire avant le chargement** sur une base neuve
   (`CREATE ROLE cedrick;`), sinon chaque `ALTER … OWNER TO` échoue.
3. **Ne jamais faire passer la sortie de `psql` dans un `head`** (ni `grep -m`) :
   la fermeture du tube envoie un SIGPIPE qui interrompt le chargement au bout de
   quelques dizaines de lignes et laisse une base vide sans message d'erreur.
   Rediriger vers un fichier, puis le lire.

Contrôle de conformité après restauration : nombre de tables, de migrations, de
contraintes et d'index, puis quelques comptages métier (`users`, `ad`, `media`).

Sans mot de passe valide l'extraction échoue : une archive chiffrée dont la
passphrase est perdue est définitivement illisible. Conserver
`BACKUP_ARCHIVE_PASSWORD` hors du VPS.

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
php artisan app:create-test-users
# Docker
docker compose exec app php artisan app:create-test-users
```

### Import des attributs de biens

```bash
php artisan catalog:sync-attributes           # Ajoute/met à jour le catalogue
php artisan catalog:sync-attributes --fresh   # Réinitialise et réimporte
# Docker
docker compose exec app php artisan catalog:sync-attributes
```

### Meilisearch

```bash
php artisan meilisearch:sync-settings        # Synchroniser les paramètres d'index
php artisan scout:import "App\Models\Ad"     # Importer toutes les annonces
```

### Seeding

> Avant le seed, déposez au moins 10 images au total dans
> `resources/seeder-images/{maison,terrain,chambre,studio,appartement,commercial}/`.
> Formats acceptés : jpg, jpeg, png, webp. `MassiveAdSeeder` sélectionne les
> 10 premiers fichiers dans un ordre déterministe et réutilise exactement ce
> même ensemble pour chacune des 10 annonces.

```bash
php artisan migrate:fresh --seed              # Purge et re-seed complet
php artisan media-library:regenerate --force  # Régénérer conversions WebP
```

### Notifications de rétention (push comportemental)

Cinq déclencheurs fréquentiellement limités via Redis :

| Déclencheur | Condition | Fréquence max |
|---|---|---|
| `win_back` | Aucune connexion depuis ≥ 3 jours | 1 / 7 jours / utilisateur |
| `search_alert_match` | Nouvelle annonce AVAILABLE correspond à une alerte active | 1 / 24 h / alerte |
| `price_drop` | Prix d'une annonce favorite baisse de ≥ 5 000 FCFA | 1 / 48 h / (utilisateur, annonce) |
| `viewing_reminder` | Visite confirmée avec `slot_date` = demain | 1 fois / réservation |
| `lease_expiry` | Bail expire dans 30 ou 7 jours | 1 / seuil / bail |

```bash
# Envoi réel (planifié 2× / jour à 09:00 et 18:00)
php artisan app:send-retention-pushes

# Prévisualisation sans envoi
php artisan app:send-retention-pushes --dry-run

# Docker
docker compose exec app php artisan app:send-retention-pushes --dry-run
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

> Le scheduler tourne en service dédié dans `docker-compose.yml` / `docker-compose.preprod.yml`
> (`scheduler` — `php artisan schedule:work`, même image que `worker`). Sans lui, AUCUNE tâche
> planifiée ne s'exécute (réconciliation des paiements, rappels J-1, backups, rapports…).
> Après déploiement : `docker compose up -d scheduler`.
>
> Fuseaux : `APP_TIMEZONE=UTC` (affichage, factures, mails, logs — référence unique pour des
> utilisateurs multi-fuseaux) et `APP_BUSINESS_TIMEZONE=Africa/Douala` (interprétation des
> créneaux de visite stockés en heure locale Cameroun — ne pas mélanger les deux).

## Authentification

| Méthode | Description |
|---|---|
| **Sanctum Bearer** | Tokens API préfixés `kh_` — clients & mobile |
| **Clerk JWT** | OAuth frontend échangé via `POST /api/v1/auth/clerk/exchange` |
| **Socialite** | Google, Facebook, Apple (`/api/v1/auth/oauth/{provider}`) |
| **Passkeys (WebAuthn)** | Connexion biométrique (empreinte, Face ID, clé USB) pour les panels Filament — `laragear/webauthn ^5` |
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
2. **Déblocage payant** — Paiement Kpay/Stripe pour les coordonnées de contact (montant résolu côté serveur).
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
15. **Messagerie temps réel** — Chat visiteur ↔ bailleur (Reverb WebSocket), chiffré au repos, réactions, pièces jointes, push FCM + email différé.
16. **Notifications** — Database, email (45+ mailables), Web Push, WhatsApp, broadcast temps réel.
17. **Newsletter** — Campagnes, abonnements, unsubscribe.
18. **Sondages** — Anonymes et authentifiés.
19. **RGPD** — Anonymisation, export des données utilisateur.
20. **Multi-tenant agence** — Équipes, invitations, rôles scopés.

## Temps réel (Laravel Reverb)

Le broadcasting utilise **Laravel Reverb** (protocole Pusher) avec des canaux
privés authentifiés via Sanctum (`POST /broadcasting/auth`). Tous les events
implémentent `ShouldBroadcastNow` et sont diffusés après commit DB, dans un
`try/catch` qui ne fait jamais échouer la réponse HTTP si Reverb est indisponible.

| Canal | Events | Consommateurs |
|---|---|---|
| `conversation.{uuid}` (2 participants seulement) | `message.sent`, `messages.read`, `message.deleted`, `message.reaction.added/removed`, `user.typing`, `conversation.archived/unarchived` | Fil de discussion ouvert (web + mobile) |
| `user.{id}` (le propriétaire seulement) | `message.received` (toast + inbox + badge), `credits.updated` (solde + transactions), `search_alert.match` (alerte de recherche) | Listeners globaux : web (`ChatNotificationListener`, `CreditsRealtimeListener`, `NotificationsRealtimeListener`), mobile (`useChatNotificationsRealtime`, `useCreditsRealtime`, `useNotificationsRealtime`) |

`message.received` est le complément clé de `message.sent` : ce dernier
n'atteint que les clients déjà abonnés au canal de la conversation, alors que
le premier notifie le destinataire **partout dans l'app** (toast « Voir »,
badge non-lu, inbox remontée en tête) — y compris pour une conversation toute
neuve. Les notifications Laravel passent par le channel `broadcast` avec
`User::receivesBroadcastNotificationsOn()` = `user.{id}` et un
`broadcastAs()` court (`search_alert.match`).

> En production : `BROADCAST_CONNECTION=reverb` requis (défaut `null` = temps
> réel muet). Auth canal : chacun ne peut s'abonner qu'à son propre
> `user.{id}` et aux conversations dont il est participant (403/404 sinon).

### Cache chat chiffré on-device (modèle WhatsApp)

Les trois clients (web, mobile visitors, mobile owners) **persistent le cache
TanStack Query du chat chiffré sur l'appareil** : inbox et fils s'affichent
instantanément au cold-start, puis resynchronisent en arrière-plan. Web :
AES-GCM 256, clé non-extractible WebCrypto en IndexedDB. Mobile : AES-256
(`crypto-es`), clé dans le SecureStore (Keychain/Keystore,
`THIS_DEVICE_ONLY`). Snapshot purgé au logout. Détails dans `AGENTS.md`
(« Cache chat chiffré on-device — modèle WhatsApp »).

20. **Codes promo** — `PromoCode` + `PromoCodeUsage`.

## Architecture des couches

| Couche | Répertoire | Règle |
|---|---|---|
| Controllers | `app/Http/Controllers/Api/V1/` | `final`, Form Requests, injection DI ; délèguent les opérations métier aux Services et Actions |
| Services | `app/Services/` | Logique métier, injection DI, groupés par domaine (`Ad/`, `Auth/`, `Chat/`, `Payment/`, `Geo/`, `Rental/`, `Monetization/`…) ; `final` (`readonly` dès que l'état le permet) |
| Actions | `app/Actions/` | Classes à responsabilité unique (`execute()` / `handle()`) — ex. `CreateAd`, `UnlockAd`, `InitiateSubscriptionPayment`, `HandlePostPaymentActions` |
| DTOs | `app/DTOs/` | Value objects immuables `final readonly` (`LoginResult`, `AdFeedResult`, `PromoCodeApplication`…) |
| Models | `app/Models/` | Eloquent, UUID, soft delete, Spatie Media Library |
| Support | `app/Support/` | Utilitaires sans état (`ApiResponse`, `GeoLocation`, `PanelUrl`, `Money`…) |

## Paiement (Kpay + Stripe)

- Pattern Stratégie : `PaymentGatewayInterface` → `KpayPaymentService` (mobile money, passerelle par défaut) et `StripePaymentService` (cartes)
- `PaymentService` : orchestrateur multi-passerelle injecté via DI dans `AppServiceProvider` — passerelle par défaut résolue depuis `config('payment.default')` (`kpay`), Stripe toujours enregistré pour les paiements par carte
- Montants résolus côté serveur par `PaymentPricingResolver` depuis `PointPackage` / `SubscriptionPlan` — jamais côté client
- Codes promo appliqués par `PromoCodeApplicator`
- Webhooks : Stripe (signature vérifiée sur le corps brut) et Kpay, traités via `PaymentService::processWebhook()`
- Verrous DB (`lockForUpdate`) contre la double-dépense
- Events : `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`
- `RefundService` : traitement des remboursements

## Infrastructure Docker

### Services (`docker-compose.yml`)

| Service | Image | Rôle |
|---|---|---|
| `app` | `keyhome-backend` (Dockerfile) | PHP-FPM (Laravel) |
| `worker` | `keyhome-backend` | Queue `critical,payments,notifications,emails,default` (tries=3, timeout=90s) |
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

- [Index complet de la documentation](docs/README.md)
- [Runbooks et commandes d’exploitation](docs/operations/README.md)
- [Déploiement](docs/operations/runbooks/deployment.md)
- [Rollback](docs/operations/runbooks/rollback.md)
- [Réponse aux incidents](docs/operations/runbooks/incident-response.md)
- [Configuration préproduction](docs/infrastructure/PREPROD_SETUP.md)
- [Déploiement Reverb/WebSocket](docs/infrastructure/REVERB_DEPLOY.md)
- [Monitoring](docs/infrastructure/MONITORING_GUIDE.md)

## Licence

Projet privé — NeoCraft. Tous droits réservés.
