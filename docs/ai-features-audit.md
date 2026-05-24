# KeyHome — Audit & Plan d'implémentation IA (3 modules)
*Généré le 24 mai 2026 — Stack : Laravel 12 + Next.js 15 + OpenAI/Groq/Gemini*

---

## Executive Summary

Les 3 features IA sont **fonctionnelles mais sous-exploitées**. Le backend multi-provider
(`AiDescriptionEnhancer`) est excellent. Les lacunes sont côté UX, richesse contextuelle
et modération proactive. 6 améliorations haute priorité identifiées.

| Feature | État actuel | Score percutance |
|---------|------------|-----------------|
| AI Enhancer (annonces) | ✅ Description seule, pas de titre, pas d'undo, pas de génération à froid | 4/10 |
| AI Rejection (admin) | ✅ Reformule le texte admin, mais pas de diagnostic proactif ni templates | 5/10 |
| AI Bail (contrats) | ✅ Améliore les conditions, pas de templates camerounais, pas de résumé | 5/10 |

---

## Module 1 — AI Enhancer (Annonces)

### État actuel (✅ implémenté)
- `AiDescriptionEnhancer::enhance()` — multi-provider avec fallback chain
- Route `POST /api/v1/ads/ai/enhance-description`
- `adsService.enhanceDescription()` côté frontend
- Bouton "Améliorer avec l'IA" dans `AdFormBasicInfo.tsx`
- Spinner pendant la requête (~2-3s)

### Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| Pas de génération "à froid" (description vide) | 🔴 Haute | Moyen |
| Pas d'amélioration du titre | 🔴 Haute | Faible |
| Pas d'undo/restore après enhance | 🟡 Moyen | Faible |
| Pas de streaming (UX bloquante) | 🟡 Moyen | Moyen |
| Pas d'analyse de photos pour générer description | 🟢 Nice-to-have | Élevé |
| Pas de contexte attributs (type/ville/surface) injecté | 🟡 Moyen | Faible |

### Améliorations implémentées dans cette session

#### 1. `generateFromAttributes()` — Génération à froid
**Backend**: Nouveau system prompt contextuel qui utilise type, ville, quartier, chambres,
surface, prix, transaction_type pour générer une description de qualité pro même si
l'utilisateur n'a rien écrit.

**Frontend**: Quand `description.trim() === ''` → bouton "Générer une description ✨"
au lieu de "Améliorer avec l'IA". L'API reçoit les attributs du formulaire en context.

#### 2. `enhanceTitle()` — Amélioration du titre
**Backend**: `AiDescriptionEnhancer::enhanceTitle(string $title, string $context): string`
Prompt spécialisé : titre concis (6-12 mots), accrocheur, factuel, SEO.

**Frontend**: Bouton `✨` inline à droite du champ titre dans `AdFormBasicInfo.tsx`.

#### 3. Undo/restore — Diff avant/après
**Frontend**: `originalDescription` stocké avant enhance + bouton "Annuler" si different.

---

## Module 2 — AI Rejection Reason (Admin Filament)

### État actuel (✅ implémenté)
- `AiDescriptionEnhancer::enhanceRejectionReason()` — reformule le texte admin
- Bouton "Améliorer avec l'IA" dans `PendingAdResource.php` (Filament SchemaActions)
- Admin doit taper le motif de zéro avant de cliquer

### Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| Aucun template de refus rapide (admin tape tout de zéro) | 🔴 Haute | Faible |
| Pas de diagnostic automatique (l'IA doit lire l'annonce et proposer) | 🔴 Haute | Moyen |
| Pas de score de confiance modération sur la liste | 🟡 Moyen | Élevé |
| Pas d'historique des motifs fréquents | 🟢 Nice-to-have | Élevé |

### Améliorations implémentées dans cette session

#### 1. Chips de templates rapides
5 chips cliquables au-dessus du textarea :
- 📷 Photos insuffisantes/floues
- 📝 Description trop courte
- 💰 Prix incohérent/manquant
- 📋 Documents requis manquants
- ⚠️ Informations trompeuses

