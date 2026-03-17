# KeyHome Backend

Plateforme immobilière **multi-tenant** destinée au marché **ouest-africain** (monnaie XOF, paiement via FedaPay). Elle permet de publier, rechercher, débloquer et gérer des annonces immobilières (vente/location), avec un écosystème complet : API backend Laravel, panels d'administration Filament, frontend Next.js, et 2 apps mobiles Expo (bailleur & agence).

## Architecture technique

| Couche | Stack |
|---|---|
| **Backend API** | Laravel 12, PHP 8.4, Sanctum (auth API tokens) |
| **Base de données** | PostgreSQL + PostGIS (géolocalisation) |
| **Recherche** | Meilisearch (full-text, autocomplétion, facettes) |
| **Panels admin** | Filament 4 (3 panels : Admin, Bailleur, Agency) |
| **Frontend web** | Next.js (dans `keyhome-frontend-next/`) |
| **Apps mobiles** | 2 apps Expo/React Native (dans `mobile/agency/` et `mobile/bailleur/`) |
| **Paiement** | FedaPay (passerelle de paiement africaine, en XOF) |
| **Files d'attente** | Redis |
| **Monitoring** | Sentry, Laravel Pulse, Telescope |
| **Media** | Spatie Media Library (images avec conversions WebP : thumb, medium, large) |

## Modèles de données principaux

| Modèle | Rôle |
|---|---|
| **User** | 3 rôles (`admin`, `agent`, `customer`), 2 types (`individual`, `agency`). Auth Sanctum + MFA Filament. |
| **Ad** | Annonce immobilière : titre, description, prix, surface, chambres, SdB, parking, géoloc PostGIS, statut (available/reserved/rent/pending/sold), boost, slug, expiration. Recherche Meilisearch. |
| **Agency** | Agence immobilière, avec un propriétaire, des agents, et des abonnements. |
| **AdType** | Types d'annonces (appartement, maison, terrain, etc.) |
| **City / Quarter** | Localisation hiérarchique : ville → quartier |
| **Payment** | Paiements FedaPay — 3 types : `unlock` (débloquer une annonce), `subscription`, `boost` |
| **UnlockedAd** | Pivot : quand un client paie pour accéder aux détails d'une annonce |
| **Subscription / SubscriptionPlan** | Plans d'abonnement pour agences (mensuel/annuel, boost inclus, limite d'annonces, features) |
| **Review** | Avis (note + commentaire) sur des annonces |
| **AdInteraction** | Tracking des interactions : vues, favoris, impressions, partages, clics contact/téléphone |
| **Setting** | Paramètres dynamiques (ex: prix de déblocage) |

## Fonctionnalités clés

1. **Publication d'annonces** — Les agents/bailleurs publient des annonces avec photos, géolocalisation, caractéristiques. Machine à états pour le statut (pending → available → reserved/rent/sold).
2. **Déblocage payant** — Les clients voient les annonces mais doivent payer (via FedaPay, en XOF) pour débloquer les coordonnées de contact. Prix configurable dans les Settings.
3. **Abonnements agences** — Plans d'abonnement (mensuel/annuel) avec fonctionnalités premium : boost automatique des annonces, nombre max d'annonces, etc.
4. **Boost d'annonces** — Les annonces des agences abonnées sont automatiquement boostées (score + durée) pour apparaître en priorité.
5. **Moteur de recommandation** — Score pondéré (type ×40, ville ×25, budget ×20, fraîcheur ×10, popularité ×5 + bonus boost). Gestion du cold-start et injection de 20% de diversité.
6. **Recherche avancée** — Meilisearch full-text, autocomplétion, facettes. Recherche géographique `nearby` via PostGIS.
7. **Analytics bailleur/agence** — Dashboard avec statistiques : vues, impressions, clics contact, favoris, partages par annonce.
8. **3 panels Filament** :
   - **Admin** : gestion globale (utilisateurs, annonces, villes, quartiers, types, paiements, abonnements, reviews, activités, paramètres).
   - **Bailleur** : gestion de ses annonces, paiements, reviews, dashboard avec graphiques.
   - **Agency** : idem bailleur + gestion d'abonnement.
