# KeyHome — Audit Géolocalisation / ORS / Carte — 22 mai 2026

## Résumé exécutif

- **Implémentation backend solide** : `DirectionsService` + `IsochroneService` avec cache 24 h, negative-cache 5 min, auth Bearer ORS, timeout HTTP configuré.
- **Gap critique côté carte** : le tracé sur la carte est une ligne droite haversine alors que l'ORS calcule déjà la vraie route routière — mais le GeoJSON n'est jamais passé à la carte.
- **Gap performances** : `DirectionsPanel` tire 3 appels ORS parallèles à l'ouverture sans TanStack Query → cache à froid = 3 requêtes simultanées par utilisateur.
- **ORS free tier** : 40 req/min, 2 000 req/jour pour Directions. Avec 3 calls par ouverture de panneau et ~14 utilisateurs simultanés → quota épuisé.
- **2 correctifs prioritaires** (route sur carte + useQuery) débloquent 80 % de la valeur perçue.

---

## MODULE : GEO — OpenRouteService + Mapbox GL JS

### Implémentation actuelle KeyHome

| Couche | Fichier | État |
|--------|---------|------|
| Backend proxy directions | `app/Services/DirectionsService.php` | ✅ Solide |
| Backend proxy isochrone | `app/Services/IsochroneService.php` | ✅ Solide |
| Authentification ORS | `app/Support/OpenRouteServiceAuth.php` | ✅ Solide |
| Contrôleurs | `IsochroneController`, `DirectionsController` | ✅ Validation + 503 propre |
| Cache | Redis/file, TTL 24 h + neg-cache 5 min | ✅ Bon |
| Frontend service | `src/services/geo.service.ts` | ✅ Minimal et correct |
| Panel directions | `src/components/ads/DirectionsPanel.tsx` | ⚠️ Gaps perf |
| Carte | `src/components/ads/AdLocationMap.tsx` | ❌ Ligne droite, pas route ORS |
| Intégration DirectionsPanel→Carte | — | ❌ Inexistante |

---

## Meilleures pratiques ORS trouvées

| Pratique | Source | Priorité |
|----------|--------|----------|
| Utiliser POST `/v2/directions/{profile}/geojson` pour route GeoJSON complète | ORS API docs | HIGH |
| Mettre en cache côté serveur (TTL 24h+ pour routes stables) | Community best practice | ✅ Déjà fait |
| Negative cache sur erreur ORS pour éviter le marteau-piqueur | Community best practice | ✅ Déjà fait |
| Charger un seul profil d'abord (car), lazy-loader les autres | ORS rate limits | HIGH |
| Utiliser TanStack Query avec `staleTime` pour déduplication | React best practice | HIGH |
| Coordonnées arrondies au 3e décimal (±111 m) dans la clé cache | ORS docs | ✅ Déjà fait |
| Afficher la vraie distance routière (pas haversine) | UX best practice | MEDIUM |
| Fallback vers Google Maps si ORS indisponible | UX best practice | ✅ Déjà fait |

---

## Limites ORS (plan Standard gratuit)

| Endpoint | Quota journalier | Quota /minute |
|----------|-----------------|---------------|
| Directions V2 | 2 000 | **40** |
| Isochrones V2 | 500 | 20 |

> **Impact concret** : `computeAll()` dans `DirectionsPanel` déclenche 3 appels Directions simultanés.
> 14 utilisateurs ouvrant le panneau dans la même minute = 42 req/min → 429 Too Many Requests.

---

## Gap Audit

### ❌ Gap 1 — CRITIQUE : Carte affiche ligne droite, pas la route ORS

**Où** : `AdLocationMap.tsx` lignes 303-328

```ts
// Actuel — ligne droite haversine
map.addSource('route-line', {
  type: 'geojson',
  data: {
    type: 'Feature',
    geometry: {
      type: 'LineString',
      coordinates: [
        [userLocation.longitude, userLocation.latitude],
        [displayLng, displayLat],
      ],
    },
  },
});
```

**Problème** : `DirectionsPanel` calcule le GeoJSON routier complet via ORS mais ne le communique jamais à `AdLocationMap`. Les deux composants sont **totalement déconnectés**.

**Fix requis** :
1. Ajouter prop `routeGeojson?: GeoJSON.FeatureCollection | null` sur `AdLocationMap`
2. Quand `routeGeojson` est présent, remplacer la ligne droite par le LineString ORS
3. Faire remonter le premier résultat (voiture) de `DirectionsPanel` vers `AdDetailClient` via état partagé ou callback `onRouteComputed`
4. Mettre à jour la légende : "Trajectoire directe" → "Itinéraire routier (ORS)"

---

### ❌ Gap 2 — HIGH : 3 appels ORS parallèles à l'ouverture du panneau

**Où** : `DirectionsPanel.tsx` fonction `computeAll()` lignes 204-236

```ts
const fetches = PROFILES.slice(0, 3).map((p) =>
  geoService.getDirections(..., p.value).then(res => res.data).catch(() => null)
);
const all = await Promise.all(fetches); // ← 3 appels ORS simultanés !
```

