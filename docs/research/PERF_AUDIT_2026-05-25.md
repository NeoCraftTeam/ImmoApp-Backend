# KeyHome — Audit Performance — 25 Mai 2026

## Résumé exécutif

- FCP/LCP déjà bien optimisés côté frontend (`priority`, `blurDataURL`, `prefetch` hover) mais deux gaps critiques sur `fetchPriority` et le cache TTL de l'image optimizer
- Les indexes DB existants (`2026_05_02`) couvrent les scans `ad` principaux, mais `search_alerts`, `search_alert_matches` et `quarter` n'étaient pas indexés
- `MatchSearchAlertsForAdJob` chargeait **toutes** les alertes actives en PHP — O(n) sur 10 k+ alertes
- Images backend : 3 conversions WebP (placeholder 20px, thumb 480 px, large 1280 px) — aucun gap
- Meilisearch : filtres composite `city_id + type + transaction_type + relevance_score` déjà en place (audit NLP mai 2026)

---

## Module: LCP / FCP (Next.js Frontend)

### Implémentation actuelle ✅
| Élément | État |
|---------|------|
| `next/image` avec `priority` sur image[0] | ✅ |
| `blurDataURL` base64 20px WebP | ✅ |
| `formats: ['image/avif', 'image/webp']` | ✅ |
| `deviceSizes` taillé mobile (390, 640…) | ✅ |
| `optimizePackageImports` MUI | ✅ |
| Prefetch route sur hover/touch | ✅ |
| Skeleton `loading.tsx` | ✅ |

### Gaps corrigés
| Gap | Fix appliqué |
|-----|-------------|
| `AdCard.tsx` manquait `fetchPriority="high"` sur image[0] | Ajouté `fetchPriority={currentImage === 0 ? 'high' : 'auto'}` |
| `OwnerAdCard.tsx` sans `priority` ni `fetchPriority` | Ajouté `priority` + `fetchPriority="high"` |
| `minimumCacheTTL` absent — images R2 re-traitées toutes les 60 s | `minimumCacheTTL: 604800` (7 jours) |
| Pas de `contentDispositionType` | `contentDispositionType: 'inline'` |

### Best practices 2025 (sources crawlées)
- LCP cible ≤ 2,5 s — FCP ≤ 1,8 s (Google Core Web Vitals)
- `priority` + `fetchPriority="high"` = double garantie sur l'image above-the-fold
- `minimumCacheTTL` élevé critique pour les images immuables sur CDN/R2

---

## Module: Base de données PostgreSQL

### Indexes existants (avant cet audit)
```
ad: status+type_id, user_id+status, status+price, status+created_at
ad_interactions: user_id+type+created_at, ad_id+type, type+created_at
payments: gateway+transaction_id, user_id+status+created_at, status+created_at
tentative_reservations: status+slot_date
lease_contracts: lease_end
login_histories: user_id+successful+created_at
```

### Gaps corrigés — migration `2026_05_25_140000`
| Table | Index | Raison |
|-------|-------|--------|
| `search_alerts` | `(is_active, city_id, type_id)` | Pre-filter `MatchSearchAlertsForAdJob` |
| `search_alerts` | `(is_active, user_id)` | Filtre propriétaire de l'annonce |
| `search_alert_matches` | `(user_id, digest_sent_at)` | `SendSearchAlertDigestJob` query |
| `search_alert_matches` | `(search_alert_id, digest_sent_at)` | Lookup par alerte |
| `quarter` | `(city_id)` | JOIN constant dans toutes les recherches |
| `ad` | `(quarter_id, status)` | Filtrage par quartier |
| `ad` | `(type_id, status, price)` | Combo filtrage type + fourchette prix |

### Best practices PostgreSQL 2025
- Index composite : ordre colonnes = ordre `WHERE` (égalité en premier, range en dernier)
- `CREATE INDEX CONCURRENTLY` pour éviter le verrou — Laravel l'utilise automatiquement en migration
- Partial indexes conseillés pour colonnes booléennes (`is_active = true`) — à envisager si table dépasse 1 M lignes

