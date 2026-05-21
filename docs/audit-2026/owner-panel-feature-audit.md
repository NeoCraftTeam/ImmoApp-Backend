# KeyHome Owner Panel — Expert Feature Audit 2026

> **Méthodologie** : Crawl firecrawl de sources expertes (PacificABS, Buildium, MagicDoor, Chekin/Airbnb, TheAfricanVestor, Ownkey, EliteConsulting, RentRedi, DoorLoop) + benchmark concurrentiel Afrique (CamerImmo, PropertyPro, OwnKey Ghana, ShifTenant Kenya). Chaque fonctionnalité est évaluée sur trois axes : **Implémentation actuelle**, **Benchmark expert**, **Gaps identifiés**.
>
> Auteur : Cascade AI — 21 mai 2026

---

## Contexte Marché — Cameroun / Afrique Centrale

| Signal | Donnée clé | Source |
|--------|-----------|--------|
| Marché immobilier Afrique | USD 233 Md (2025) → 347 Md (2034) | Ownkey 2026 |
| Portail dominant Cameroun | CamerImmo.com — Tier ★ (minimal traffic, market offline) | Ownkey Africa Guide 2026 |
| Days-on-market Douala/Yaoundé | 90–240 jours (paperasse, titres fonciers) | TheAfricanVestor 2026 |
| % ventes sous prix affiché | 80–85% des biens (négociation standard -5% à -15%) | TheAfricanVestor 2026 |
| Quartiers premium Douala | Bonapriso, Bonanjo, Akwa | TheAfricanVestor 2026 |
| Quartiers premium Yaoundé | Bastos, Nlongkak | TheAfricanVestor 2026 |
| Mix propriétés | Appartements 40-50%, maisons 35-45%, terrains 10-20% | TheAfricanVestor 2026 |
| Paiement dominant | Mobile Money (Orange, MTN) — pas de carte bancaire largement | Terrain |
| Compétiteur le plus proche | PropertyPro.ng (Nigeria) — 60k+ annonces, non vérifiées | Ownkey 2026 |
| **Opportunité KeyHome** | Seul portail Cameroun avec vérification, e-signature, boost, chat | Analyse interne |

**Conclusion marché** : KeyHome opère dans un désert concurrentiel numérique (CamerImmo = Tier ★). L'opportunité est de devenir le **premier portail proptech vérifié d'Afrique Centrale**, comme Ownkey l'est au Ghana (Tier ★★★★★).

---

## 1. Dashboard Propriétaire

### État actuel
- Page `/owner/dashboard` avec salutation, métriques de base
- Composants : `FadeIn`, `Typography`, `GradientText`
- Données : annonces actives, visites demandées, contrats

### Benchmark expert (PacificABS, Buildium Analytics Hub, GoodData 2026)
Les 14 KPIs obligatoires pour un propriétaire en 2026 :

| Catégorie | KPI | Priorité |
|-----------|-----|---------|
| Financier | Taux de collecte loyer (loyer encaissé / loyer facturé) | 🔴 Critique |
| Financier | Flux de trésorerie net par annonce | 🔴 Critique |
| Financier | Budget vs Réel (variance dépenses) | 🟠 Haute |
| Opérationnel | Taux d'occupation / vacance | 🔴 Critique |
| Opérationnel | Délai réponse aux demandes de visite | 🟠 Haute |
| Opérationnel | Taux de conversion visite → contrat | 🟠 Haute |
| Marketing | Taux conversion annonce → contact | 🟡 Moyenne |
| Marketing | Performance Boost (vues pendant boost vs hors boost) | 🟡 Moyenne |
| Satisfaction | Score satisfaction locataires (reviews moyens) | 🟡 Moyenne |
| Trust | Trust Score progression | 🟡 Moyenne |
| Engagement | Annonces vues / semaine | 🟢 Basse |
| Engagement | Taux de réponse messages | 🟢 Basse |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Aucun widget "taux collecte loyer"** — modèle `Payment` existe mais n'est pas agrégé sur le dashboard | Élevé | Moyen |
| **Pas de widget "taux occupation"** — calculable depuis `LeaseContract.status` + annonces actives | Élevé | Faible |
| **Pas de comparaison période N vs N-1** — impossible de voir si la performance s'améliore | Élevé | Moyen |
| **Pas de graphique temporel** (ligne évolutive loyers, vues, contacts sur 30/90 jours) | Moyen | Élevé |
| **Pas d'alerte proactive** — "Votre annonce X n'a reçu aucune vue depuis 7 jours" | Moyen | Moyen |
| **Boost ROI invisible** — après un boost, aucun widget ne compare vues avant/pendant/après | Élevé | Moyen |