9. **Notifications email** — Confirmation de soumission, approbation/refus d'annonce, bienvenue, abonnement (succès, expiration, facture).
10. **API REST v1** — Versionnée, rate limiting, documentation Swagger. Endpoints : auth, annonces, paiements, abonnements, interactions, analytics, recommandations, reviews, villes/quartiers.

## Prérequis

- PHP 8.4+
- PostgreSQL + PostGIS
- Composer
- Node.js (pour le frontend)
- Redis (queues)
- Meilisearch (recherche)

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

## Commandes utiles

### Créer un administrateur

Crée un compte administrateur de manière interactive (prompts) ou via des options en ligne de commande. Le compte est directement vérifié et un email de bienvenue est envoyé.

```bash
# Mode interactif (prompts)
php artisan app:create-admin

# Mode non-interactif (CI / scripts)
php artisan app:create-admin --email=admin@example.com --firstname=John --lastname=Doe --password=Secret123
```

**En production (Docker) :**
```bash
docker compose exec app php artisan app:create-admin
```

### Créer les utilisateurs de test

Crée 4 utilisateurs (admin, agence, bailleur, client) avec des identifiants prédéfinis, vérifie leurs comptes et envoie un email de bienvenue adapté à chaque rôle.

```bash
php artisan app:create-test-users
```

**En production (Docker) :**
```bash
docker compose exec app php artisan app:create-test-users
```

### Seeding de la base de données

Avant de lancer le seed, placez 10–20 images par catégorie (téléchargeables sur Unsplash) dans :

```
resources/seeder-images/
├── maison/
├── terrain/
├── chambre/
├── studio/
├── appartement/
└── commercial/
```

Formats acceptés : jpg, jpeg, png, webp.

```bash
# Purge et re-seed complet (villes, quartiers, 2000 annonces)
php artisan migrate:fresh --seed

# Ou simplement
php artisan db:seed

# Régénérer les conversions d'images (thumb, medium, large en WebP)
php artisan media-library:regenerate --force
```

### Import des catégories et attributs de biens

```bash
# Import standard (ajoute/met à jour le catalogue)
php artisan make:upload-attributes

# Réinitialiser puis réimporter entièrement le catalogue
php artisan make:upload-attributes --fresh
```

**En production (Docker) :**
```bash
docker compose exec app php artisan make:upload-attributes
```

### Backups (spatie/laravel-backup)

Les backups (base de données + fichiers) sont planifiés quotidiennement à 02:00. Configuration via `BACKUP_DISKS`, `BACKUP_NOTIFICATION_MAIL` et `BACKUP_SEND_BY_MAIL` dans `.env`.

Pour recevoir le fichier de backup par email : `BACKUP_SEND_BY_MAIL=true`. La pièce jointe est ignorée si le fichier dépasse `BACKUP_MAIL_MAX_SIZE_MB` (défaut 20 MB, limite courante des fournisseurs email).

```bash
# Backup complet (DB + fichiers)
php artisan backup:run

# Backup base de données uniquement
php artisan backup:run --only-db

# Backup fichiers uniquement
php artisan backup:run --only-files

# Lister les backups existants
php artisan backup:list

# Nettoyer les anciens backups (selon la rétention configurée)
php artisan backup:clean
```

**En production (Docker) :**
```bash
docker compose exec app php artisan backup:run
```

> Les backups planifiés nécessitent que le scheduler Laravel soit exécuté par cron : `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`

### Meilisearch

```bash
# Synchroniser les paramètres d'index
php artisan scout:sync-index-settings

# Importer les annonces dans Meilisearch
php artisan scout:import "App\Models\Ad"
```

### Tests

```bash
php artisan test
php artisan test --filter=NomDuTest
```

### Formatage du code

```bash
vendor/bin/pint
```

## Comptes de test (`app:create-test-users`)

| Rôle     | Email                        | Mot de passe |
|----------|------------------------------|--------------|
| Admin    | test-admin-nc@proton.me      | Password123! |
| Agence   | test-prof-nc@proton.me       | Password123! |
| Bailleur | test-student-nc@proton.me    | Password123! |
| Client   | test-client-nc@proton.me     | Password123! |

## Déploiement

Le déploiement est automatisé via GitLab CI. Un push sur `main` déclenche :
1. Build de l'image Docker
2. Exécution des tests
3. Déploiement sur le VPS
