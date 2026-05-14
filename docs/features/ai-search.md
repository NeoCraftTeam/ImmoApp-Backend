# Recherche IA — Langage naturel avec Groq LLM

> **Date :** Mars 2026  
> **Fonctionnalité :** Parsing de requêtes en langage naturel vers des paramètres de recherche structurés  
> **Provider :** Groq (LLM Llama 3.3 70B)

---

## Vue d'ensemble

La **Recherche IA** permet aux utilisateurs de rechercher des annonces immobilières en tapant une requête en langage naturel, comme dans une conversation. Par exemple :

- *« Appartement meublé 2 chambres à Douala pas cher avec parking »*
- *« Villa avec piscine à Bastos moins de 150 000 FCFA »*
- *« Studio à Yaoundé »*

Le système convertit ces requêtes en filtres structurés (ville, type, chambres, prix, etc.) utilisés par l’API de recherche Meilisearch.

---

## Architecture

```
┌─────────────────┐     POST /api/v1/search/parse      ┌──────────────────────┐
│  HeroSearch     │ ─────────────────────────────────► │ NaturalSearchController│
│  (Recherche IA) │     { q: "appartement à Douala" }  │                      │
└─────────────────┘                                    └──────────┬───────────┘
                                                                   │
                                                                   ▼
                                                        ┌──────────────────────┐
                                                        │   AiSearchService     │
                                                        │                      │
                                                        │  1. Cache (24h) ?     │
                                                        │  2. Groq LLM          │
                                                        │  3. Fallback regex    │
                                                        └──────────┬───────────┘
                                                                   │
                                    ┌──────────────────────────────┼──────────────────────────────┐
                                    │                              │                              │
                                    ▼                              ▼                              ▼
                           ┌────────────────┐            ┌────────────────┐            ┌────────────────┐
                           │ Cache (Redis)   │            │ Groq API       │            │ Regex Parser   │
                           │ 24h par query  │            │ llama-3.3-70b  │            │ (fallback)     │
                           └────────────────┘            └────────────────┘            └────────────────┘
```

---

## Configuration

### Variables d'environnement

| Variable | Description | Obligatoire |
|----------|-------------|-------------|
| `GROQ_API_KEY` | Clé API Groq (https://console.groq.com) | Oui pour l'IA |
| `GROQ_MODEL` | Modèle Groq (défaut : `llama-3.3-70b-versatile`) | Non |

### Exemple `.env`

```env
# Recherche IA — Groq
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GROQ_MODEL=llama-3.3-70b-versatile
```

### Comportement sans clé API

Si `GROQ_API_KEY` n'est pas défini, le système utilise automatiquement le **parser regex** (`NaturalSearchRegexParser`). Aucune erreur n'est levée ; la recherche reste fonctionnelle avec des capacités plus limitées.

---

## API

### Endpoint

```
POST /api/v1/search/parse
```

**Rate limit :** 30 requêtes / minute

### Requête

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `q` | string | Oui | Requête en langage naturel (max 300 caractères) |

### Réponse

```json
{
  "original_query": "appartement 3 pièces à Douala moins de 150 000 FCFA avec parking",
  "type_id": "uuid",
  "type_name": "Appartement",
  "city_id": "uuid",
  "city_name": "Douala",
  "quarter_name": "Bastos",
  "bedrooms": 3,
  "price_max": 150000,
  "price_min": null,
  "surface_min": null,
  "has_parking": true,
  "furnished": null,
  "q": null
}
```

| Champ | Type | Description |
|-------|------|-------------|
| `original_query` | string | Requête brute de l'utilisateur |
| `type_id` | string\|null | UUID du type de bien (Appartement, Maison, etc.) |
| `type_name` | string\|null | Nom du type |
| `city_id` | string\|null | UUID de la ville |
| `city_name` | string\|null | Nom de la ville |
| `quarter_name` | string\|null | Nom du quartier |
| `bedrooms` | int\|null | Nombre de chambres/pièces |
| `price_max` | int\|null | Budget max en FCFA |
| `price_min` | int\|null | Budget min en FCFA |
| `surface_min` | int\|null | Surface min en m² |
| `has_parking` | bool\|null | Filtre parking |
| `furnished` | bool\|null | Filtre meublé |
| `q` | string\|null | Mots-clés full-text si critères vagues |

---

## Exemples de requêtes

| Requête utilisateur | Résultat attendu |
|---------------------|------------------|
| `Appartement 3 pièces à Bastos moins de 150 000 FCFA` | type=Appartement, quarter=Bastos, bedrooms=3, price_max=150000 |
| `Villa avec piscine à Douala Bonapriso` | type=Maison/Villa, city=Douala, quarter=Bonapriso |
| `Studio meublé à Yaoundé pas cher` | type=Studio, city=Yaoundé, furnished=true, price_max≈100000 |
| `Terrain à vendre à Cotonou` | type=Terrain, city=Cotonou |
| `quelque chose de vague` | q="quelque chose de vague" (fallback full-text) |

### Règles de parsing (LLM)

- **Prix :** Toujours en FCFA. `150k` → 150000, `1.5M` → 1500000
- **« Pas cher » / « budget serré »** → `price_max` bas (ex. 100000 location)
- **« Haut de gamme » / « luxe »** → `price_min` élevé
- **Villes et quartiers :** Uniquement ceux présents dans le référentiel (DB)

---

## Cache

- **Durée :** 24 heures par requête
- **Clé :** `ai_search:{md5(normalized_query)}`
- **Store :** Configuré via `CACHE_STORE` (Redis en production recommandé)

Une même requête identique renvoie le résultat mis en cache sans nouvel appel à Groq.

---

## Fichiers concernés

| Fichier | Rôle |
|---------|------|
| `app/Services/AiSearchService.php` | Service principal — appel Groq, cache, enrichissement |
| `app/Services/NaturalSearchRegexParser.php` | Parser regex de fallback |
| `app/Http/Controllers/Api/V1/NaturalSearchController.php` | Contrôleur API |
| `keyhome-frontend-next/src/components/ads/HeroSearch.tsx` | UI — onglet « Recherche IA » |
| `keyhome-frontend-next/src/app/search/page.tsx` | Page de recherche — lecture des params URL |
| `tests/Feature/NaturalSearchParseTest.php` | Tests fonctionnels |

---

## Tests

```bash
php artisan test tests/Feature/NaturalSearchParseTest.php
```

Les tests s'exécutent **sans clé Groq** (fallback regex) pour éviter les appels réseau en CI.

---

## Dépannage

### La recherche IA ne renvoie pas de résultats structurés

1. Vérifier que `GROQ_API_KEY` est défini dans `.env`
2. Consulter les logs : `storage/logs/laravel.log` — messages `AiSearchService`
3. En cas d'erreur Groq, le fallback regex est utilisé automatiquement

### Les villes/quartiers ne sont pas reconnus

Le LLM reçoit la liste des villes et quartiers de la base. Vérifier que les données sont bien présentes dans `city` et `quarter`.

### Cache obsolète

Pour vider le cache des recherches IA :

```bash
php artisan cache:clear
```

Ou en production avec Redis, supprimer les clés `ai_search:*`.