### Recommandations prioritaires
1. Ajouter backend : `GET /owner/stats` endpoint retournant les 5 KPIs critiques agrégés
2. Widget "Taux occupation" calculé : annonces avec `LeaseContract ACTIVE` / total annonces publiées
3. Widget "Performance récente" : sparkline 30 jours des vues (depuis `AdInteraction`)
4. Widget "Boost ROI" sur la page boost : vues J-7 vs J+7 après boost

---

## 2. Gestion des Annonces

### État actuel
- CRUD complet avec wizard multi-étapes (`AdFormWizard`)
- Draft workflow + edit-draft payload pour annonces live
- Upload photos avec crop, tri drag-and-drop
- Meilisearch indexation, Scout sync
- `AdBoost` crédit-based (récemment construit)

### Benchmark expert (Ownkey Ghana, Property24 South Africa, PropertyPro Nigeria)
Les portails Tier ★★★★+ proposent :

| Feature | Ownkey (★★★★★) | Property24 (★★★★) | KeyHome | Gap |
|---------|----------------|-------------------|---------|-----|
| Vérification annonce avant publication | ✅ Indépendante | ✅ Agent licencié | ❌ Manuel admin | 🔴 |
| Virtual tours / 3D | ✅ | ✅ | ✅ Partiel | 🟡 |
| AI valuation / prix marché | ✅ OwnEstimate | ✅ | ⚠️ Page existe | 🟠 |
| Photos IA auto-recadrées | ✅ | ✅ | ❌ | 🟡 |
| Historique modifications annonce | ✅ | ✅ | ❌ | 🟡 |
| Partage annonce WhatsApp 1-clic | ✅ | ✅ | ❌ | 🔴 |
| Badge "Annonce vérifiée" visible | ✅ | ✅ | ❌ | 🔴 |
| Statistiques par annonce (vues, contacts, taux clic) | ✅ | ✅ | ⚠️ `AdInteraction` existe | 🟠 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas de badge "Annonce vérifiée"** sur les annonces approuvées par admin — différenciateur clé vs CamerImmo | Élevé | Faible |
| **Partage WhatsApp manquant** — en Afrique, WhatsApp > email pour partager un bien. 1 bouton suffit | Élevé | Faible |
| **Stats par annonce invisibles** — `AdInteraction` enregistre mais l'owner ne voit pas ses propres stats | Élevé | Moyen |
| **Pas de "prix suggéré"** sur le formulaire de création — le marché Cameroun est opaque, un guide XAF/m² aiderait | Moyen | Élevé |
| **Pas de duplication annonce** — copier une annonce existante comme point de départ | Faible | Faible |
| **Pas d'historique des modifications** — audit trail pour l'owner (qui a changé quoi) | Faible | Moyen |

---

## 3. Ad Boost (Système de Crédit)

### État actuel
- `BoostPack` (3 packs avec durées et crédits)
- `AdBoost` avec status enum (PENDING, ACTIVE, EXPIRED, CANCELLED)
- `BoostService::apply()` / `expire()` atomique
- `BoostController` : list packs, status, boost, unboost
- `ExpireBoostedAds` command

### Benchmark expert (Facebook Ads, OLX Boost, Marketplace UX patterns 2024)
Les meilleures UX de boost en marketplace :

