---
name: codebase-cleanup
description: >
  Audit et nettoyage systématique d'une codebase existante : suppression du code mort,
  dédoublonnage, typage strict, élimination des dépendances circulaires, suppression
  du code défensif inutile, retrait du code legacy/deprecated, et nettoyage des
  commentaires et stubs inutiles. Utiliser ce skill dès que l'utilisateur parle de
  "refactoring", "nettoyage de code", "dette technique", "dead code", "types any/unknown",
  "dépendances circulaires", "try/catch inutiles", "code legacy", ou souhaite auditer
  une codebase avant une mise en production. Déclencher même si la demande est partielle
  (ex. "trouve le code mort") — ce skill couvre toujours l'ensemble du processus.
---

# Codebase Cleanup

Ce skill guide un audit complet et un nettoyage méthodique d'une codebase. L'objectif
est d'obtenir un code **propre, strict, singulier et sans dette technique**.

Chaque étape est indépendante mais elles se renforcent mutuellement. Travailler dans
l'ordre proposé évite de nettoyer du code qui sera de toute façon supprimé à l'étape
suivante.

---

## Étape 0 — Orientation

Avant tout, comprendre la codebase :

- Quel est le langage / stack principal ?
- Y a-t-il un `package.json`, `composer.json`, `pyproject.toml`, `Cargo.toml` ?
- Quelle est la structure des dossiers (monorepo, multi-packages, modules) ?
- Des outils sont-ils déjà configurés (`tsconfig.json`, `eslint`, `phpstan`, etc.) ?

```bash
# Exemple pour un projet Node/TS
ls -la
cat package.json | jq '.scripts, .dependencies, .devDependencies'
cat tsconfig.json
```

Ne pas présupposer le stack. Lire d'abord, agir ensuite.

---

## Étape 1 — DRY : Dédoublonnage et consolidation

**Objectif** : éliminer toute duplication qui ajoute de la complexité, pas celle qui
améliore la lisibilité.

### Identifier les doublons

- Chercher les fonctions quasi-identiques (même logique, noms différents)
- Repérer les blocs copiés-collés entre modules
- Identifier les helpers redéfinis dans plusieurs fichiers

```bash
# Recherche de patterns répétés (adapter selon le langage)
grep -rn "function " src/ | sort | uniq -d
# Ou avec des outils comme jscpd (JS/TS)
npx jscpd src/ --min-lines 5 --reporters console
```

### Règle de consolidation

> Ne fusionner que si la fusion **réduit la complexité**. Deux fonctions similaires
> avec des contextes métier distincts restent séparées.

- Créer un fichier `shared/utils.ts` (ou équivalent) pour les helpers vraiment partagés
- Éviter les abstractions prématurées : une fonction utilitaire doit être utilisée
  **au moins 3 fois** avant d'être extraite

---

## Étape 2 — Types : consolidation des définitions partagées

**Objectif** : un type = une définition. Aucun type dupliqué ou fragmenté.

### Identifier les types dispersés

```bash
# TypeScript
grep -rn "^type \|^interface \|^export type \|^export interface " src/ | sort
# PHP (classes/DTOs)
grep -rn "^class \|^interface \|^readonly class " app/ | sort
```

### Consolidation

- Créer `src/types/index.ts` (ou `src/types/<domaine>.ts` pour les gros projets)
- Les types utilisés par plus d'un module → `shared/`
- Les types spécifiques à un module → co-localisés avec ce module
- Supprimer les re-exports inutiles et les alias redondants

### Vérification

```bash
# Trouver les types définis plusieurs fois avec le même nom
grep -rn "type User\|interface User" src/
```

---

## Étape 3 — Code mort : détection et suppression

**Objectif** : supprimer tout ce qui n'est pas référencé, sans exception.

### Outils recommandés

**TypeScript / JavaScript**
```bash
npx knip --reporter compact
# ou
npx ts-prune
```

**PHP**
```bash
composer require --dev rector/rector
# Rector avec règle RemoveUnusedVariableAssignRector
```

