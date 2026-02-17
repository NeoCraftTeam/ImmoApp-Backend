# KeyHome Backend

Application de gestion immobilière — API Laravel.

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