| Aspect | Meilleure pratique | KeyHome état |
|--------|-------------------|-------------|
| **Estimation reach avant achat** | "Votre annonce sera vue ~2,500 fois" (basé sur historique) | ❌ Absent |
| **Aperçu visuel du badge boost** | Mockup de l'annonce avec badge "En vedette" visible | ❌ Absent |
| **Sélection durée/intensité** | Slider durée + prix calculé en temps réel | ⚠️ Packs fixes uniquement |
| **ROI post-boost dashboard** | Graphique vues avant/pendant/après | ❌ Absent |
| **Notification expiration** | "Votre boost expire dans 24h — renouveler ?" | ❌ Absent |
| **Boost auto-renew** | Option renouvellement automatique | ❌ Absent |
| **Historique boosts** | Liste tous les boosts passés avec performance | ❌ Absent |
| **Remboursement partiel** | Si boost annulé avant expiration → crédits pro-rata | ❌ Absent |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas d'estimation reach** — l'owner achète en aveugle, sans savoir combien de personnes verront son annonce | Élevé | Moyen |
| **Pas de dashboard performance boost** — post-boost, aucune comparaison vues avant/pendant | Élevé | Moyen |
| **Pas de notification expiration** — l'owner ne sait pas que son boost va expirer → churn | Élevé | Faible |
| **Pas d'historique boosts** sur la page owner → `/owner/ads/[id]` | Moyen | Faible |
| **Refund partiel absent** — si l'owner annule un boost actif, les crédits ne sont pas remboursés | Moyen | Moyen |
| **API Boost manque endpoint stats** — `GET /ads/{ad}/boost/stats` n'existe pas | Élevé | Moyen |

### Recommandations
```
GET /ads/{ad}/boost/stats
→ { views_before_7d, views_during, views_after_7d, contacts_during, active_boost }
```
Ce endpoint alimenterait le widget ROI boost frontend.

---

## 4. Visites / Viewing Reservations

### État actuel
- `ViewingReservationController` + `ReservationService`
- `TentativeReservation` model
- Page `/owner/viewings` avec FadeIn, cards

### Benchmark expert (Buildium Showings Coordinator, ShowMojo, Tenant Turner 2026)

Les fonctionnalités de référence des outils de showing :

| Feature | Buildium (★★★★) | Tenant Turner | KeyHome | Gap |
|---------|----------------|--------------|---------|-----|
| **Booking self-service** (locataire choisit le créneau lui-même) | ✅ | ✅ | ❌ | 🔴 |
| **Sync calendrier** (Google Calendar, iCal) | ✅ | ✅ | ❌ | 🟠 |
| **Rappels automatiques SMS/email** avant visite | ✅ | ✅ | ❌ | 🔴 |
| **Prescreening questions** (revenus, emploi) avant confirmation | ✅ | ✅ | ❌ | 🟠 |
| **Suivi prospect** (visite → candidature → contrat) | ✅ | ✅ | ⚠️ Partiel | 🟠 |
| **Historique visites par annonce** | ✅ | ✅ | ❌ | 🟡 |
| **Post-showing follow-up automatique** | ✅ | ✅ | ❌ | 🟡 |
| **Calcul taux conversion visite/contrat** | ✅ | ✅ | ❌ | 🟠 |
| **Notes privées propriétaire sur candidat** | ✅ | ✅ | ❌ | 🟡 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas de rappel automatique** — les no-shows en Afrique sont fréquents sans rappel. SMS/push J-1 et H-2 | Critique | Moyen |
| **Pas d'auto-scheduling** — l'owner confirme manuellement chaque visite. Créneaux disponibles = self-service | Élevé | Élevé |
| **Pas de prescreening** — un formulaire "revenus mensuels, emploi" avant de confirmer filtrerait les candidats non qualifiés | Élevé | Moyen |
| **Pas de note privée** sur le candidat après visite ("locataire sérieux", "doublon avec propriétaire actuel") | Moyen | Faible |
| **Pas de taux conversion** affiché (X visites → Y contacts → Z contrats) | Moyen | Moyen |
| **Lien visite → contrat** manque (depuis une visite confirmée, créer directement un `LeaseContract`) | Élevé | Moyen |

### API Gap
```
# Manquant :
POST /viewings/{viewing}/confirm-and-schedule   # confirmation + créneau définitif
POST /viewings/{viewing}/remind                 # déclenche rappel manuel
GET  /owner/viewings/stats                      # conversion pipeline
```

---

## 5. Messages / Chat

### État actuel
- `ChatHeader` avec annonce liée, pièces jointes, menu
- Bottom sheet mobile pour l'annonce
- Real-time via Pusher/Echo
- Pièces jointes : images, PDF

### Benchmark expert (DoorLoop, RentRedi, TenantCloud, Iconic PM 2026)

