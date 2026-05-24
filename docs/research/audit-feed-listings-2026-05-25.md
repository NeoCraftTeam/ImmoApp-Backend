# KeyHome — Audit Feed Annonces (LISTINGS) — 2026-05-25

## Résumé Exécutif

- Le feed est bien architecturé (cursor-based infinite scroll, carousels, skeletons, memo) et s'appuie sur des patterns Airbnb modernes.
- **5 gaps UX concrets** ont été identifiés, dont 2 critiques pour la rétention mobile.
- La grille 2 colonnes sur mobile est conforme à l'usage (Airbnb, Leboncoin) mais à surveiller sur les très petits écrans.
- Le gap le plus impactant : **aucune restauration de position de scroll** après retour depuis une fiche — confirmé critique par NN/G (juillet 2025).
- Les carousels horizontaux (recommandations, récemment consultés) **n'ont pas de skeletons de chargement**.

---

## MODULE : LISTINGS — Feed & AdCard

### Implémentation KeyHome actuelle

| Composant | Fichier | Statut |
|-----------|---------|--------|
| Page feed home | `app/(dashboard)/home/page.tsx` | ✅ Complet |
| AdCard | `components/ads/AdCard.tsx` | ✅ Complet |
| Skeleton | `components/ads/AdCardSkeleton.tsx` | ✅ Présent |
| Hook recently viewed | `hooks/useRecentlyViewed.ts` | ✅ Complet |
| Backend feed | `GET /api/v1/ads/feed` (cursor) | ✅ Cursor-based |

**Stack :** Next.js 16 + React Query + Framer Motion + MUI + Meilisearch

**Points forts déjà en place :**
- Cursor-based infinite scroll (pas de OFFSET/COUNT coûteux) ✅
- Sentinel `IntersectionObserver` avec `rootMargin: 300px` ✅
- `memo()` avec comparaison `id + updated_at` ✅
- Préchargement route sur hover/touchstart ✅
- Ratio image 3:2 (66.67%) — standard Airbnb ✅
- Swipe tactile images + keyboard nav WCAG ✅
- `touchAction: pan-x pan-y` (fix du bug scroll horizontal mobile) ✅
- `blur` placeholder sur toutes les images ✅
- Badges : sponsorisé, statut, KeyScore, rating ✅
- Grille responsive : `xs:6` (2 col), `md:4` (3 col), `lg:3` (4 col) ✅
- Carousels horizontaux avec `mx:{xs:-2}` pleine largeur mobile ✅
- `staleTime: 2min` + `refetchOnWindowFocus: false` ✅

---

## Meilleures Pratiques Expertes Trouvées

| Pratique | Source | Priorité |
|----------|--------|----------|
| Sauvegarder la position de scroll lors du retour arrière | NN/G (juil. 2025) | 🔴 Critique |
| Afficher le nombre total de résultats | Zillow, Redfin, designmonks.co | 🟡 Moyen |
| Skeletons sur les carousels horizontaux | Best practice React Query | 🟡 Moyen |
| Tri des résultats dans le feed (récent, prix ↑↓) | Zillow critique (Medium, 2023) | 🟡 Moyen |
| Bouton "Retour en haut" après plusieurs pages chargées | NN/G — interaction cost | 🟢 Faible |
| Map/Liste toggle unifié dans le feed home | Zillow critique | 🟢 Faible |

---

## Analyse des Gaps

### Gap 1 — 🔴 Scroll position perdu au retour arrière

**Problème :** Quand l'utilisateur clique sur une annonce, consulte la fiche, et revient (`Back`), Next.js remet le scroll à 0. L'utilisateur doit re-scroller pour retrouver sa position — NN/G appelle ça du "pogo sticking" et c'est le **principal cause d'abandon** sur les listing pages.

**NN/G (2025) :** "Save scroll position almost always. When users navigate back to the routing page, the page often resets to the top of the list, forcing the user to scroll and scan again."

**Implémentation actuelle :** `useInfiniteQuery` avec `staleTime: 2min` conserve les données en cache, mais le scroll Y de la page est perdu à la navigation.

**Fix recommandé :** Utiliser `useScrollRestoration` ou sauvegarder/restaurer `window.scrollY` dans `sessionStorage` via un hook.

---

### Gap 2 — 🟡 Carousels sans skeleton de chargement

**Problème :** Les sections "Recommandé pour vous" et "Récemment consultés" n'affichent rien pendant le chargement des données. Cela cause un **layout shift** visible : la section apparaît d'un coup après le chargement, décalant le contenu en dessous.

