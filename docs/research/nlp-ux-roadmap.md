# KeyHome — NLP & UX Roadmap (Sourced Research)

> Généré le 2026-05-22. Cocher chaque item au fil de l'implémentation.
> Sources : Zillow IR 2024, Airbnb Engineering, Meilisearch Docs, Algolia UX Research, AppFolio, Tezeract.

---

## Sprint 1 — Quick Wins (1-2 semaines)

### 1.1 NLP Parser Backend

- [x] **LocalNlpParser** : regex prix FCFA/XAF (`\d+\s*(fcfa|xaf|f\b)`) → `price_max`
- [x] **LocalNlpParser** : regex chambres (`(\d)\s*(chambre|pièce|ch\.)`) → `bedrooms`
- [x] **LocalNlpParser** : dict types (`studio`, `garçonnière`, `villa`, `appartement`) → `ad_type`
- [x] **LocalNlpParser** : détection meublé (`meublé`, `équipé`, `avec meubles`) → `furnished=true`
- [x] Brancher `LocalNlpParser` dans `AiSearchServiceInterface::parse()`
- [x] Frontend : si parse retourne des champs structurés → **auto-populate les filtres actifs** (chips)

### 1.2 Meilisearch Synonyms Map

- [x] Synonymes FR-CM définis dans `SyncMeilisearchSettings::buildSynonyms()` (30 groupes : types, équipements, quartiers, transactions)
- [x] Commande Artisan `php artisan meilisearch:sync-settings` enrichie pour pousser synonymes + stop words
- [x] Stop words FR activés dans Meilisearch settings

### 1.3 Listing Quality Score (Bailleur — Wizard)

- [x] Créer `src/lib/listingQuality.ts` : `computeListingQuality(values, photosCount, has3dTour)` → score 0-100
- [x] Composant `<ListingQualityBar />` : barre colorée + score + hint à la prochaine amélioration
- [x] Intégré dans `AdFormWizard` visible dès step 1, mise à jour temps réel
- [x] Messages d'encouragement : hint sur l'action manquante la plus impactante

### 1.4 "Vues récemment" Client

- [ ] Hook `useRecentlyViewed` : sauvegarder les IDs d'annonces vues dans `localStorage` (max 10)
- [ ] Composant `<RecentlyViewedAds />` : carousel horizontal sur homepage
- [ ] Afficher dans la sidebar de la page search (desktop)

### 1.5 Alerte bailleur — Annonce débloquée

- [ ] Backend : event `AdUnlocked` → notification DB au propriétaire
- [ ] Frontend owner dashboard : badge + toast `"Quelqu'un vient de débloquer votre annonce"`
- [ ] Email notification (si préférence activée)

---

## Sprint 2 — Engagement & Personnalisation (2-4 semaines)

### 2.1 Relevance Score dans le Ranking

- [ ] Calculer `relevance_score` dans `AdResource` :
  - CTR : `unlocked_count / max(views_count, 1)`
  - Rating : `reviews_avg_rating / 5`
  - Boost actif : ×1.5
  - Formule : `CTR*40 + rating*30 + boost*30`
- [ ] Ajouter `relevance_score` dans `Ad::toSearchableArray()`
- [ ] Configurer Meilisearch `customRanking: ["desc(relevance_score)"]`

### 2.2 Facet Counts dans l'UI

- [ ] Récupérer `facetDistribution` depuis Meilisearch dans `useSearchFilters`
- [ ] Afficher le count dans l'autocomplete ville : `"Douala (347)"`
- [ ] Afficher le count dans le dropdown type : `"Villa (42)"`
- [ ] Grise les facets avec count = 0

### 2.3 SearchAlert — Améliorations

- [ ] Champ `frequency` sur `SearchAlert` : `immediate | daily | weekly`
- [ ] Afficher le compteur d'annonces matchant au moment de la création de l'alerte
- [ ] Relier aux `PushSubscription` existants pour push notification mobile
- [ ] Page "Mes alertes" : liste + edit + supprimer

### 2.4 Dashboard Bailleur — Métriques

- [ ] Graphique vues/jours (30 derniers jours) sur chaque annonce
- [ ] Taux de conversion : `(contacts + unlocks) / views_count × 100`
- [ ] **Prix relatif au marché** : `votre prix / median_price_in_quarter` → `+12% au-dessus`
- [ ] Classement estimé dans les résultats de recherche (rank tracker)

### 2.5 "Aucun résultat" — Page intelligente

- [ ] Si zéro résultats → suggérer quartiers proches (même ville)
- [ ] Proposer type élargi : `"Pas de studio à Akwa, voir appartements"`
- [ ] Proposer d'élargir le budget : `"Voir studios jusqu'à 80 000 FCFA"`
- [ ] CTA créer une alerte pour être notifié