**Fix requis** :
- Charger uniquement `driving-car` au premier ouverture (résultat le plus utile)
- Déclencher `foot-walking` et `cycling-regular` en lazy après 300ms
- Ou utiliser **TanStack Query** avec `staleTime: 86_400_000` (24h, aligné sur le cache backend)

---

### ⚠️ Gap 3 — HIGH : Pas de TanStack Query dans DirectionsPanel

`DirectionsPanel` utilise `useState` + `useCallback` manuels. Conséquences :
- Même requête refaite si l'utilisateur ferme/rouvre le panneau sur la même page
- Pas de déduplication si deux composants demandent le même trajet
- Pas de gestion de `staleTime`

**Fix requis** : migrer vers `useQuery` avec clé `['directions', fromLat, fromLng, toLat, toLng, profile]` et `staleTime: 86_400_000`.

---

### ⚠️ Gap 4 — MEDIUM : Le bannière de distance affiche haversine même quand ORS est disponible

**Où** : `AdLocationMap.tsx` ligne 673

```ts
'Distance calculée en ligne droite. L'emplacement exact est visible car l'annonce est débloquée.'
```

Quand `DirectionsPanel` a calculé la distance routière, il faudrait afficher "~X min en voiture (Xkm par la route)" dans le bannière de la carte.

**Fix requis** : prop optionnelle `roadSummary?: { distance_label: string; duration_label: string; profile: string }` sur `AdLocationMap`, affichée à la place du disclaimer haversine.

---

### ⚠️ Gap 5 — MEDIUM : Pas de retry avec backoff sur timeout ORS backend

`DirectionsService` et `IsochroneService` passent directement au negative-cache sur timeout/erreur sans retry.

**Fix requis** :
```php
$response = $this->http
    ->timeout(12)
    ->retry(2, 500) // ← 2 tentatives, 500ms entre elles
    ->withHeaders([...])
    ->post(...);
```
Laravel HTTP client supporte nativement `->retry(times, sleep_ms)`.

---

### 🟢 Gap 6 — LOW : Incohérence doc OpenAPI TTL directions

`DirectionsController` OpenAPI description : *"Mis en cache 1 h"*
`DirectionsService::CACHE_TTL` = `86_400` (24 h)

**Fix** : corriger le string dans `#[OA\Get(..., description: '...Mis en cache 24 h.')]`.

---

### 🟢 Gap 7 — LOW : Précision clé cache directions (3 décimales = ±111 m)

La clé cache de `DirectionsService` arrondit au 3e décimal (`%.3f`). Cela signifie que deux positions à 100m l'une de l'autre utilisent le même cache — convenable pour un immobilier statique. Pas un bug, mais à documenter.

---

## Plan d'action prioritaire

| # | Action | Sévérité | Effort | Owner |
|---|--------|----------|--------|-------|
| 1 | **Passer `routeGeojson` ORS à `AdLocationMap`** — remplacer ligne droite par route réelle | 🔴 Critique | 2-3h | frontend |
| 2 | **Migrer DirectionsPanel vers `useQuery`** — staleTime 24h, lazy-load profils 2+3 | 🟠 High | 1h | frontend |
| 3 | **Afficher résumé routier dans bannière carte** — prop `roadSummary` | 🟡 Medium | 1h | frontend |
| 4 | **Ajouter `->retry(2, 500)` sur ORS HTTP calls** | 🟡 Medium | 30min | backend |
| 5 | **Corriger doc OpenAPI TTL** `1 h` → `24 h` | 🟢 Low | 5min | backend |

---

## Interopérabilité ORS

| Dimension | Statut | Notes |
|-----------|--------|-------|
| Auth (Bearer header) | ✅ | `OpenRouteServiceAuth` gère le préfixe |
| Timeout HTTP | ✅ | 12s directions, 10s isochrone |
| Negative cache | ✅ | 5 min, évite le marteau-piqueur |
| Rate limit free tier | ⚠️ | 40 req/min Directions — risque si trafic > 14 users/min |
| Plan upgrade | ℹ️ | Collaborative (NGO/academic): 10 000/jour, 60/min |
| On-Premise | ℹ️ | `∞/∞` — option si quotas insuffisants |
| Cameroun coverage | ✅ | OSM data couvre Douala/Yaoundé correctement |
| GeoJSON format V2 | ✅ | `/v2/directions/{profile}/geojson` utilisé |
| Coordonnées `[lng, lat]` | ✅ | Ordre ORS respecté dans les deux services |

---

## Sources & Références

- ORS API Docs: https://openrouteservice.org/dev/#/api-docs
- ORS Plans & Rate Limits: https://openrouteservice.org/plans/
- Mapbox GL JS Performance: https://docs.mapbox.com/help/troubleshooting/mapbox-gl-js-performance/
- ORS Rate Limit Issues: https://ask.openrouteservice.org/t/error-about-the-rate-limit-while-i-am-under-the-quota/7500
- ORS JavaScript SDK: https://github.com/K4ryuu/ors-client