**Impact :** CLS (Cumulative Layout Shift) dégradé, mauvaise expérience sur connexion lente (fréquente au Cameroun).

**Fix recommandé :** Ajouter des `AdCardSkeleton` dans les carousels pendant `isLoading` (identique à ce qui est fait pour la grille principale).

---

### Gap 3 — 🟡 Aucun compteur de résultats

**Problème :** Le feed affiche "Annonces récentes" sans indiquer combien d'annonces sont disponibles. Zillow, Redfin, et Realtor affichent tous un compteur ("1 247 annonces à Douala").

**Valeur UX :** Réduit l'anxiété de l'utilisateur ("est-ce qu'il y a vraiment des biens ici ?"), signal de confiance.

**Fix recommandé :** Exposer `total_count` dans la réponse `feed` et l'afficher sous le titre de section.

---

### Gap 4 — 🟡 Aucun tri dans le feed principal

**Problème :** Le feed home n'offre pas de tri. Les experts (designmonks.co, NN/G) indiquent que les utilisateurs s'attendent à pouvoir trier par : prix croissant/décroissant, date de publication, pertinence.

**Note :** La page `/search` a probablement ce tri — mais le feed `/home` non.

**Fix recommandé :** Ajouter un sélecteur de tri simple (2-3 options) à côté du titre "Annonces récentes".

---

### Gap 5 — 🟢 Pas de bouton "Retour en haut" après scroll profond

**Problème :** Après 3+ pages chargées (60+ annonces), l'utilisateur n'a aucun moyen rapide de remonter en haut (catégories, hero search). 

**Fix recommandé :** FAB "↑ Haut" apparaissant après `scrollY > 600px` (déjà utilisé sur d'autres pages ?).

---

## Gap Analysis Matrix (Phase 4)

### Performance

| Check | Statut | Note |
|-------|--------|------|
| N+1 queries éliminées | ✅ | Eager loading + cursor |
| Indexes DB sur clés de filtre | ✅ | Meilisearch |
| Cache strategy (read-heavy) | ✅ | staleTime 2min |
| Images en format moderne (WebP) | ✅ | Cloudflare R2 → WebP |
| `sizes` attribute précis sur carousels | ⚠️ | `50vw` mais cartes font 220px fixe |
| Scroll position restaurée | ❌ | Gap critique |
| Layout shift (CLS) carousels | ❌ | Pas de skeleton |

### Feature Completeness

| Check | Statut | Note |
|-------|--------|------|
| Infinite scroll | ✅ | Cursor-based |
| Filtres par catégorie | ✅ | Pills scrollables |
| Recommandations perso | ✅ | Pour utilisateurs auth |
| Récemment consultés | ✅ | localStorage + backend |
| Skeleton loading principal | ✅ | AdCardSkeleton |
| Skeleton loading carousels | ❌ | Gap |
| Compteur de résultats | ❌ | Gap |
| Tri du feed | ❌ | Gap |
| Retour en haut | ❌ | Gap faible |
| Persistance scroll (pogo sticking) | ❌ | Gap critique |

---

## Plan d'Action Prioritaire

| # | Action | Sévérité | Effort | Fichiers |
|---|--------|----------|--------|---------|
| 1 | **Restaurer position scroll au Back** | 🔴 Critique | ~2h | `home/page.tsx` + hook `useScrollRestoration` |
| 2 | **Skeletons carousels horizontaux** | 🟡 Moyen | ~30min | `home/page.tsx` |
| 3 | **Compteur total résultats** | 🟡 Moyen | ~1h | backend `feed` + `home/page.tsx` |
| 4 | **Tri feed** | 🟡 Moyen | ~2h | `home/page.tsx` + backend query |
| 5 | **`sizes` précis carousels** | 🟢 Faible | ~15min | `home/page.tsx` wrapper |
| 6 | **Bouton "Retour en haut"** | 🟢 Faible | ~30min | `home/page.tsx` |

---

## Sources & Références

- NN/G — "Designing Scroll Behavior: When to Save a User's Place" (juil. 2025) — https://www.nngroup.com/articles/saving-scroll-position/
- NN/G — "Infinite Scrolling: When to Use It, When to Avoid It" (sept. 2022) — https://www.nngroup.com/articles/infinite-scrolling-tips/
- Design Monks — "7 Best Real Estate Website UX Design Examples" (jan. 2026) — https://www.designmonks.co/blog/real-estate-website-ux-design-examples
- Harpreet Vishnoi — "Product Critique: Zillow Mobile App" (2023) — https://harpreetvishnoi.medium.com/product-critique-zillow-mobile-app-1dd0a1c26fb9