---

## Sprint 3 — Hybrid Semantic Search (2-4 semaines)

### 3.1 Meilisearch Embedder Multilingue

- [ ] Choisir provider : **Cohere `embed-multilingual-v3.0`** (recommandé FR africain)
  - Alternative : Jina `jina-embeddings-v3` (multilingue, moins cher)
  - Alternative : Mistral embed (bonne qualité FR)
- [ ] Configurer `COHERE_API_KEY` dans `.env`
- [ ] Créer embedder dans Meilisearch settings via Artisan command
- [ ] `documentTemplate` : `"Annonce à {{doc.quarter_name}}, {{doc.city_name}} : {{doc.title}}. {{doc.description}} Type: {{doc.ad_type_name}}. Prix: {{doc.price}} FCFA."`
- [ ] Tester re-indexation complète (surveiller coût tokens)

### 3.2 Requête Hybride

- [ ] Modifier `AiSearchService::buildMeilisearchParams()` :
  - `semanticRatio: 0.5` par défaut
  - `semanticRatio: 0.8` si `strlen(q) > 20` (requête longue = intent sémantique)
  - `semanticRatio: 0.2` si q contient des chiffres (intent facet/prix)
- [ ] A/B test : keyword-only vs hybrid → mesurer CTR et unlock rate

### 3.3 Annonces Similaires

- [ ] Page détail annonce : section `"Annonces similaires"`
- [ ] Utiliser Meilisearch `/similar` ou query par `quartier + type + ±20% prix`
- [ ] Carousel de 6 annonces similaires

---

## Sprint 4 — Features Avancées (1-2 mois)

### 4.1 Recherche par Temps de Trajet

- [ ] Backend : `POST /api/v1/search/isochrone` → `{ origin, max_minutes, mode }`
  - Utiliser ORS (déjà intégré) pour calculer l'isochrone
  - Retourner les IDs d'annonces dans la zone
- [ ] Frontend : input `"À moins de X min de [lieu]"` dans les filtres avancés
- [ ] Afficher isochrone sur la carte Mapbox

### 4.2 Auto-génération Description (LLM)

- [ ] Backend : `POST /api/v1/ads/generate-description` (owner only)
  - Input : `{ type, bedrooms, surface, amenities[], quarter, price }`
  - LLM → description française attractive
- [ ] Bouton `"Générer une description"` dans le wizard step description
- [ ] L'utilisateur peut éditer le résultat avant validation

### 4.3 Personnalisation Comportementale

- [ ] Collecter `AdInteraction` (type: view, click, favorite, unlock) → 3 mois de données
- [ ] Entraîner modèle GBDT (XGBoost) sur données comportementales
- [ ] Features : type préféré, quartiers vus, budget moyen, durée sessions
- [ ] Intégrer scores dans `relevance_score` Meilisearch

### 4.4 NLP Image Search

- [ ] Endpoint `POST /api/v1/search/parse-image` : photo de bien → critères structurés
  - Via Restb.ai API ou OpenAI Vision
  - Extraire : type, standing, jardin, balcon, piscine
- [ ] Frontend : bouton appareil photo dans la barre de recherche

---

## Sources

| Sujet | Source | URL |
|-------|--------|-----|
| NL Search real estate | Zillow IR 2024 | https://investors.zillowgroup.com/…/Zillows-AI-powered-home-search-gets-smarter… |
| ML ranking marketplace | Airbnb Engineering Blog 2019 | https://medium.com/airbnb-engineering/machine-learning-powered-search-ranking-of-airbnb-experiences-110b4b1a0789 |
| Hybrid search setup | Meilisearch Docs | https://meilisearch.com/docs/capabilities/hybrid_search/getting_started |
| Filter UX best practices | Algolia Blog | https://www.algolia.com/blog/ux/search-filter-ux-best-practices |
| NLP real estate use cases | Tezeract.ai | https://tezeract.ai/applications-of-nlp-in-real-estate/ |
| AI search architecture | LinkedIn / Mohamed H. | https://www.linkedin.com/pulse/building-ai-powered-real-estate-search-system-advanced-mohamed-ovele |
| Owner onboarding best practices | AppFolio Blog | https://www.appfolio.com/blog/setting-owners-up-for-success-onboarding |
| Listing quality / photo AI | Restb.ai | https://restb.ai/ |

---

## Légende

- 🔴 P0 — Critique, à faire en Sprint 1
- 🟠 P1 — Important, Sprint 2
- 🟡 P2 — Valeur ajoutée, Sprint 3
- 🟢 P3 — Nice to have, Sprint 4
- ✅ Complété
- 🚧 En cours