| Feature | DoorLoop | RentRedi | KeyHome | Gap |
|---------|----------|----------|---------|-----|
| **Réponses rapides prédéfinies** (templates messages) | ✅ | ✅ | ❌ | 🟠 |
| **Traduction automatique** messages multi-langue | ✅ | ❌ | ❌ | 🟡 |
| **Marquage "lu/non-lu" propriétaire** | ✅ | ✅ | ⚠️ | 🟠 |
| **Archivage conversations** | ✅ | ✅ | ❌ | 🟡 |
| **Partage annonce dans la conversation** | ✅ | ✅ | ✅ | ✅ |
| **Intégration WhatsApp** | ⚠️ | ❌ | ❌ | 🔴 |
| **Email fallback** (si pas de compte) | ✅ | ✅ | ❌ | 🟠 |
| **Filtres conversations** (non lus, avec annonce, archivés) | ✅ | ✅ | ❌ | 🟠 |
| **Modération spam/signalement** | ✅ | ❌ | ⚠️ `AdReport` | 🟡 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Intégration WhatsApp absente** — en Afrique Centrale, 90% des échanges immobiliers passent par WhatsApp. Un bouton "Continuer sur WhatsApp" sur le chat ferait exploser l'engagement | Critique | Moyen |
| **Réponses rapides** — templates "Bonjour, quand êtes-vous disponible pour une visite ?" économisent 80% du temps de saisie | Élevé | Faible |
| **Email fallback** — si le candidat n'a pas l'app, le message devrait partir par email automatiquement | Élevé | Moyen |
| **Filtres conversations manquants** — liste de toutes les conversations sans filtre "non lu / avec visite planifiée" | Moyen | Faible |
| **Pas de "vu à" / double coché** visible par l'owner pour confirmer que le message est lu | Moyen | Faible |

---

## 6. Contrats de Bail (Lease Contracts)

### État actuel
- `LeaseContract` + `LeaseSignatureRequest` models
- Signature électronique partielle
- `Document` model pour uploads

### Benchmark expert (MagicDoor, DocuSign, Yousign, eSignly 2025)

Standard légal e-signature pour bail :

| Exigence légale | Standard (ESIGN/UETA) | Yousign (eIDAS EU) | KeyHome | Gap |
|----------------|----------------------|-------------------|---------|-----|
| **Intention de signer capturée** | ✅ | ✅ | ⚠️ | 🟠 |
| **Consentement e-signature explicite** | ✅ | ✅ | ⚠️ | 🟠 |
| **Audit trail** (timestamp, IP, identité) | ✅ | ✅ | ❌ | 🔴 |
| **Document tamper-proof** (hash) | ✅ | ✅ | ❌ | 🔴 |
| **Stockage sécurisé cloud** | ✅ | ✅ | ⚠️ Spatie Media | 🟡 |
| **Téléchargement PDF signé** | ✅ | ✅ | ❌ | 🔴 |
| **Relance signature automatique** | ✅ | ✅ | ❌ | 🟠 |
| **Multi-signataire** (propriétaire + locataire) | ✅ | ✅ | ⚠️ Partiel | 🟠 |
| **Templates bail** (réutilisables) | ✅ | ✅ | ❌ | 🟡 |
| **Alerte renouvellement** (J-30, J-60) | ✅ | ✅ | ❌ | 🔴 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Audit trail manquant** — pour être légalement opposable, chaque signature doit logguer IP + timestamp + device. Actuellement inexistant | Critique | Moyen |
| **PDF signé téléchargeable** absent — post-signature, aucun document final généré | Critique | Élevé |
| **Alerte renouvellement** absente — "Votre bail expire dans 30 jours" → notification push + email | Élevé | Faible |
| **Relance automatique** — si le locataire n'a pas signé après 48h, relance automatique | Élevé | Faible |
| **Templates bail** — propriétaire devrait pouvoir créer un modèle standard et le réutiliser | Moyen | Élevé |
| **Clause génération Droit OHADA** — les baux Cameroun/CEMAC suivent le droit OHADA, pas ESIGN/UETA | Critique | Élevé |

### API Gap
```
# Manquant :
GET  /lease-contracts/{id}/download-pdf          # PDF signé final
POST /lease-contracts/{id}/send-reminder         # relance signature
GET  /lease-contracts/{id}/audit-trail           # log signature
GET  /owner/lease-contracts/expiring?days=30     # baux expirant bientôt
```

