# ADR — Gestion d'état global : Zustand vs React Context

**Date :** 2026-03-28 
**Statut :** Accepté 
**Contexte :** KeyHome Frontend (Next.js 16 / React 19)

---

## Contexte

Le frontend KeyHome utilise actuellement **React Context** pour cinq domaines d'état global :

| Provider | Lignes | État géré | Complexité |
| -------------------- | ------ | -------------------------------------------------------------- | -------------- |
| `AuthProvider` | 697 | user, token, isLoading, isLoggingOut + 6 actions Clerk/Sanctum | Élevée |
| `FavoritesProvider` | 172 | favorites[], favoriteIds + sync localStorage/API | Moyenne |
| `ComparatorProvider` | 91 | items[], isOpen, drawerMode, maxReached | Faible–Moyenne |
| `ThemeProvider` | 157 | choice, mode, toggleTheme | Faible |
| `OwnerThemeProvider` | 39 | mode dérivé du ThemeProvider parent | Très faible |

Les alternatives évaluées sont **Zustand** (store atomique léger) et **Redux Toolkit** (store centralisé avec slices).

---

## Problèmes identifiés avec l'approche Context actuelle

### 1. Re-renders en cascade (`FavoritesProvider`, `ComparatorProvider`)

React Context re-rend **tous les consommateurs** à chaque changement de valeur. `FavoritesProvider` expose un objet `value` contenant le tableau `favorites[]` : chaque ajout/suppression déclenche le re-render de tout composant appelant `useFavorites()`, même s'il n'a besoin que de `isFavorite(id)`.

```tsx
// Actuellement — s'abonne à TOUT le contexte
const { isFavorite } = useFavorites();

// Avec Zustand — s'abonne uniquement à la fonction sélectionnée
const isFavorite = useFavoritesStore((s) => s.isFavorite);
```

### 2. Boilerplate localStorage manuel

`FavoritesProvider` et `ComparatorProvider` réimplémentent manuellement la lecture/écriture localStorage + la gestion des erreurs. Zustand résout ce problème nativement via le middleware `persist`.

### 3. `AuthProvider` : couplage aux hooks Clerk

`AuthProvider` appelle directement `useClerkAuth()`, `useUser()`, `useSignIn()`, `useClerk()` — des hooks React qui **ne peuvent être appelés que dans un composant**. Ce couplage fort rend une migration vers un store externe impossible sans restructurer le service d'authentification.

---

## Décision

### Conserver React Context pour

| Provider | Raison |
| -------------------- | ------------------------------------------------------------------------------------------------ |
| `AuthProvider` | Couplage structurel aux hooks Clerk — migration impossible sans refonte complète du service auth |
| `ThemeProvider` | État mis à jour très rarement (login/logout/changement manuel) — pas de problème de performance |
| `OwnerThemeProvider` | État dérivé du ThemeProvider, 2 consommateurs max |
| `QueryProvider` | Simple wrapper `QueryClientProvider`, aucune logique d'état propre |

### Migrer vers Zustand pour

| Provider | Bénéfice attendu |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `FavoritesProvider` | Middleware `persist` remplace ~40 lignes de code localStorage ; subscriptions granulaires (`isFavorite` uniquement) |
| `ComparatorProvider` | `persist` pour `items[]` ; subscriptions séparées pour `isOpen` vs `items` évitent les re-renders lors de l'ouverture du drawer |

### Redux Toolkit — Rejeté

Redux Toolkit apporterait une complexité disproportionnée (slices, actions, reducers, store configuration) pour un état de taille modeste. Le bundle serait ~3× plus lourd que Zustand (~2 kB gzip). Justifié uniquement si le projet évolue vers une gestion d'état complexe avec time-travel debugging en production.

---

## Plan de migration incrémentale

### Phase 1 — `ComparatorProvider` → `useComparatorStore`

Priorité haute car l'état de l'UI (drawer ouvert/fermé) et l'état des données (items[]) sont mélangés — le drawer s'ouvre sans changer `items[]`, causant un re-render inutile de la `ComparatorBar` flottante.

```ts
// src/stores/comparator.store.ts
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface ComparatorStore {
 items: Ad[];
 isOpen: boolean;
 drawerMode: CompareDrawerMode | null;
 maxReached: boolean;
 add: (ad: Ad) => void;
 remove: (id: string) => void;
 clear: () => void;
 isSelected: (id: string) => boolean;
 setOpen: (v: boolean) => void;
 openDrawer: (mode: CompareDrawerMode) => void;
 clearMaxReached: () => void;
}

export const useComparatorStore = create<ComparatorStore>()(
 persist(
 (set, get) => ({
 items: [],
 isOpen: false,
 drawerMode: null,
 maxReached: false,
 add: (ad) =>
 set((s) => {
 if (s.items.find((a) => a.id === ad.id)) return s;
 if (s.items.length >= COMPARATOR_MAX_ITEMS)
 return { ...s, maxReached: true };
 return { items: [...s.items, ad] };
 }),
 remove: (id) =>
 set((s) => ({ items: s.items.filter((a) => a.id !== id) })),
 clear: () => set({ items: [] }),
 isSelected: (id) => get().items.some((a) => a.id === id),
 setOpen: (v) =>
 set((s) => ({ isOpen: v, drawerMode: v ? s.drawerMode : null })),
 openDrawer: (mode) => set({ isOpen: true, drawerMode: mode }),
 clearMaxReached: () => set({ maxReached: false }),
 }),
 { name: 'keyhome_comparator', partialize: (s) => ({ items: s.items }) }
 )
);
```

### Phase 2 — `FavoritesProvider` → `useFavoritesStore`

La logique de synchronisation API reste dans un hook séparé (`useFavoritesSync`) pour ne pas polluer le store.

```ts
// src/stores/favorites.store.ts
export const useFavoritesStore = create<FavoritesStore>()(
 persist(
 (set, get) => ({
 favorites: [],
 favoriteIds: new Set<string>(),
 // ... actions
 }),
 {
 name: 'keyhome_favorites',
 partialize: (s) => ({ favorites: s.favorites }),
 }
 )
);
```

---

## Conséquences

### Positives

- Suppression de ~60 lignes de code localStorage manuel
- Re-renders granulaires pour `isFavorite` et `isSelected`
- Devtools Zustand disponibles en développement
- `ComparatorBar` ne re-rend plus quand les favoris changent (stores indépendants)

### Négatives / Risques

- Dépendance supplémentaire (`zustand` ~2 kB gzip, déjà dans beaucoup de Next.js apps)
- Migration à faire progressivement — les deux Providers actuels restent compatibles pendant la transition
- Tests des composants consommateurs à mettre à jour (mock du store au lieu du Provider)

### Non-changements

- `AuthProvider` reste identique — aucune action requise
- `ThemeProvider` reste identique — aucune action requise
- TanStack Query reste le gestionnaire d'état **serveur** (cache API) — Zustand gère uniquement l'état **client**

---

## Dépendance à installer

```bash
npm install zustand
```

Pas de peer dependency. Compatible Next.js App Router avec SSR (le middleware `persist` désactive la réhydratation serveur automatiquement).