---

## Module: SearchAlerts — Matching

### Problème initial
`MatchSearchAlertsForAdJob` chargeait **toutes** les alertes actives via `chunkById(500)` — uniquement un filtre `user_id !=` côté DB. Le PHP faisait ensuite `matchesAd()` sur chaque alerte.

**Impact :** avec 10 000 alertes actives, le job charge ~10 000 lignes pour souvent n'en matcher que 10-50.

### Fix appliqué
Pre-filtrage DB ajouté avant le chunk :
```php
->when($cityId, fn($q) => $q->where(fn($q2) => $q2->whereNull('city_id')->orWhere('city_id', $cityId)))
->when($typeId, fn($q) => $q->where(fn($q2) => $q2->whereNull('type_id')->orWhere('type_id', $typeId)))
->when($quarterId, fn($q) => $q->where(fn($q2) => $q2->whereNull('quarter_id')->orWhere('quarter_id', $quarterId)))
->when($price !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('price_max')->orWhere('price_max', '>=', $price)))
->when($price !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('price_min')->orWhere('price_min', '<=', $price)))
```

**Chunk réduit :** 500 → 200 lignes (adapté à la charge réduite post-filtrage).

**Gain estimé :** réduction de 80-95 % des lignes chargées en PHP sur une base de 10 k+ alertes avec répartition géographique normale.

---

## Module: Images backend (Spatie Media Library)

### Conversions existantes ✅
| Conversion | Dimensions | Format | Qualité | Queue |
|-----------|-----------|--------|---------|-------|
| `placeholder` | 20×20 max | WebP | 30 | Sync |
| `thumb` | 480×320 max | WebP | 78 | Async |
| `large` | 1280×854 max | WebP | 82 | Async |

Aucun gap identifié. Les 3 conversions sont utilisées correctement dans `AdCard.tsx` (`thumb` pour la liste, `large` pour le détail).

---

## Module: Meilisearch / Recherche filtrée

### État ✅ (voir audit NLP mai 2026)
- Filterable attributes : `city_id`, `transaction_type`, `relevance_score`, `status`, `price`, `bedrooms`, `surface_area`, `type_id`
- Ranking composé : `relevance_score` desc + `words` + `exactness`
- Embedder Cohere multilingue conditionnel
- Requête hybride adaptative (ratio sémantique selon longueur query)

### Recommandations non implémentées
- **Index de recherche séparé** pour les annonces `AVAILABLE` uniquement — évite de filtrer `status=available` sur chaque requête Meilisearch
- **Facet counts** déjà implementés (ville + type) ✅

---

## Plan d'action prioritaire

| # | Action | Sévérité | Effort | Statut |
|---|--------|----------|--------|--------|
| 1 | Index `search_alerts (is_active, city_id, type_id)` | 🔴 | 30 min | ✅ Livré |
| 2 | Index `search_alert_matches (user_id, digest_sent_at)` | 🔴 | 30 min | ✅ Livré |
| 3 | Pre-filtrage DB dans `MatchSearchAlertsForAdJob` | 🔴 | 1h | ✅ Livré |
| 4 | `fetchPriority="high"` sur `AdCard` + `OwnerAdCard` | 🟡 | 15 min | ✅ Livré |
| 5 | `minimumCacheTTL: 604800` dans `next.config.ts` | 🟡 | 5 min | ✅ Livré |
| 6 | Index `quarter (city_id)` + `ad (quarter_id, status)` | 🟡 | 15 min | ✅ Livré |
| 7 | Index Meilisearch dédié `available` uniquement | 🟢 | 2h | ⬜ Backlog |
| 8 | Partial index PG `WHERE is_active = true` sur search_alerts | 🟢 | 30 min | ⬜ Backlog (si >1M lignes) |

---

*Sources : eastondev.com/blog/nextjs-core-web-vitals, mydbops.com/blog/postgresql-indexing-best-practices-guide, medium.com/cubbit/optimizing-postgresql-queries*