---

## 7. Trust Score

### État actuel
- `TrustScore` model + `TrustScoreService`
- `TrustScoreServiceInterface` avec `compute()`, `getOrCompute()`, `invalidate()`
- Affiché sur profil public + page owner

### Benchmark expert (Airbnb Identity Verification, Chekin Biometric 2025)

| Composante trust | Airbnb | Chekin Pro | KeyHome | Gap |
|----------------|--------|-----------|---------|-----|
| **Upload CNI / passeport** | ✅ | ✅ Biométrique | ⚠️ Document model | 🟠 |
| **Selfie + liveness detection** | ✅ | ✅ | ❌ | 🔴 |
| **Badge vérifié visible** sur profil | ✅ Rouge + ✓ | ✅ | ❌ | 🔴 |
| **Vérification téléphone** | ✅ | ✅ | ✅ | ✅ |
| **Vérification email** | ✅ | ✅ | ✅ | ✅ |
| **Vérification bancaire** | ✅ | ✅ | ❌ | 🟠 |
| **Historique reviews/réputation** | ✅ | ✅ | ✅ `Review` | ✅ |
| **Score progressif avec paliers** | ✅ | ✅ | ⚠️ Calcul interne | 🟡 |
| **Explication score visible** | ✅ | ✅ | ❌ | 🟠 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Badge vérifié absent** — le trust score est calculé mais le locataire candidat ne voit pas "✓ Identité vérifiée" sur l'annonce | Critique | Faible |
| **Liveness detection absente** — upload photo CNI sans biométrie = facilement fraudable | Élevé | Élevé |
| **Explication score opaque** — "Votre score est 72" mais pourquoi ? Quelles actions l'augmenteraient ? | Moyen | Faible |
| **Score invisible sur annonces** — les annonces devraient afficher le badge trust du propriétaire | Élevé | Faible |
| **Vérification N° de compte Mobile Money** (Orange/MTN) — preuve d'identité financière très forte en Afrique | Moyen | Moyen |

---

## 8. Abonnements (Subscriptions)

### État actuel
- `SubscriptionPlan` + `Subscription` models
- `SubscriptionService` + paiement GeniusPay / Stripe
- Plans bailleur avec limites d'annonces, boosts inclus

### Benchmark expert (Buildium, Property24 Agent Plans, PropertyPro.ng 2026)

| Feature | Buildium Free→Pro | PropertyPro.ng | KeyHome | Gap |
|---------|------------------|----------------|---------|-----|
| **Freemium avec limite annonces** | ✅ | ✅ | ✅ | ✅ |
| **Trial gratuit 14 jours** | ✅ | ✅ | ⚠️ | 🟡 |
| **Upgrade depuis le dashboard** | ✅ | ✅ | ⚠️ | 🟠 |
| **Factures téléchargeables** | ✅ | ✅ | ✅ `Invoice` | ✅ |
| **Pause abonnement** | ✅ | ❌ | ❌ | 🟡 |
| **Proration upgrade mid-cycle** | ✅ | ✅ | ⚠️ | 🟠 |
| **Notification avant renouvellement** | ✅ J-7, J-3 | ✅ | ❌ | 🔴 |
| **Récap utilisation en cours** (X/Y annonces utilisées) | ✅ | ✅ | ❌ | 🟠 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas de notification renouvellement** — l'owner est surpris par le débit | Élevé | Faible |
| **Pas de récap utilisation** dans le dashboard — "Vous avez utilisé 3/5 annonces ce mois" | Élevé | Faible |
| **Upgrade mid-cycle sans proration claire** — manque de transparence sur le calcul | Moyen | Moyen |
| **Pas de page downgrade** — l'owner doit contacter le support pour rétrograder | Moyen | Moyen |

---

## 9. Prix du Marché (`/owner/prix-marche`)

### État actuel
- Page existe : `/owner/owner/prix-marche/page.tsx`
- Contenu inconnu (à auditer)

### Benchmark expert (TheAfricanVestor 2026, Ownkey OwnEstimate, HouseCanary)

Données de référence Cameroun disponibles :
- **INS Cameroun** (Institut National de Statistique) — données logement
- **BEAC OpenData** — taux d'intérêt CEMAC
- **GlobalPropertyGuide** — rendements locatifs Cameroun
- **MINDCAF** — registre foncier

