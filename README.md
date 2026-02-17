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

### Créer un admin

```bash
# Interactif
php artisan app:create-admin

# Non-interactif
php artisan app:create-admin --email=admin@example.com --firstname=John --lastname=Doe --password=secret123

# Promouvoir un utilisateur existant en admin
php artisan app:create-admin --email=user@example.com
```

**En production (Docker) :**
```bash
docker compose exec app php artisan app:create-admin
```

### Seeding de la base de données

```bash
# Purge et re-seed complet (villes, quartiers, 2000 annonces)
php artisan migrate:fresh --seed

# Régénérer les conversions d'images (thumb, medium, large en WebP)
php artisan media-library:regenerate --force
```

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

## Comptes par défaut (seed)

| Rôle     | Email              | Mot de passe |
|----------|--------------------|--------------|
| Admin    | admin@keyhome.cm   | password     |

## Déploiement

Le déploiement est automatisé via GitLab CI. Un push sur `main` déclenche :
1. Build de l'image Docker
2. Exécution des tests
3. Déploiement sur le VPS