Clic → pré-remplit le textarea (cumulatif si plusieurs sélectionnés) →
admin peut affiner → bouton "Améliorer avec l'IA" reformule.

#### 2. `diagnoseAdForRejection()` — Diagnostic proactif
**Backend**: `AiDescriptionEnhancer::diagnoseAdForRejection(...)` reçoit titre,
description, prix, nb photos, type et génère un motif de refus structuré.

**Admin**: Nouveau bouton "Diagnostiquer avec l'IA 🔍" qui lit l'annonce courante
et pré-remplit le textarea sans que l'admin n'ait rien tapé.

---

## Module 3 — AI Bail (Contrats de location)

### État actuel (✅ implémenté)
- `AiDescriptionEnhancer::enhanceLeaseConditions()`
- Route `POST /api/v1/my/lease-contracts/ai/enhance-conditions`
- `ownerService.enhanceLeaseConditions()` côté frontend
- Bouton "Améliorer avec l'IA" dans :
  - `owner/ads/[id]/page.tsx` (création de contrat)
  - `owner/lease-contracts/page.tsx` (modification)
- Bouton visible uniquement si le champ n'est pas vide

### Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| Pas de templates de clauses camerounaises | 🔴 Haute | Faible |
| Pas de résumé simplifié du contrat | 🟡 Moyen | Moyen |
| Pas de détection clauses manquantes | 🟡 Moyen | Moyen |
| Pas de génération "à froid" pour conditions | 🟡 Moyen | Faible |

### Améliorations implémentées dans cette session

#### 1. Clause templates chips (droit camerounais)
Chips sélectionnables dans le formulaire :
- Interdiction de sous-location
- Animaux domestiques autorisés
- Entretien jardin à la charge du locataire
- Préavis de 2 mois requis
- Paiement du loyer avant le 5 du mois
- État des lieux contradictoire obligatoire

Multi-sélection → texte concaténé dans le textarea → IA améliore/structure.

#### 2. `summarizeLeaseContract()` — Résumé langage simple
**Backend**: Nouveau prompt qui génère un résumé 5-8 points en langage courant
pour que le locataire comprenne ses obligations avant de signer.

---

## Plan d'action prioritaire

| # | Action | Module | Sévérité | Effort | Statut |
|---|--------|--------|----------|--------|--------|
| 1 | `generateFromAttributes()` — génération à froid | Annonces | 🔴 Critique | 3h | ✅ Implémenté |
| 2 | `enhanceTitle()` — amélioration titre | Annonces | 🔴 Critique | 1h | ✅ Implémenté |
| 3 | Undo/restore description | Annonces | 🟡 Moyen | 30min | ✅ Implémenté |
| 4 | Admin: chips refus rapides | Rejection | 🔴 Critique | 1h | ✅ Implémenté |
| 5 | Admin: `diagnoseAdForRejection()` | Rejection | 🔴 Critique | 2h | ✅ Implémenté |
| 6 | Contrats: clause templates chips | Bail | 🔴 Critique | 1h | ✅ Implémenté |
| 7 | `summarizeLeaseContract()` | Bail | 🟡 Moyen | 2h | 🕐 V2 |
| 8 | Streaming SSE pour enhance | Annonces | 🟡 Moyen | 4h | 🕐 V2 |
| 9 | Analyse photos → description | Annonces | 🟢 Nice-to-have | 6h | 🕐 V3 |
| 10 | Score confiance modération | Rejection | 🟡 Moyen | 8h | 🕐 V2 |

---

## Sources & Références

- [ListingAI.co](https://www.listingai.co/) — génération de descriptions depuis attributs
- [V7 Labs — AI Lease Abstraction](https://www.v7labs.com/blog/ai-real-estate-lease-abstraction) — extraction clauses, flags missing
- [NAAHQ — AI Leasing Operations 2025](https://naahq.org/top-7-strategies-integrating-ai-your-leasing-operations-2025)
- [AdventuresInCRE — AI Tools Spring 2026](https://www.adventuresincre.com/ai-tools-commercial-real-estate/)