**Python**
```bash
pip install vulture
vulture src/
```

### Protocole de suppression

1. Lancer l'outil et noter chaque export/fonction/classe signalé
2. Vérifier manuellement dans le code : chercher toutes les occurrences du symbole
3. Vérifier aussi dans les fichiers de config, les routes, les tests, les templates
4. Supprimer uniquement si **aucune référence active** n'existe
5. Ne pas commenter le code supprimé — le supprimer vraiment

> ⚠️ Les points d'entrée dynamiques (routes auto-découvertes, listeners d'événements,
> commandes CLI enregistrées par convention) peuvent créer des faux positifs. Les
> vérifier manuellement avant suppression.

---

## Étape 4 — Dépendances circulaires

**Objectif** : aucun cycle dans le graphe de dépendances.

### Détection

**TypeScript / JavaScript**
```bash
npx madge --circular --extensions ts,tsx src/
# Générer un graphe visuel
npx madge --circular --image graph.png src/
```

**PHP**
```bash
composer require --dev maglnet/composer-require-checker
# ou analyser manuellement avec phpstan
```

### Résolution

Pour chaque cycle `A → B → A` :

1. Identifier quelle dépendance est illégitime (souvent une importation de commodité)
2. Extraire l'interface ou le type partagé dans un troisième module `C`
3. `A → C`, `B → C` — le cycle est cassé
4. En dernier recours : injection de dépendance pour briser le couplage fort

Ne jamais masquer un cycle avec un import dynamique (`require()` ou `import()` lazy)
sauf si le lazy loading est justifié fonctionnellement.

---

## Étape 5 — Types faibles : élimination de `any`, `unknown`, et équivalents

**Objectif** : typage strict partout. Zéro `any` non justifié.

### Inventaire

```bash
# TypeScript
grep -rn ": any\|as any\|: unknown" src/ --include="*.ts" --include="*.tsx"
# Avec ESLint
npx eslint src/ --rule '{"@typescript-eslint/no-explicit-any": "error"}'
```

### Protocole de remplacement

Pour chaque `any` / `unknown` trouvé :

1. **Remonter à la source** : d'où vient cette donnée ?
2. **Chercher dans la lib/le package** : le type est souvent déjà exporté
   ```bash
   # Chercher dans node_modules
   grep -rn "export.*interface\|export.*type" node_modules/nom-du-package/dist/*.d.ts
   ```
3. **Regarder la doc ou les types du package** (`@types/xxx`)
4. Si la donnée vient d'une API externe : définir un type `ApiResponse<T>` précis
5. Si la donnée est vraiment polymorphe : utiliser un **union type** `TypeA | TypeB`,
   pas `any`

### Cas légitimes de `unknown`

`unknown` est acceptable **uniquement** dans les points d'entrée de données non
contrôlées : parsing JSON brut, réponses réseau avant validation, handlers d'erreurs
`catch (e: unknown)`. Dans ces cas, le `unknown` doit être immédiatement narrowé avec
un type guard ou un schéma de validation (Zod, class-validator, etc.).

---

## Étape 6 — Try/catch et programmation défensive

**Objectif** : ne garder que les blocs d'erreur qui ont une **raison métier claire**.

### Cas à supprimer

- `try/catch` qui ne font que logger et re-throw → supprimer, laisser propager
- `catch` vides ou avec `console.log` seulement → supprimer
- Valeurs par défaut silencieuses sur des erreurs qui ne devraient pas arriver :
  ```ts
  // ❌ Masque une vraie erreur
  const name = user?.name ?? 'Unknown';
  
  // ✅ Si user est garanti non-null par le flux
  const name = user.name;
  ```
- `try/catch` autour d'opérations synchrones pures → supprimer

### Cas à conserver

- Parsing de données externes (JSON, fichiers, inputs utilisateur)
- Appels réseau avec gestion d'erreur différenciée (timeout, 4xx vs 5xx)
- Transactions base de données avec rollback explicite
- Points d'entrée utilisateur (formulaires, uploads)

### Règle de décision

