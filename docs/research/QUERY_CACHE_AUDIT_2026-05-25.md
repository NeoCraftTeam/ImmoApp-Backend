# KeyHome — Audit Requêtes & Cache Redis — 2026-05-25

## Résumé exécutif

| Statut | Élément |
|--------|---------|
| ✅ Bon | Eager-loading globalement bien utilisé (with/loadMissing partout) |
| ✅ Bon | `Setting::get()` déjà caché 1h via `Cache::remember` |
| ✅ Bon | `isUnlockedFor()` déjà mémoïsé par static array |
| ✅ Bon | Landing stats, testimonials, owner dashboard, analytics → cachés |
| ✅ Bon | PropertyAttributes, BoostPacks, PointPackages, Cities, Quarters → cachés |
| 🔴 Corrigé | `facets()` — 7 requêtes lourdes sans cache (→ Redis 10 min) |
| 🔴 Corrigé | `autocomplete()` — JOIN queries sans cache (→ Redis 5 min) |
| 🔴 Corrigé | `isFavoritedBy()` — 2N queries par page (→ batch loader 1 query) |
| ⚠️ ACTION VPS | `CACHE_STORE=database` par défaut — Redis non activé si `.env` absent |

---

## Problème critique VPS : Redis non utilisé

```
'default' => env('CACHE_STORE', 'database'),
```

**Si `CACHE_STORE` n'est pas dans le `.env` du VPS, tous les `Cache::remember()` vont en PostgreSQL.**
Cela annule complètement le bénéfice du cache.

### Vérification VPS

```bash
# 1. Redis tourne ?
redis-cli ping          # → doit répondre PONG

# 2. Vérifier .env production
grep CACHE_STORE /var/www/html/.env

# 3. Vérifier que Redis répond côté Laravel
php artisan tinker --execute="echo Cache::store('redis')->get('test') ?? 'OK';"
```

### Fix `.env` production (si manquant)

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CACHE_CONNECTION=cache
```

Puis :

```bash
php artisan cache:clear
php artisan config:cache
```

---

## Gaps corrigés ce sprint

### 1. `AdSearchController::facets()` — 7 queries → 1 Redis GET

**Avant** : 7 requêtes SQL lourdes sur chaque chargement de la page de recherche :
- `JOIN quarter + city + GROUP BY` pour les villes
- `JOIN ad_type + GROUP BY` pour les types  
- `GROUP BY bedrooms + COUNT`
- `MIN/MAX price`
- `MIN/MAX surface_area`
- `COUNT has_parking = true`
- `COUNT has_parking = false`

**Après** : `Cache::remember('ads:facets:pgsql', 600, ...)` — Redis hit en < 1 ms.

**Impact estimé** : -85 ms par page de recherche (p95 sur 500+ annonces).

### 2. `AdSearchController::autocomplete()` — JOIN query → Redis 5 min

**Avant** : JOIN sur `ad + quarter/city/ad_type + GROUP BY` à **chaque frappe clavier** (debounce ~300 ms côté frontend mais toujours 1 requête par frappe).

**Après** : `Cache::remember('ads:autocomplete:city:do', 300, ...)` — clé par (field, préfixe).

**Impact** : 10 frappes "Douala" = 10 requêtes avant, 1 requête + 9 Redis hits après.

### 3. `Ad::isFavoritedBy()` — 2N queries → 1 query par requête

**Avant** : Pour une page de 15 annonces avec utilisateur connecté :
```
SELECT COUNT(*) WHERE user_id=X AND ad_id=Y AND type='favorite'   // ×15
SELECT COUNT(*) WHERE user_id=X AND ad_id=Y AND type='unfavorite' // ×15
= 30 queries par page paginée
```

**Après** : Static per-request batch loader :
```
SELECT ad_id, type, COUNT(*) WHERE user_id=X GROUP BY ad_id, type  // ×1
= 1 query, résultats servis depuis static array pour les 14 annonces suivantes
```

**Impact** : -29 queries sur chaque liste paginée pour utilisateurs connectés.

---

## Endpoints & leur stratégie cache actuelle

| Endpoint | Cache | TTL | Store |
|----------|-------|-----|-------|
| `GET /stats/landing` | ✅ `landing:stats` | 30 min | Redis/DB |
| `GET /stats/testimonials` | ✅ `landing:testimonials` | 10 min | Redis/DB |
| `GET /ads` (page 1, guest) | ✅ `ads:feed:guest:first` | 5 min | Redis/DB |
| `GET /ads/search` | ❌ Aucun (Meilisearch handle) | — | — |
| `GET /ads/facets` | ✅ `ads:facets:pgsql` **(nouveau)** | 10 min | Redis/DB |
| `GET /ads/autocomplete` | ✅ `ads:autocomplete:{f}:{q}` **(nouveau)** | 5 min | Redis/DB |
| `GET /property-attributes` | ✅ `property_attributes:all` | 24h | Redis/DB |
| `GET /cities` | ✅ `cities:list:{hash}` | 1h / 5 min | Redis/DB |
| `GET /quarters` | ✅ `quarters:list:{hash}` | 30 min / 5 min | Redis/DB |
| `GET /owner/dashboard` | ✅ `owner:stats:{userId}` | 5 min | Redis/DB |
| `GET /analytics/overview` | ✅ `analytics:overview:{id}:{period}` | 5 min | Redis/DB |
| `GET /boost/packs` | ✅ `boost:packs:active` | 1h | Redis/DB |
| `GET /credits/packages` | ✅ `credits:packages:active` | 1h | Redis/DB |
| `GET /surveys/active` | ✅ `surveys:active:id` | 5 min | Redis/DB |
| `GET /price-heatmap` | ✅ `price_heatmap_{hash}` | 30 min | Redis/DB |

---

## Requêtes déjà bien optimisées

### Eager loading

Tous les endpoints de liste chargent les relations en avance :
```php
// AdSearchController
->with(['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency'])
->withAvg('reviews', 'rating')
->withCount('reviews')