| Feature pricing tool | Ownkey OwnEstimate (Ghana) | Zillow Zestimate | KeyHome | Gap |
|---------------------|--------------------------|-----------------|---------|-----|
| **Estimation prix au m²** par quartier | ✅ Temps réel | ✅ | ❌ | 🔴 |
| **Comparables récents** (biens similaires vendus) | ✅ | ✅ | ❌ | 🔴 |
| **Carte thermique prix** par quartier | ✅ | ✅ | ❌ | 🟠 |
| **Historique prix** sur 12 mois | ✅ | ✅ | ❌ | 🟠 |
| **Fourchette loyer suggéré** pour une annonce | ✅ | ✅ | ❌ | 🔴 |
| **Rendement locatif estimé** (ROI investisseur) | ✅ | ✅ | ❌ | 🟠 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas de fourchette loyer suggéré** — l'owner publie un prix sans référence. Le marché Cameroun est opaque : 80-85% négocient sous le prix. Un guide prix éviterait les erreurs | Critique | Élevé |
| **Pas de carte thermique** — visualisation du prix moyen par quartier (Bonamoussadi, Makepe, Akwa...) | Élevé | Élevé |
| **Données basées sur les propres annonces KeyHome** — la plateforme a les données pour faire ce calcul, mais ne les expose pas | Élevé | Moyen |
| **API tierce manquante** — pas d'intégration GlobalPropertyGuide ou INS pour compléter les données | Moyen | Élevé |

---

## 10. Notifications

### État actuel
- `NotificationPreference` model
- Push via `PushSubscription` (web push)
- Notifications base Laravel

### Benchmark expert (RentRedi, DialMyCalls, TaiwosAlam/OurPropertyNG Africa 2026)

