# KeyHome — Audit Feed Annonces (LISTINGS) — 2026-05-25

> **Mis à jour le 2026-05-25** — Tous les gaps identifiés ont été implémentés et testés. Commit backend+frontend : `da39c6f6` (branche `preprod`).

## Résumé Exécutif

- Le feed est bien architecturé (cursor-based infinite scroll, carousels, skeletons, memo) et s'appuie sur des patterns Airbnb modernes.
- **6 gaps UX** ont été identifiés et **tous corrigés** — dont 2 critiques pour la rétention mobile.
- La grille 2 colonnes sur mobile est conforme à l'usage (Airbnb, Leboncoin) mais à surveiller sur les très petits écrans.
- ~~Le gap le plus impactant : **aucune restauration de position de scroll**~~ → ✅ **Corrigé** : hook `useScrollRestoration` (sessionStorage + TTL 30 min).
- ~~Les carousels horizontaux n'ont pas de skeletons de chargement~~ → ✅ **Corrigé** : 4× `AdCardSkeleton` pendant `isRecommendationsLoading`.

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

### Gap 1 — ✅ Scroll position restaurée au retour arrière

**Problème :** Quand l'utilisateur clique sur une annonce, consulte la fiche, et revient (`Back`), Next.js remet le scroll à 0. L'utilisateur doit re-scroller pour retrouver sa position — NN/G appelle ça du "pogo sticking" et c'est le **principal cause d'abandon** sur les listing pages.

**NN/G (2025) :** "Save scroll position almost always. When users navigate back to the routing page, the page often resets to the top of the list, forcing the user to scroll and scan again."

**✅ Implémenté :** Hook `useScrollRestoration(route, isReady)` créé dans `hooks/useScrollRestoration.ts` :
- Sauvegarde `window.scrollY` dans `sessionStorage` à chaque unmount (clé `kh:scroll:/home`).
- Restaure via `requestAnimationFrame` dès que les données sont disponibles (`isReady = ads.length > 0 || isError`).
- TTL 30 min : entrée expirée ignorée et supprimée automatiquement.
- Intégré dans `home/page.tsx` ligne 255 : `useScrollRestoration('/home', ads.length > 0 || isError)`.

---

### Gap 2 — ✅ Carousels avec skeleton de chargement

**Problème :** Les sections "Recommandé pour vous" et "Récemment consultés" n'affichaient rien pendant le chargement.

**✅ Implémenté :** Dans les 2 instances du carousel "Recommandé pour vous" (`home/page.tsx`) :
- Condition étendue : `{isAuthenticated && (isRecommendationsLoading || recommendations.length > 0)}` — la section reste montée dès que l'utilisateur est auth.
- Pendant `isRecommendationsLoading` : 4× `AdCardSkeleton` avec les mêmes dimensions que les vraies cartes (`minWidth: { xs: 220, md: 280 }`).
- Transition instantanée vers les vraies cartes une fois les données reçues. CLS éliminé.

---

### Gap 3 — ✅ Compteur de résultats affiché

**Problème :** Le feed n'indiquait pas le nombre d'annonces disponibles.

**✅ Implémenté :**
- **Backend** (`AdController::feed()`) : `Cache::remember('ads:feed:total:{type}', 600s)` renvoie le count; exposé via `->additional(['total_approximate' => $total])`.
- **Types** (`types/index.ts`) : `total_approximate?: number` ajouté à `CursorPaginatedResponse<T>`.
- **Frontend** (`home/page.tsx`) : `const totalApproximate = adsData?.pages[0]?.total_approximate` — affiché sous le titre de chaque grille (2 instances) : `"{N} annonces disponibles"` en `caption` gris.

---

### Gap 4 — ✅ Tri du feed implémenté

**Problème :** Le feed `/home` n'offrait aucun tri.

**✅ Implémenté :**
- **Validation** (`AdRequest.php`) : valeurs `newest | price_asc | price_desc` ajoutées au champ `sort`.
- **Backend** (`AdController::feed()`) : `match($sort)` → `orderBy('price')` / `orderByDesc('price')` / défaut boost+date. Le cache guest ne s'applique qu'à `sort=newest` sans filtre.
- **Service** (`ads.service.ts`) : paramètre `sort?: 'newest' | 'price_asc' | 'price_desc'` typé.
- **Frontend** (`home/page.tsx`) : `feedSort` dans le `queryKey`; `Select` + `SortIcon` inline dans les 2 headers de grille (branche selectedCategory + branche default). 3 options : "Plus récentes", "Prix croissant", "Prix décroissant".