> Si supprimer le `try/catch` ferait crasher l'app sur une entrée invalide venant
> de l'extérieur → le garder. Sinon → le supprimer.

Tout `catch` conservé doit avoir une action concrète : retourner une erreur typée,
logger avec contexte, déclencher un fallback métier documenté.

---

## Étape 7 — Code legacy, deprecated et chemins multiples

**Objectif** : un seul chemin de code par fonctionnalité. Aucun code "de transition".

### Identification

```bash
# Chercher les marqueurs legacy
grep -rn "TODO\|FIXME\|HACK\|DEPRECATED\|@deprecated\|legacy\|fallback\|old_\|_v1\|_v2" src/
# Feature flags / conditionnels de compatibilité
grep -rn "if.*version\|if.*legacy\|if.*old" src/
```

### Suppression

- Code marqué `@deprecated` sans remplacement documenté → analyser, puis supprimer
- Branches conditionnelles qui ne peuvent être vraies qu'en mode legacy → supprimer
  la branche entière, pas juste le flag
- Fonctions suffixées `_old`, `_v1`, `_backup` → supprimer si non référencées
- Migrations / scripts one-shot laissés dans le code → déplacer dans un dossier
  `scripts/migrations/` ou supprimer s'ils ont déjà tourné

> Après suppression, vérifier que tous les tests passent. Si un test dépend du code
> legacy, le test doit être mis à jour.

---

## Étape 8 — AI slop, stubs et commentaires inutiles

**Objectif** : aucun commentaire qui ne dit pas quelque chose que le code ne peut pas
dire lui-même.

### Patterns à supprimer

**Commentaires redondants**
```ts
// ❌ Dit la même chose que le code
// Increment counter
counter++;

// ❌ Décrit un mouvement passé
// Previously used Redux, now using Zustand

// ❌ En-têtes de section inutiles dans de petits fichiers
// ===== UTILS =====
```

**Stubs et larp**
```ts
// ❌ Implémentation factice laissée en place
function sendEmail(to: string) {
  // TODO: implement
  console.log('would send email to', to);
}
```

**Commentaires de travail en cours**
```ts
// ❌
// Note: this replaces the old fetchUser function
// Temporary fix until we migrate to new API
// This is a workaround for issue #123
```

### Commentaires à conserver

- Explications de **pourquoi** une décision non-évidente a été prise
- Références à des specs externes, RFC, lois, contraintes métier
- Avertissements sur des effets de bord non-évidents

```ts
// ✅ Explique le pourquoi, pas le quoi
// Cloudflare R2 ne supporte pas les multipart uploads > 5GB via SDK v2
// Utiliser l'API S3 compatible directement pour ces cas
```

### Protocole

1. Lister tous les commentaires : `grep -rn "//\|/\*\|#" src/`
2. Pour chaque commentaire : est-ce qu'il apporte une information que le code seul
   ne peut pas transmettre ?
3. Non → supprimer
4. Oui mais mal formulé → réécrire en une phrase concise

---

## Checklist finale

Avant de déclarer le nettoyage terminé :

- [ ] `knip` (ou équivalent) retourne zéro exports non utilisés
- [ ] `madge --circular` retourne zéro cycles
- [ ] `grep -rn ": any"` retourne zéro (hors cas légitimes documentés)
- [ ] Aucun `try/catch` sans action concrète dans le `catch`
- [ ] Aucun `TODO`, `FIXME`, `HACK`, `_old`, `_v1` dans le code actif
- [ ] Tous les tests passent (`npm test` / `php artisan test` / équivalent)
- [ ] Le build est propre sans warnings de type

---

## Priorité d'exécution

Si la codebase est grande, commencer par les étapes à fort impact :

1. **Étape 3** (code mort) — réduit la surface à analyser pour les autres étapes
2. **Étape 4** (cycles) — débloque les refactorisations sans risque
3. **Étape 5** (types faibles) — le plus long, mais le plus impactant sur la fiabilité
4. **Étapes 1, 2, 6, 7, 8** — dans l'ordre, une fois la base stabilisée