En Afrique, la hiérarchie des canaux de notification :
1. **WhatsApp** (taux ouverture 98%)
2. **SMS** (pas besoin d'internet)
3. **Push notification** (requiert app installée)
4. **Email** (faible taux ouverture Afrique sub-saharienne)

| Canal | RentRedi | ShifTenant Kenya | KeyHome | Gap |
|-------|----------|-----------------|---------|-----|
| Push notification | ✅ | ✅ | ✅ | ✅ |
| Email | ✅ | ✅ | ✅ | ✅ |
| SMS | ✅ | ✅ MTN/Safaricom | ❌ | 🔴 |
| WhatsApp | ✅ | ✅ | ❌ | 🔴 |
| Notification in-app center | ✅ | ✅ | ⚠️ | 🟠 |
| Groupes de notification par type | ✅ | ✅ | ⚠️ | 🟡 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **SMS absent** — au Cameroun, réseau mobile avant internet. SMS pour : visite confirmée, bail signé, renouvellement. API Orange SMS ou Twilio | Critique | Moyen |
| **WhatsApp Business API absent** — envoi de messages structurés WhatsApp (360dialog, Twilio, Meta API) | Critique | Élevé |
| **Centre de notifications in-app** absent — liste chronologique de toutes les notifications | Moyen | Moyen |
| **Notification "visite dans 2h"** (rappel automatique) manquante | Élevé | Faible |

---

## 11. Dépenses & Comptabilité

### État actuel
- `Expense` model existe
- Pas de page dédiée visible dans l'audit frontend

### Benchmark expert (PacificABS, Buildium Accounting 2026)

| Feature | Buildium | MagicDoor | KeyHome | Gap |
|---------|----------|-----------|---------|-----|
| **Catégories dépenses** (maintenance, taxes, assurance) | ✅ | ✅ | ⚠️ | 🟠 |
| **Upload justificatifs** | ✅ | ✅ | ⚠️ | 🟡 |
| **Rapport mensuel dépenses vs revenus** | ✅ | ✅ | ❌ | 🔴 |
| **Export CSV/Excel** | ✅ | ✅ | ❌ | 🟠 |
| **Budget vs Réel** | ✅ | ✅ | ❌ | 🟠 |
| **Suivi loyers impayés** | ✅ | ✅ | ❌ | 🔴 |

### 🔴 Gaps identifiés

| Gap | Impact | Effort |
|-----|--------|--------|
| **Pas de rapport financier** — vue mensuelle revenus (loyers) vs dépenses = NOI. Fondamental pour un investisseur | Élevé | Moyen |
| **Pas d'export** — l'owner ne peut pas extraire ses données comptables | Moyen | Faible |
| **Page dépenses absente du panel** — le modèle existe en backend mais aucune UI | Élevé | Moyen |

---

## Synthèse : Gaps par Priorité

### 🔴 CRITIQUES — Bloquer la croissance ou la légalité (10 gaps)

| # | Feature | Gap | Action |
|---|---------|-----|--------|
| 1 | **Bail** | Audit trail signature inexistant (illégal sans) | `LeaseSignatureAuditLog` table + middleware |
| 2 | **Bail** | PDF signé non généré | Integration laravel-pdf ou gotenberg |
| 3 | **Notifications** | SMS absent (canal #1 Afrique sub-saharienne) | Orange SMS API / Twilio |
| 4 | **Notifications** | WhatsApp Business absent | 360dialog ou Meta Cloud API |
| 5 | **Visites** | Pas de rappel automatique J-1 / H-2 | Queue job `SendViewingReminder` | ✅ **DONE** — `SendViewingReminders` command + `ViewingReminderNotification` + scheduled daily 08:00 |
| 6 | **Trust Score** | Badge vérifié absent sur profil/annonces | Frontend uniquement |
| 7 | **Annonces** | Partage WhatsApp 1-clic absent | `<a href="https://wa.me/?text=...">` |
| 8 | **Boost** | Dashboard ROI boost absent | `AdInteraction` stats endpoint |
| 9 | **Dashboard** | Taux occupation manquant | Calcul depuis `LeaseContract` | ✅ **DONE** — `GET /api/v1/my/stats` via `OwnerDashboardController` |
| 10 | **Contrats** | Alerte renouvellement absente | Queue + notification J-30, J-60 | ✅ **DONE** — `CheckLeaseExpirations` (90/60/30/14/7j) + `LeaseExpiringNotification` scheduled daily 09:00 |

### 🟠 HAUTES — Différenciation concurrentielle (8 gaps)

| # | Feature | Gap |
|---|---------|-----|
| 11 | **Visites** | Prescreening locataire avant confirmation |
| 12 | **Annonces** | Stats par annonce (vues, contacts, taux clic) |
| 13 | **Boost** | Estimation reach avant achat |
| 14 | **Prix marché** | Fourchette loyer suggéré lors de la création |
| 15 | **Trust** | Explication score transparent ("Pour augmenter votre score, faites X") |
| 16 | **Dashboard** | Comparaison N vs N-1 (this month vs last month) |
| 17 | **Abonnements** | Récap utilisation en cours (X/Y annonces) |
| 18 | **Comptabilité** | Page dépenses avec rapport mensuel |

### 🟡 MOYENNES — Amélioration UX (6 gaps)

| # | Feature | Gap |
|---|---------|-----|
| 19 | **Chat** | Réponses rapides prédéfinies |
| 20 | **Chat** | Filtres conversations (non-lus, avec visite) |
| 21 | **Bail** | Relance automatique signature (48h) |
| 22 | **Bail** | Templates bail réutilisables |
| 23 | **Annonces** | Duplication annonce existante |
| 24 | **Notifications** | Centre notifications in-app |

---

## Gaps API & Interopérabilité

### Endpoints manquants (backend)

```
# Dashboard analytics
GET /owner/stats
    → { occupancy_rate, rent_collection_rate, pending_viewings, active_boosts, unread_messages }

# Annonces
GET /ads/{ad}/stats
    → { views_7d, views_30d, contacts_7d, boost_roi: { before, during, after } }

# Boost
GET /ads/{ad}/boost/stats
    → { views_before_7d, views_during, views_after_7d, contacts_during }
POST /ads/{ad}/boost/cancel-refund
    → pro-rata credit refund

# Visites
POST /viewings/{viewing}/remind               # relance manuelle
GET  /owner/viewings/stats                   # conversion pipeline
PATCH /viewings/{viewing}/add-note           # note privée candidat

# Contrats
GET  /lease-contracts/{id}/download-pdf
POST /lease-contracts/{id}/send-reminder
GET  /lease-contracts/{id}/audit-trail
GET  /owner/lease-contracts/expiring         # ?days=30

# Prix marché
GET /market-prices?city_id={id}&property_type={type}
    → { min_xaf, max_xaf, avg_xaf, avg_per_sqm, sample_count, period }

# Notifications
POST /notifications/sms-test
POST /notifications/whatsapp-test
```

### Intégrations tierces manquantes

| Service | Utilisation | Priorité | API |
|---------|-------------|----------|-----|
| **Orange Cameroun SMS** | Rappels visites, alertes bail | 🔴 | SMS Orange B2B |
| **WhatsApp Business** | Canal principal Afrique | 🔴 | Meta Cloud API / 360dialog |
| **GeniusPay Webhook** | Confirmation paiements Mobile Money (existe partiellement) | 🟠 | Déjà en cours |
| **Gotenberg / WeasyPrint** | Génération PDF bail signé | 🔴 | Docker sidecar |
| **INS Cameroun / GlobalPropertyGuide** | Données prix marché | 🟠 | API tierce |
| **Yousign / DocuSign** | E-signature légale avec audit trail | 🟠 | REST API |

---

## Positionnement Concurrentiel

```
                    Proptech maturity (Africa)
           Tier ★        Tier ★★       Tier ★★★      Tier ★★★★+
           CamerImmo     PropertyPro   BuyRentKenya  Ownkey / Property24
               │              │              │              │
               │              │              │              │
KeyHome ───────┼──────────────►              │              │
(aujourd'hui)  │                             │              │
               │                             │              │
KeyHome ───────┼─────────────────────────────►              │
(objectif Q4)  │                                            │
```

**Avantages compétitifs actuels :**
- Seul portail Cameroun avec e-signature bail (même partielle)
- Seul avec système crédit boost
- Chat temps réel lié aux annonces
- Panel bailleur intégré (app mobile + web)
- Multi-paiement : GeniusPay (Orange MM) + Stripe

**Pour atteindre Tier ★★★ (Q4 2026), priorités :**
1. SMS + WhatsApp notifications
2. Badge vérifié sur annonces + profils
3. PDF bail signé téléchargeable
4. Dashboard analytics KPIs
5. Stats par annonce (vues, contacts, boost ROI)

---

## Roadmap Recommandée

### Sprint 1 (2 semaines) — Quick wins haute valeur
- [ ] Badge "Annonce vérifiée" et "Propriétaire vérifié" (frontend uniquement)
- [ ] Bouton partage WhatsApp sur les annonces
- [ ] Alerte renouvellement bail J-30 (job scheduled existant à étendre)
- [ ] Explication trust score (liste des critères et leur statut)
- [ ] Centre notifications in-app (liste chronologique)

### Sprint 2 (3 semaines) — Gaps opérationnels critiques
- [x] Rappel automatique visite J-1 (command `app:send-viewing-reminders` + `ViewingReminderNotification`)
- [ ] Intégration SMS Orange Cameroun (ou Twilio)
- [x] `GET /api/v1/my/stats` endpoint (KPIs dashboard — occupancy, boosts, viewings, messages)
- [x] Stats par annonce `GET /my/ads/{ad}/analytics` — déjà implémenté via `AdAnalyticsController`
- [ ] Page dépenses (`/owner/depenses`)

### Sprint 3 (4 semaines) — Différenciation premium
- [ ] PDF bail signé (Gotenberg sidecar + endpoint)
- [ ] Audit trail signature bail
- [ ] Dashboard boost ROI
- [ ] WhatsApp Business API (360dialog)
- [ ] Fourchette prix suggérée dans formulaire création annonce

### Sprint 4 (6 semaines) — Leadership marché
- [ ] Auto-scheduling visites (créneaux disponibles)
- [ ] Prescreening locataire
- [ ] Templates bail
- [ ] Carte thermique prix par quartier (données internes)
- [ ] Rapport mensuel revenus/dépenses exportable

---

*Sources : PacificABS (2026), Buildium Blog (2026), MagicDoor Blog (2025), Chekin Blog (2026), TheAfricanVestor Cameroon (2026), Ownkey Africa Guide (2026), EliteConsulting Africa (2025), RentRedi Blog (2025), DoorLoop Blog (2025), TaiwosAlam/OurPropertyNG (2025)*