---

### Gap 5 — ✅ `sizes` précis sur les carousels

**Problème :** Les `AdCard` dans les carousels utilisaient `sizes="50vw"` alors que les cartes font ~220 px fixe.

**✅ Implémenté :**
- `AdCard.tsx` : nouvelle prop `imageSizes?: string` — remplace le `sizes` par défaut quand fournie. Incluse dans `memo()` comparaison.
- 4 instances carousel dans `home/page.tsx` : `imageSizes="(max-width: 600px) 220px, 280px"` — Next.js Image télécharge désormais la bonne taille (au lieu de 50vw = 360px sur mobile).

### Gap 6 — ✅ Bouton "Retour en haut"

**Problème :** Après 3+ pages chargées (60+ annonces), l'utilisateur n'avait aucun moyen rapide de remonter.

**✅ Implémenté :**
- Listener `scroll` passif : `showBackTop = window.scrollY > 600`.
- `Zoom`-animated MUI `Fab` (taille `small`, `KeyboardArrowUpIcon`) fixé en `position: fixed` bas-droite.
- `bottom: { xs: 80, sm: 32 }` — décalé de 80 px sur mobile pour ne pas masquer la bottom nav.
- `zIndex: 1200` pour passer au-dessus des cartes.
- `onClick: () => window.scrollTo({ top: 0, behavior: 'smooth' })`.

---

## Gap Analysis Matrix (Phase 4)

### Performance

| Check | Statut | Note |
|-------|--------|------|
| N+1 queries éliminées | ✅ | Eager loading + cursor |
| Indexes DB sur clés de filtre | ✅ | Meilisearch |
| Cache strategy (read-heavy) | ✅ | staleTime 2min |
| Images en format moderne (WebP) | ✅ | Cloudflare R2 → WebP |
| `sizes` attribute précis sur carousels | ✅ | `(max-width:600px) 220px, 280px` via prop `imageSizes` |
| Scroll position restaurée | ✅ | `useScrollRestoration` sessionStorage+TTL 30min |
| Layout shift (CLS) carousels | ✅ | 4× AdCardSkeleton pendant `isRecommendationsLoading` |

### Feature Completeness

| Check | Statut | Note |
|-------|--------|------|
| Infinite scroll | ✅ | Cursor-based |
| Filtres par catégorie | ✅ | Pills scrollables |
| Recommandations perso | ✅ | Pour utilisateurs auth |
| Récemment consultés | ✅ | localStorage + backend |
| Skeleton loading principal | ✅ | AdCardSkeleton |
| Skeleton loading carousels | ✅ | 4× AdCardSkeleton (recommendations) |
| Compteur de résultats | ✅ | `total_approximate` backend + UI caption |
| Tri du feed | ✅ | newest / price_asc / price_desc |
| Retour en haut | ✅ | FAB Zoom-animated > 600px scroll |
| Persistance scroll (pogo sticking) | ✅ | useScrollRestoration hook |

---

## Plan d'Action Prioritaire

| # | Action | Sévérité | Statut | Commit |
|---|--------|----------|--------|--------|
| 1 | **Restaurer position scroll au Back** | 🔴 Critique | ✅ DONE | `da39c6f6` |
| 2 | **Skeletons carousels horizontaux** | 🟡 Moyen | ✅ DONE | `da39c6f6` |
| 3 | **Compteur total résultats** | 🟡 Moyen | ✅ DONE | `da39c6f6` |
| 4 | **Tri feed** | 🟡 Moyen | ✅ DONE | `da39c6f6` |
| 5 | **`sizes` précis carousels** | 🟢 Faible | ✅ DONE | `da39c6f6` |
| 6 | **Bouton "Retour en haut"** | 🟢 Faible | ✅ DONE | `da39c6f6` |

---

## Sources & Références

- NN/G — "Designing Scroll Behavior: When to Save a User's Place" (juil. 2025) — https://www.nngroup.com/articles/saving-scroll-position/
- NN/G — "Infinite Scrolling: When to Use It, When to Avoid It" (sept. 2022) — https://www.nngroup.com/articles/infinite-scrolling-tips/
- Design Monks — "7 Best Real Estate Website UX Design Examples" (jan. 2026) — https://www.designmonks.co/blog/real-estate-website-ux-design-examples
- Harpreet Vishnoi — "Product Critique: Zillow Mobile App" (2023) — https://harpreetvishnoi.medium.com/product-critique-zillow-mobile-app-1dd0a1c26fb9