// AdController (liste publique)
->with(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency'])
```

### Indexes DB (migrations déjà appliquées)

- `ad`: index sur `status`, `type_id`, `user_id`, `price`, `created_at`
- `search_alerts`: index composite `(is_active, city_id, type_id)`, `(is_active, user_id)`
- `search_alert_matches`: index sur `(search_alert_id, ad_id)`, `(notified_at)`
- `quarter`: index sur `city_id`

### MatchSearchAlertsForAdJob

Pré-filtrage DB sur `city_id, type_id, quarter_id, price_max/min` avant le chunk PHP → réduit drastiquement les enregistrements chargés côté PHP.

---

## Ce qui n'a PAS besoin de cache

| Endpoint | Raison |
|----------|--------|
| `GET /ads/search` | Meilisearch répond < 20 ms, données doivent être fraîches |
| `GET /ads/{id}` (show) | Single row, PostgreSQL = < 5 ms avec index UUID |
| POST/PATCH/DELETE | Mutations, jamais cacher |
| `GET /ads/{ad}/similar` | Déjà TTL CDN 300s via middleware `cdn.cache` |

---

## Recommandations restantes (non bloquantes)

| # | Action | Impact | Effort |
|---|--------|--------|--------|
| 1 | **Activer `CACHE_STORE=redis` sur VPS** | 🔴 Critique | 5 min |
| 2 | Configurer `maxmemory-policy allkeys-lru` dans `/etc/redis/redis.conf` | 🟡 Moyen | 10 min |
| 3 | Ajouter `php artisan cache:forget ads:facets:pgsql` dans `AdStatusController` (invalidation explicite) | 🟢 Faible | 30 min |
| 4 | Envisager `spatie/laravel-responsecache` pour les pages listing publiques | 🟡 Moyen | 1 jour |
| 5 | Activer Laravel Telescope en staging pour monitorer les slow queries | 🟢 Faible | 1h |
