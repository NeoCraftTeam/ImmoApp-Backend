# Spec — Chat « statique comme WhatsApp » + temps réel (web, Phase 1)

- **Date** : 2026-08-22
- **Périmètre** : `keyhome-frontend-next` uniquement (visitor `(dashboard)/messages` **et** owner `(owner)/owner/messages`). Mobile Expo hors scope.
- **Approche retenue** : A — app-shell persistant. Temps réel : Option 1 (web pur + vérification config, aucun changement backend dans cette phase).
- **Phase 2 (différée, séquencée par l'utilisateur)** : médias — avatars lents + photos de la **page détail annonce**.

---

## 1. Objectif

Le chat doit se comporter comme WhatsApp Web : ouvrir la liste des messages n'affiche **aucun** chargement de composant ; cliquer une conversation ouvre le fil **instantanément** ; naviguer inbox↔conversation ne remonte rien (scroll liste, brouillons, position conservés) ; la page suivante de messages se précharge **avant** qu'on l'atteigne ; le temps réel est fiable et mis en avant. Zéro flash, zéro spinner, zéro bug.

## 2. Diagnostic (root cause)

La couche données est **déjà** solide : persistance chiffrée 7 j (`PersistQueryClientProvider`), messages `staleTime` 23 h, prefetch au survol, envoi optimiste, temps réel complet (Echo/Reverb, 9 events, listeners montés au niveau layout donc survivant à la navigation). Le problème n'est **pas** un manque de cache mais **5 causes visuelles** :

1. **`(dashboard)/template.tsx`** (et miroir owner) : Next remonte le template à **chaque** navigation → fondu 0,3 s + démontage/remontage de tout le sous-arbre.
2. **`/messages` et `/messages/[uuid]` montent chacun leur propre `KeyHomeChatBox`** (`page.tsx:14`, `[uuid]/page.tsx:22-26`) → changer de conversation remonte la liste + le fil (perte scroll/brouillon, effets rejoués).
3. **Pas de `loading.tsx` chat** → le skeleton d'annonces `(dashboard)/loading.tsx` (nearest Suspense) flashe en entrant dans une conversation.
4. **Spinner deep-link** : `KeyHomeChatBox` affiche `ChatLoadingState` = `CircularProgress` plein volet quand la conversation vient de la requête de repli `['conversation-single']`.
5. **`PersistQueryClientProvider` non gaté sur `isRestoring`** → au rechargement à froid, les enfants rendent contre un cache vide au 1ᵉʳ frame (flash skeleton) ; `useConversations` a `refetchOnMount: 'always'` → barre `isFetching` ré-affichée à chaque montage.

## 3. Périmètre

**Inclus (Phase 1) :**
- Shell persistant chat (visitor + owner).
- Suppression des fondus/remontages pour le chat, sans régresser les autres pages.
- Suppression des 5 flashs (spinner, skeleton d'annonces, barre isFetching, flash cache froid).
- Prefetch « page 2 avant l'arrivée » (messages anciens auto + 1ʳᵉ page des top-N conversations + `router.prefetch` des lignes).
- Préservation scroll liste + **brouillons par conversation**.
- Temps réel : vérification/rapport de `BROADCAST_CONNECTION` en prod (lecture seule).

**Exclus :**
- Tout changement backend Laravel (logging des broadcasts avalés → tâche séparée).
- Médias (avatars, galerie page détail annonce) → Phase 2.
- Mobile Expo.

## 4. Architecture cible

```
(dashboard)/
  layout.tsx          persiste (déjà). Héberge PageTransition (rendu conscient du pathname).
  template.tsx        ▶ SUPPRIMÉ (cause du remontage global)
  loading.tsx         inchangé (ne s'applique plus au chat car le shell ne suspend plus)
  messages/
    layout.tsx        ▶ NOUVEAU (client). Monte <ChatShell/> UNE fois.
    page.tsx          ▶ coquille : reste server component, garde `metadata`, rend `null`
    [uuid]/
      page.tsx        ▶ coquille : server component, `metadata` noindex, rend `null`
(owner)/
  template.tsx        ▶ SUPPRIMÉ
  owner/messages/
    layout.tsx        ▶ NOUVEAU (miroir)
    page.tsx          ▶ coquille
    [uuid]/page.tsx   ▶ coquille
```

**Principe clé** : le rendu du chat migre des `page.tsx` vers `messages/layout.tsx`. Un `layout` **persiste** entre ses segments enfants (`/messages` ↔ `/messages/[uuid]`) **à condition qu'aucun `template` remontant ne le surplombe** — d'où la suppression de `(dashboard)/template.tsx`. Le layout lit la conversation active via le segment d'URL, pas via une prop de page.

## 5. Changements fichier par fichier

### 5.1 `(dashboard)/messages/layout.tsx` — NOUVEAU (client)

Rend un `<Suspense>` autour d'un `<ChatShell backHref="/home" />`. `ChatShell` (client) :
- `const segment = useSelectedLayoutSegment();` → `activeConversationId = segment ?? undefined` (segment vaut `null` sur l'inbox, l'uuid sur `/messages/[uuid]`).
- `const draft = useSearchParams().get('draft') ?? undefined;`
- Rend `<KeyHomeChatBox activeConversationId={activeConversationId} initialDraft={draft} backHref="…" />`.
- `useSearchParams()` impose une frontière `<Suspense>` (sinon warning `next build`) → d'où le Suspense dans le layout, fallback = scaffold shell (voir 5.5).

Owner : idem avec `backHref="/owner/dashboard"`, thème owner (via `OwnerChatBox`).

### 5.2 `page.tsx` + `[uuid]/page.tsx` — coquilles

- `messages/page.tsx` : reste **server component**, conserve son export `metadata` (title « Messages », noindex), `export default function () { return null; }`. Le layout rend l'UI.
- `messages/[uuid]/page.tsx` : devient **server component** (plus besoin de `'use client'` : le layout lit `?draft=`), ajoute `metadata` noindex, `return null`. Le `use(params)` disparaît (l'uuid vient du segment côté layout).

Un `page.tsx` reste obligatoire pour que la route soit adressable — d'où les coquilles plutôt que la suppression.

### 5.3 Suppression `template.tsx` + `PageTransition` conscient du pathname

- Supprimer `(dashboard)/template.tsx` et `(owner)/template.tsx`.
- `components/ui/layout/PageTransition.tsx` : lire `usePathname()`.
  - Si pathname correspond au chat (`/messages` ou `/owner/messages`, préfixe) → rendre `children` **sans** `motion.div` (statique).
  - Sinon → `motion.div` **keyé par le pathname** avec `initial={{opacity:0,y:12}} animate={{opacity:1,y:0}} transition={{duration:0.3, ease:[0.22,1,0.36,1]}}` (valeurs de l'ancien template), respectant `useReducedMotion()`.
- Effet net : les pages non-chat conservent **un** fondu par navigation (on supprime au passage le double-fondu template+PageTransition qui les affectait) ; le chat devient statique. Comportement de remontage des pages non-chat inchangé (le key par pathname reproduit le remontage que faisait le template).

### 5.4 `KeyHomeChatBox.tsx` — prop contrôlée, suppression spinner, brouillons hoistés

- **Prop contrôlée** : remplacer `initialActiveConversationId` (lu une fois) par `activeConversationId` (contrôlé, relu à chaque render depuis le segment). Quand il change, le volet fil change **sans** remonter la liste ni le shell. `ChatWindow` est keyé par `conversation.uuid` (un fil = une query messages distincte, remontage attendu et correct ; la liste et le chrome, eux, persistent).
- **Suppression du spinner deep-link** : retirer `ChatLoadingState`/`CircularProgress` (lignes ~100-106, 164-165, 224). Nouveau comportement :
  1. amorcer la conversation active depuis le cache liste (`cachedConversation`) si présente ;
  2. sinon, garder la requête de repli `['conversation-single']` mais rendre le **scaffold du fil** (header depuis les données minimales connues + bulles skeleton) pendant le fetch, jamais un spinner centré.
- **Brouillons par conversation** : introduire un store léger `useChatDrafts` (Map en mémoire de module, clé = uuid) pour que le texte en cours survive au remontage de `ChatWindow` lors d'un switch de conversation (comportement WhatsApp). `initialDraft` (depuis `?draft=`) amorce le draft de la conversation ciblée s'il est vide.

### 5.5 Gate `isRestoring` scopé au chat (dans `ChatShell`, pas `QueryProvider`)

- Ne **pas** gater toute l'app (retarderait le 1ᵉʳ paint partout).
- `QueryProvider` **inchangé** : `useIsRestoring()` (de `@tanstack/react-query-persist-client`) est déjà disponible partout sous le provider existant.
- Dans `ChatShell` : tant que `useIsRestoring()` est `true`, rendre le **scaffold shell** (sidebar + lignes skeleton dimensionnées comme le rendu final, header vide) → zéro layout shift, puis les données remplissent. N'affecte que le 1ᵉʳ chargement à froid (les navigations douces ont déjà le cache en mémoire).

### 5.6 `useConversations.ts` — refetch + prefetch

- `refetchOnMount: 'always'` → `true` (ligne ~43) : refetch seulement si périmé, plus à chaque montage.
- Garder le prefetch top-N (lignes ~67-77) mais s'assurer qu'il précharge aussi la **1ʳᵉ page de messages** de ces conversations (pas seulement les métadonnées) → ouverture instantanée.
- Barre `isFetching` : masquée dès que `data` existe (revalidation silencieuse) — appliqué dans `ConversationList` et `ChatWindow`.

### 5.7 `ChatWindow.tsx` — auto-loadMore + barre isFetching

- Remplacer le déclenchement **manuel** « Messages précédents » par un auto-`loadMore()` : sentinelle en haut de la liste virtualisée (IntersectionObserver) **ou** détection du premier index visible via le virtualizer, déclenchant quand on est à ~1 hauteur d'écran du haut. Garde-fous : uniquement si `hasNextPage && !isFetchingNextPage`. Conserver le bouton en repli accessible.
- Masquer la barre `isFetching` (800-817) quand des messages sont déjà affichés.
- `isLoading` skeleton (704-714) : conservé uniquement pour le tout premier fetch sans cache.

### 5.8 `ConversationItem.tsx` — router.prefetch

- Ajouter `router.prefetch('/messages/[uuid]')` (résolu) au survol desktop (déjà un prefetch de query au survol) et/ou à l'apparition (IntersectionObserver) pour les lignes visibles. Passer `scroll={false}` au `router.push` de navigation (le shell est `overflow:hidden`, pas de scroll document, mais garde-fou).

### 5.9 Miroir owner

Répliquer 5.1–5.8 pour `(owner)/owner/messages/*` et `(owner)/template.tsx`. `OwnerChatBox` enveloppe déjà `KeyHomeChatBox` (thème owner, `basePath="/owner/messages"`, `backHref="/owner/dashboard"`) → la prop contrôlée traverse.

## 6. Temps réel (Option 1)

- **Aucun changement de code backend** dans cette phase.
- **Vérification** : lire la valeur effective de `BROADCAST_CONNECTION` en prod (doit être `reverb` ; défaut `config/broadcasting.php:18` = `'null'` → WS muets en silence si mal configuré). Rapport à l'utilisateur ; si ≠ `reverb`, correctif = variable d'environnement (ops), hors code.
- **Client** : le temps réel web est déjà robuste (listeners au niveau layout survivant à la navigation, 9 events, singleton Echo). Le shell persistant **renforce** ceci : `useChatChannel` (par conversation) vit dans `ChatWindow` et se réabonne au switch (correct) ; `ChatNotificationListener` (niveau layout) reste monté.
- **Différé (tâche backend séparée)** : logger les échecs de broadcast aujourd'hui avalés silencieusement (`MessageService`/`ConversationService` try/catch). Touche le repo Laravel + `quality.sh` → hors Phase 1.

## 7. Cas limites

- **Mobile (list ↔ thread plein écran)** : le shell reste monté ; la vue interne bascule selon `activeConversationId` (null → liste, uuid → fil). Retour depuis le fil → `router.push('/messages')` → segment `null` → liste. Vérifier que la logique responsive de `KeyHomeChatBox` réagit au **changement** de prop (d'où la prop contrôlée).
- **Metadata** : conservée sur les `page.tsx` (server components rendant `null`) ; le layout client ne casse pas l'export metadata.
- **`useSearchParams` / build** : encapsulé dans `<Suspense>` au niveau layout.
- **Scroll** : conteneur chat `height:100dvh; overflow:hidden` → pas de scroll document ; le scroll liste vit dans son conteneur interne, préservé car non remonté. Fil : scroll propre par conversation (attendu).
- **`useSelectedLayoutSegment` = null** sur l'inbox → état « aucune conversation » (desktop : volet vide/empty-state ; mobile : liste).

## 8. Tests

Frontend = **Vitest** (app Next séparée, pas Pest) + QA manuelle.

**Vitest (unitaires) :**
- `ChatShell` : segment → `activeConversationId` (uuid / null) ; `?draft=` transmis.
- `PageTransition` : pathname chat → pas de `motion.div` ; pathname non-chat → wrapper keyé.
- `useChatDrafts` : persistance en mémoire par uuid à travers un remontage simulé.
- Seuil auto-loadMore : déclenche à proximité du haut ; ne redéclenche pas si `isFetchingNextPage`.
- Suppression barre `isFetching` quand `data` présent.
- Gate `isRestoring` → scaffold puis contenu.

**QA manuelle (checklist) :**
- Ouvrir `/messages` : aucune barre/spinner si cache présent.
- Cliquer une conversation : ouverture **instantanée**, la liste ne bouge pas.
- Revenir à l'inbox : scroll liste préservé.
- Taper un brouillon, changer de conversation, revenir : brouillon **conservé**.
- Rechargement à froid (Cmd-Shift-R) : scaffold bref puis données, **pas** de flash skeleton d'annonces.
- Scroller vers le haut d'un long fil : messages anciens chargés **automatiquement** avant d'atteindre le sommet.
- Recevoir un message en temps réel dans la conversation ouverte et dans une autre (badge non-lus).
- Idem sur l'espace owner.

## 9. Risques & rollback

- **Suppression `template.tsx`** : si une page non-chat dépendait du remontage template pour réinitialiser un état, le `PageTransition` keyé par pathname reproduit ce remontage → risque faible. Rollback : restaurer les deux fichiers `template.tsx`.
- **Prop contrôlée `KeyHomeChatBox`** : régression possible si un consommateur passait encore `initialActiveConversationId`. Grep exhaustif des usages avant migration ; conserver un alias déprécié si un appelant subsiste.
- **`useSearchParams` sans Suspense** : casse `next build` → couvert par la frontière Suspense (test build local).
- **Auto-loadMore** : boucle infinie si garde-fous absents → tests unitaires du seuil + `hasNextPage/!isFetchingNextPage`.
- Chaque étape est indépendamment livrable ; rollback granulaire par fichier.

## 10. Hygiène commits

- Commits dans le repo **`keyhome-frontend-next`** (jamais committer le dossier comme gitlink dirty depuis le repo Laravel).
- Auteur **Cedrick Feze** uniquement, `git -c commit.gpgsign=false commit`, **pas** de trailer `Co-Authored-By: Claude`.
- **Pas** de push/merge/deploy sans confirmation explicite.
- Lint + `vitest run` verts avant commit.

## 11. Séquençage d'implémentation

1. `PageTransition` conscient du pathname + suppression des 2 `template.tsx` (dé-anime le chat sans rien casser).
2. `messages/layout.tsx` + coquilles `page.tsx`/`[uuid]/page.tsx` (visitor), `KeyHomeChatBox` en prop contrôlée + suppression spinner + `useChatDrafts`.
3. Gate `isRestoring` scopé + `useConversations` refetch/prefetch + barre `isFetching`.
4. `ChatWindow` auto-loadMore + `ConversationItem` router.prefetch.
5. Miroir owner (2–4).
6. Tests Vitest + QA manuelle + vérification `BROADCAST_CONNECTION` prod.
