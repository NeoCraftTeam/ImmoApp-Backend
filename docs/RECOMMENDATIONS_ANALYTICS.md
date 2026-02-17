# 📊 Système de Recommandations & Analytics

> Documentation technique pour les développeurs de l'équipe NeoCraft.

Ce document décrit le système de **recommandations personnalisées** et le **dashboard analytics** intégrés à l'API ImmoApp.

---

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Architecture](#architecture)
3. [Suivi des interactions](#suivi-des-interactions)
4. [Algorithme de recommandation](#algorithme-de-recommandation)
5. [Dashboard Analytics](#dashboard-analytics)
6. [Endpoints API](#endpoints-api)
7. [Intégration Frontend](#intégration-frontend)
8. [Base de données](#base-de-données)
9. [Tests](#tests)

---

## Vue d'ensemble

Le système repose sur une table unique `ad_interactions` qui enregistre toutes les interactions utilisateur avec les annonces. Ces données alimentent deux fonctionnalités :

- **🎯 Recommandations** : Algorithme de scoring pondéré qui propose des annonces pertinentes à chaque utilisateur.
- **📊 Analytics** : Dashboard permettant aux bailleurs et agences de suivre les performances de leurs annonces (inspiré de Facebook Insights / TikTok Analytics).

## Architecture

```
┌──────────────────┐     ┌──────────────────────┐
│  App Mobile/Web  │────▶│   ad_interactions     │
│                  │     │   (tracking events)   │
└──────┬───────────┘     └──────────┬────────────┘
       │                            │
       │ GET /recommendations       │ lecture
       ▼                            ▼
┌──────────────────┐     ┌──────────────────────┐
│ Recommendation   │◀────│  RecommendationEngine │
│ Controller       │     │  (scoring pondéré)    │
└──────────────────┘     └──────────────────────┘
       │
       │ GET /my/ads/analytics
       ▼
┌──────────────────┐
│ AdAnalytics      │
│ Controller       │
│ (métriques)      │
└──────────────────┘
```

## Suivi des interactions

### Types d'interactions

| Type | Description | Debounce | Quand déclencher |
|---|---|---|---|
| `impression` | L'annonce apparaît dans un feed/liste | 30s par user/ad | `onAppear` / `IntersectionObserver` |
| `view` | L'utilisateur ouvre la page détail | 5 min par user/ad | Ouverture de la page détail |
| `favorite` | Ajout aux favoris | — | Clic sur ❤️ |
| `unfavorite` | Retrait des favoris | — | Clic sur ❤️ (toggle) |
| `share` | Partage de l'annonce | Aucun | Clic sur "Partager" |
| `contact_click` | Clic sur "Contacter" | 1 min | Clic sur bouton contact |
| `phone_click` | Clic sur numéro de téléphone | 1 min | Clic sur numéro |
| `unlock` | Déblocage (paiement) | — | Après paiement réussi |
| `search` | Recherche utilisateur | — | Soumission de recherche |

### Debouncing

Le debouncing est géré côté serveur : si une interaction identique (même user, même ad, même type) a été enregistrée dans la fenêtre de temps, elle est ignorée silencieusement (retourne toujours `204`). Aucune logique de debounce n'est requise côté client.

## Algorithme de recommandation

### Scoring pondéré

Chaque annonce candidate reçoit un score de 0 à 100+ calculé comme suit :

| Signal | Poids | Calcul |
|---|---|---|
| **Type match** | ×40 | Correspondance avec les types d'annonces consultés |
| **City match** | ×25 | Correspondance avec les villes préférées |
| **Budget fit** | ×20 | Courbe gaussienne autour du prix moyen consulté |
| **Freshness** | ×10 | Annonces récentes favorisées |
| **Popularity** | ×5 | Nombre de vues sur 30 jours |
| **Boost** | +15 | Bonus pour annonces boostées |

### Temporal decay

Les interactions récentes comptent plus : décroissance exponentielle avec demi-vie de 14 jours.

```
weight = e^(-0.693 × days_ago / 14)
```

### Signal strength

Toutes les interactions n'ont pas le même poids dans le profil utilisateur :

| Interaction | Multiplicateur |
|---|---|
| Déblocage (paiement) | ×3 |
| Favori | ×2 |
| Vue | ×1 |

### Diversity injection

**20% des résultats** sont des annonces « exploration » en dehors du profil de l'utilisateur (type ou budget différent). Cela évite l'effet bulle de filtre.

### Cold start (nouveaux utilisateurs)

Pour les utilisateurs sans historique, le système retourne un mix de :
1. **Trending** (40%) — les annonces les plus vues sur 7 jours
2. **Boosted** (30%) — les annonces boostées
3. **Latest** (30%) — les annonces les plus récentes

### Cache

Les recommandations sont mises en cache **10 minutes** par utilisateur (clé `reco_v2_user_{id}`).

## Dashboard Analytics

### Métriques disponibles

| Métrique | Calcul |
|---|---|
| **Impressions** | Nombre d'apparitions dans les listes |
| **Vues** | Nombre d'ouvertures de la page détail |
| **Favoris** | Nombre d'ajouts aux favoris |
| **Partages** | Nombre de partages |
| **Clics contact** | Nombre de clics sur "Contacter" |
| **Clics téléphone** | Nombre de clics sur le numéro |
| **Déblocages** | Nombre de déblocages (paiements) |
| **Taux de conversion** | `(déblocages / vues) × 100` |
| **Taux d'engagement** | `(favoris + partages + contacts) / impressions × 100` |

### Entonnoir de conversion (single ad)

```
Impressions → Vues → Contacts → Déblocages
    850          210      18          5
         24.7%       8.6%       27.8%
```

### Audience (single ad)

- **Viewers uniques** : nombre de personnes distinctes ayant vu l'annonce
- **Viewers récurrents** : personnes ayant vu plus d'une fois
- **Favorited by** : nombre de personnes ayant mis en favori

### Périodes

Le paramètre `?period=` accepte : `7d`, `30d` (défaut), `90d`.

## Endpoints API

### Tracking (authentification requise)

```http
POST /api/v1/ads/{id}/impression     → 204 (fire & forget)
POST /api/v1/ads/{id}/view           → 204
POST /api/v1/ads/{id}/share          → 204
POST /api/v1/ads/{id}/contact-click  → 204
POST /api/v1/ads/{id}/phone-click    → 204
POST /api/v1/ads/{id}/favorite       → 200 { is_favorited, message }
```

### Recommandations (authentification requise)

```http
GET /api/v1/recommendations

→ 200 {
    data: [ AdResource[] ],
    meta: {
      source: "personalized" | "cold_start",
      algorithm: "weighted_scoring_v2" | "trending_boosted_latest",
      ...
    }
  }
```

### Favoris

```http
GET /api/v1/my/favorites → 200 { data: AdResource[] }
```

### Analytics (authentification requise — bailleurs/agences uniquement)

```http
# Vue d'ensemble (toutes mes annonces)
GET /api/v1/my/ads/analytics?period=30d

→ 200 {
    data: {
      period: "30d",
      totals: { impressions, views, favorites, shares, ... },
      trends: { view: [{ date, count }], ... },
      top_ads: [{ ad_id, title, views, favorites, conversion_rate }]
    }
  }

# Détails d'une annonce
GET /api/v1/my/ads/{id}/analytics?period=30d

→ 200 {
    data: {
      period: "30d",
      ad: { id, title, status },
      totals: { ... },
      daily: [{ date, impressions, views, ... }],
      funnel: { impressions, views, contacts, unlocks, ... },
      audience: { unique_viewers, repeat_viewers, favorited_by }
    }
  }
```

> ⚠️ L'endpoint single-ad retourne `403` si l'annonce n'appartient pas à l'utilisateur authentifié.

## Intégration Frontend

### Résumé des modifications frontend

| Événement | Endpoint à appeler | Moment |
|---|---|---|
| Annonce visible dans la liste | `POST /ads/{id}/impression` | `onAppear` / `IntersectionObserver` |
| Ouverture page détail | `POST /ads/{id}/view` | `initState` / `useEffect` |
| Clic sur ❤️ | `POST /ads/{id}/favorite` | `onTap` |
| Clic sur "Partager" | `POST /ads/{id}/share` | `onTap` |
| Clic sur "Contacter" | `POST /ads/{id}/contact-click` | `onTap` |
| Clic sur numéro tel | `POST /ads/{id}/phone-click` | `onTap` |

### Nouveaux champs dans AdResource

Chaque annonce retournée par l'API inclut désormais :

```json
{
  "is_favorited": true,
  "view_count": 42
}
```

### Recommandations

- Appeler `GET /recommendations` pour le feed principal
- Le champ `meta.source` indique l'algorithme utilisé (`personalized` ou `cold_start`)

### Fire & forget

Les appels de tracking (`impression`, `view`, `share`, etc.) retournent `204 No Content`. Ils peuvent être envoyés en fire-and-forget (pas besoin d'attendre la réponse). En cas d'échec réseau, l'appel peut simplement être ignoré.

## Base de données

### Table `ad_interactions`

```sql
CREATE TABLE ad_interactions (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ad_id UUID REFERENCES ads(id) ON DELETE CASCADE,
  type VARCHAR(50) NOT NULL,
  metadata JSONB DEFAULT NULL,
  created_at TIMESTAMP NOT NULL
);

-- Index pour les requêtes de profil
CREATE INDEX idx_interactions_user_type ON ad_interactions(user_id, type, created_at);
-- Index pour les requêtes analytics
CREATE INDEX idx_interactions_ad_type ON ad_interactions(ad_id, type, created_at);
```

### Fichiers clés

| Fichier | Rôle |
|---|---|
| `app/Models/AdInteraction.php` | Modèle Eloquent + constantes de type |
| `app/Services/RecommendationEngine.php` | Moteur de scoring + cold start |
| `app/Http/Controllers/Api/V1/AdInteractionController.php` | Endpoints de tracking |
| `app/Http/Controllers/Api/V1/AdAnalyticsController.php` | Dashboard analytics |
| `app/Http/Controllers/Api/V1/RecommendationController.php` | Endpoint recommandations |
| `database/migrations/2026_02_15_150000_create_ad_interactions_table.php` | Migration |

## Tests

```bash
# Tous les tests
php artisan test

# Tests recommandations uniquement
php artisan test --filter=RecommendationTest

# Tests analytics uniquement
php artisan test --filter=AdAnalyticsTest
```

### Couverture des tests

- ✅ Tracking : debounce views, impressions, contacts, phone clicks
- ✅ Share : pas de debounce (chaque clic compte)
- ✅ Favoris : toggle on/off/on, liste des favoris
- ✅ Recommandations : cold start, personnalisé, authentification
- ✅ Analytics : overview, single ad, période, autorisation ownership
- ✅ Entonnoir de conversion + audience analysis

---

*Documentation générée le 15 février 2026 — équipe NeoCraft.*
