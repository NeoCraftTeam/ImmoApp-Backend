# Plan d'affaires — KeyHome

**Version :** 1.0 — JUIN 2025  
**Statut :** Document stratégique interne / investisseurs  
**Marque :** KeyHome — *« Votre patrimoine immobilier en poche »*  
**Site :** [keyhome.app](https://keyhome.app)  

---

## Table des matières

1. [Résumé exécutif](#1-résumé-exécutif)
2. [Problème & opportunité marché](#2-problème--opportunité-marché)
3. [Solution & proposition de valeur](#3-solution--proposition-de-valeur)
4. [Produit & feuille de route](#4-produit--feuille-de-route)
5. [Modèle économique & pricing](#5-modèle-économique--pricing)
6. [Analyse concurrentielle](#6-analyse-concurrentielle)
7. [Go-to-market & acquisition](#7-go-to-market--acquisition)
8. [Opérations & technologie](#8-opérations--technologie)
9. [Équipe & gouvernance](#9-équipe--gouvernance)
10. [Finances prévisionnelles](#10-finances-prévisionnelles)
11. [Risques & mitigations](#11-risques--mitigations)
12. [Besoins de financement & utilisation des fonds](#12-besoins-de-financement--utilisation-des-fonds)
13. [KPIs & jalons](#13-kpis--jalons)
14. [Annexes](#14-annexes)

---

## 1. Résumé exécutif

**KeyHome** est une **marketplace immobilière SaaS** orientée **location et vente à moyen/long terme** en Afrique subsaharienne francophone — explicitement **pas** une plateforme de location vacances type Airbnb. La plateforme connecte **locataires, acheteurs, bailleurs particuliers, agences immobilières** et **administrateurs** sur un écosystème unique couvrant la recherche intelligente, la confiance (scores, KYC, modération), les visites (virtuelles 3D et physiques planifiées), la messagerie sécurisée, les paiements locaux (Mobile Money) et internationaux (carte Stripe en EUR), ainsi que des outils professionnels pour les annonceurs (dashboard, contrats PDF, QR terrain).

**Positionnement :** première plateforme « proptech » de référence pour le **Cameroun** (focus Yaoundé, Douala, Bafoussam), avec extension naturelle vers la **zone CEMAC** et l’**UEMOA** (Abidjan, Dakar, etc.). Devises natives **XAF/XOF**, avec affichage **27 devises** pour la diaspora et les investisseurs.

**Différenciation produit (déjà implémentée ou en cours dans le dépôt) :**

- Recherche IA en français naturel, par photo et vocale
- **KeyScore** (qualité annonce) et **Trust Score** bidirectionnel opt-in (locataire / bailleur)
- Visites virtuelles 360° (tours panoramiques)
- Modèle **crédits de déblocage** (contact qualifié, numéro non exposé publiquement)
- Paiements **mobiles** (MTN / Orange Money) + **carte** 
- Chat en temps réel

**Modèle de revenus :** crédits clients (déblocage contact), abonnements agences/bailleurs pro (boost, volume d’annonces, analytics), boost annonces, packs promo — **sans commission sur les loyers** documentée dans le code

**Objectif stratégique 24 mois :** devenir la couche digitale de confiance du parcours « trouver / louer / gérer » au Cameroun, puis répliquer le playbook pays par pays en zone francophone.

> **Note :** Les projections financières des sections 10 et 12 sont des **hypothèses illustratives** — aucune donnée de revenus réels n’est publiée dans le dépôt à ce stade.

---

## 2. Problème & opportunité marché

### 2.1 Problèmes structurels (Cameroun & Afrique francophone)

| Problème | Impact |
|----------|--------|
| **Opacité et asymétrie d’information** | Prix non comparables, quartiers mal documentés, annonces incomplètes |
| **Manque de confiance** | Arnaques (photos volées, bailleurs fantômes), intermédiaires peu fiables |
| **Friction opérationnelle** | Appels sans réponse, visites inutiles, coordination WhatsApp dispersée |
| **Numéros exposés** | Spam, curieux, perte de temps pour les bailleurs |
| **Marché fragmenté** | Facebook, groupes WhatsApp, panneaux physiques, agences locales sans standard |
| **Diaspora & investisseurs** | Difficulté à évaluer prix, distance, devise, paiement depuis l’étranger |

Ces problèmes sont particulièrement aigus à **Douala** et **Yaoundé** (forte demande locative, mobilité interne, diaspora camerounaise en Europe/Amérique du Nord).

### 2.2 Taille et dynamique du marché (cadre)

**Cameroun (focus initial)**

- Population ~28 M (2024), urbanisation croissante, marché locatif actif dans les métropoles
- Secteur immobilier non coté de façon centralisée ; estimation **plusieurs centaines de milliers** de transactions locatives/ventes annuelles informelles et formelles
- Pénétration smartphone élevée ; Mobile Money (MTN, Orange) dominant pour les paiements digitaux

**CEMAC / UEMOA (expansion)**

- Zone monétaire **XAF** (CEMAC) et **XOF** (UEMOA) — même logique produit (FCFA, Mobile Money, réglementation locale)
- Villes cibles documentées : Douala, Yaoundé, Bafoussam, Abidjan, Dakar, et au-delà

**Opportunité proptech**

- Peu d’acteurs locaux combinant **IA + confiance + 3D + paiements locaux ** sur un seul stack
- Fenêtre avant consolidation par des portails régionaux ou des géants e-commerce (ex. Jumia House historique)
- **Digitalisation post-COVID** des parcours recherche / visite / pré-sélection locataire

### 2.3 Segments clients

| Segment | Besoin principal | Willingness to pay |
|---------|------------------|-------------------|
| **Locataire / acheteur** | Trouver vite, éviter arnaques, comparer | Crédits (faible ticket, volume) |
| **Bailleur particulier** | Visibilité, filtrer sérieux, outils pro | Boost, abonnement léger (à développer côté bailleur individuel) |
| **Agence immobilière** | Multi-annonces, équipe, marque, stats | Abonnement mensuel (15k–75k FCFA/mois seed) |
| **Diaspora** | Prix en EUR/USD, carte, visite 3D | Crédits + Stripe |
| **Administrateur plateforme** | Modération, revenus, conformité | Interne (SaaS opéré par KeyHome) |

---

## 3. Solution & proposition de valeur

### 3.1 Vision produit

**Une seule plateforme, deux expériences mobiles distinctes :**

- **Client**   — recherche, favoris, déblocage, messages, paiements crédits
- **Bailleur** — publication, modération, analytics, QR pancartes, abonnements, visites

### 3.2 Proposition de valeur par persona

**Locataire / acheteur**

- Recherche en **langage naturel** (« studio meublé Akwa 50 000 F »), **photo** et **voix**
- **Carte interactive**, heatmap des prix, estimation loyer, géolocalisation relative
- **KeyScore** visible avant déblocage ; **Trust Score** sur profils (avec consentement)
- **Visite 3D** 24h/24 ; comparaison d’annonces ; alertes recherche push
- **Déblocage par crédits** → contact qualifié, chat intégré, numéro masqué avant déblocage
- **27 devises** pour diaspora ; paiement Mobile Money ou carte

**Bailleur / agence**

- Publication guidée (wizard 6 étapes), **aperçu temps réel**, auto-save brouillon serveur
- **KYC** → badge « Bailleur vérifié »
- **IA** : conseiller de prix, description enrichie
- **Boost** et abonnements avec boost_score intégré
- **Calendrier visites** , réservations, notifications
- **Contrats bail PDF**, **QR A5 / carte de visite** pour pont physique → digital
- Dashboard analytics (vues, favoris, déblocages, RDV)
- **Messagerie** professionnelle (temps réel, pièces jointes, notes vocales, réactions)
- Panel **agence** multi-utilisateur


- Modération annonces, signalements, paiements, remboursements, métriques acquisition, newsletter, sondages,

---

## 4. Produit & feuille de route

### 4.1 État actuel (MVP / pré-production documenté)
:

| Domaine | Maturité (indicatif) | Éléments clés |
|---------|---------------------|---------------|
| **Catalogue & recherche** | Avancé | Scout/Meilisearch, filtres, carte, NLP, image search, recommandations |
| **Confiance** | Avancé | Modération, signalements, KYC, KeyScore, Trust Score consent |
| **Monétisation** | Opérationnel | Crédits, abonnements seed, boost, Stripe + Flutterwave |
| **Messagerie** | Avancé | Reverb, FCM, réactions, vocal, E2EE client désactivé par défaut (serveur) |
| **Bailleur** | Avancé | Wizard annonce, tours 360°, QR PDF, leases, expenses, équipe |
| **Admin** | Avancé | Filament Admin (22+ ressources), métriques acquisition |
| **Mobile natif** | Partiel | Shell React Native bailleur (`mobile/bailleur/`) |
| **Conformité** | En cours | GDPR (export/suppression), consent Trust Score ; e-signature bail à renforcer (audit interne) |

### 4.2 Roadmap 12–24 mois (proposée)

**Phase 1 — Lancement Cameroun (0–6 mois)**

- Ouverture publique keyhome.app (SEO, contenus villes `/immobilier/[ville]`)
- Campagnes acquisition Douala / Yaoundé (organique + partenariats agences pilotes)
- Objectif : masse critique annonces vérifiées + premiers revenus crédits
- Durcissement : modération SLA, support FR, monitoring Nightwatch/Sentry

**Phase 2 — Consolidation & rétention (6–12 mois)**

- Optimisation conversion (onboarding, Turnstile, PWA install prompts)
- Rétention push (win-back, alertes prix, rappels visites — déjà codés)
- Abonnements bailleurs particuliers (packs entre « crédit-only » et plans agence)
- App native bailleur (RN) en store si métriques PWA le justifient
- Extension villes secondaires CM + test Abidjan ou Dakar

**Phase 3 — Scale zone francophone (12–24 mois)**

- Localisation contenu (i18n `next-intl` prévu — aujourd’hui FR hardcodé)
- Paiements agrégés par pays (opérateurs UEMOA)
- API partenaires (plan Enterprise seed : « API dédiée »)
- ISO/processus KYC renforcés ; partenariats bancaires / assurances (hors scope code actuel)
- Équipe commerciale agences (B2B inside sales)

**Hors scope court terme (explicitement)**

- Location vacances / Airbnb-like
- Commission sur loyers encaissés via la plateforme (non implémentée)
- E2EE bout-en-bout réactivée sans passphrase portable (roadmap technique documentée)

---

## 5. Modèle économique & pricing

### 5.1 Philosophie

KeyHome monetise la **valeur de la mise en relation qualifiée** et les **outils SaaS** pour les professionnels — pas la commission sur le loyer mensuel (modèle différent des OTAs).

**Flux de revenus documentés :**

1. **Crédits (B2C)** — déblocage contact annonce
2. **Abonnements (B2B agences / bailleurs pro)** — volume, boost, badges, support
3. **Boost annonces** — visibilité (inclus ou additionnel via `AdBoostService`)
4. **Paiements one-shot** — unlock ad, boost, packs crédits (via `PaymentService` multi-gateway)

**Pas de ligne « commission transaction immobilière »** dans le code analysé.

### 5.2 Grille tarifaire actuelle (seeders — référence produit)

#### Crédits clients (`PointSystemSeeder`)

| Pack | Prix (FCFA) | Crédits | Coût indicatif / déblocage* |
|------|-------------|---------|----------------------------|
| Pack Starter | 1 000 | 10 | ~100 F / unlock |
| Pack Pro | 4 000 | 50 | ~80 F / unlock |
| Pack Premium | 7 000 | 120 | ~58 F / unlock |

\* **Coût par déblocage :** `unlock_cost_points = 2` crédits → **2 crédits = 1 contact débloqué**.

**Bonus bienvenue :** `welcome_bonus_points = 5` (≈ 2 déblocages + marge).

#### Abonnements agences (`SubscriptionPlanSeeder`)

| Plan | Mensuel (FCFA) | Annuel (FCFA) | Max annonces | Boost |
|------|----------------|---------------|--------------|-------|
| Basic | 15 000 | 150 000 | 20 | +10 pts / 7 j |
| Premium | 35 000 | 350 000 | 50 | +25 pts / 14 j |
| Enterprise | 75 000 | 750 000 | Illimité | +50 pts / 30 j |

Les prix annuels seed reflètent ~**2 mois offerts** vs mensuel.

#### Paiements

- **Flutterwave :** Mobile Money (MTN, Orange), legacy umbrella
- **Stripe :** carte, facturation **EUR** (peg `1 EUR = 655,957 XAF`), montants canoniques stockés en XAF
- **Cartes enregistrées** (Stripe Customer, off-session) pour achats crédits récurrents diaspora

#### Codes promo

- Admin / bailleurs peuvent créer des promos (`PromoCode`) sur types `credit`, `subscription`, etc.

### 5.3 Unit economics (illustratif)

**Hypothèse déblocage :**

- Revenu brut par déblocage (Pack Pro) : 4 000 / 50 × 2 = **160 FCFA** par déblocage payant (hors bonus)
- Coûts variables : ~30–40 % (passerelle paiement, infra, modération marginal) → **marge brute ~60–70 %** cible SaaS digital

**Hypothèse agence Basic :**

- 15 000 FCFA/mois ; si 1 agence publie 15 annonces actives et génère 30 déblocages indirects sur son catalogue → revenu mixte abonnement + effet réseau

*Ces ratios sont des **hypothèses de travail** — à calibrer sur données réelles post-lancement.*

### 5.4 Évolution pricing (recommandations stratégiques)

- **Tier bailleur particulier** (5–10k FCFA/mois) : 3–5 annonces + 1 boost/mois
- **Boost à la carte** (prix fixe non seedé — à définir en prod, ex. 2 000–5 000 FCFA / 7 jours)
- **Lead premium** pour agences (mise en avant profil agence)
- **API / data** Enterprise (B2B2C portails partenaires)

---

## 6. Analyse concurrentielle

### 6.1 Cartographie

| Acteur | Type | Forces | Faiblesses vs KeyHome |
|--------|------|--------|------------------------|
| **Facebook / WhatsApp** | Gratuit, viral | Reach, habitudes | Pas de confiance structurée, spam, pas de 3D/IA/paiement intégré |
| **Jumia House / anciens portails** | Marketplace | Notoriété marque | Souvent catalogue statique, peu d’outils bailleur locaux |
| **Meqasa, etc. (Ghana/region)** | Portail annonces | SEO, volume | Hors focus CM initial ; moins IA + Mobile Money CM |
| **Agences traditionnelles** | Offline + réseau | Relation humaine, stock | Coût, lenteur, pas de self-service 24/7 |
| **Airbnb / Booking** | Court séjour | UX globale | **Segment différent** — KeyHome = long terme + vente |
| **Leboncoin / SeLoger (diaspora)** | Référence UX | Maturité | Pas adapté XAF, Mobile Money, quartiers locaux OSM |

### 6.2 Matrice différenciation

| Critère | KeyHome | Portails classiques | Réseaux sociaux |
|---------|---------|---------------------|-----------------|
| Recherche IA FR | ✅ | ❌ / limité | ❌ |
| Visite 3D | ✅ | Rare | ❌ |
| Trust / KeyScore | ✅ | Partiel | ❌ |
| Mobile Money | ✅ | Variable | ❌ |
| Chat + masquage tel | ✅ | Formulaire / tel brut | WhatsApp hors plateforme |
| PWA double app | ✅ | Site unique | N/A |
| Modération | ✅ | Variable | ❌ |
| Abonnement agence SaaS | ✅ | Rare | ❌ |

### 6.3 Stratégie concurrentielle

- **Court terme :** gagner la **qualité perçue** (annonces vérifiées, 3D, KYC) sur Douala/Yaoundé — pas la guerre du volume gratuit
- **Moyen terme :** **partenariats agences** (leur ERP = KeyHome) pour effet réseau annonces
- **Long terme :** données marché (heatmap, estimation) comme **actif B2B** (banques, promoteurs)

---

## 7. Go-to-market & acquisition

### 7.1 Cibles prioritaires

1. **Locataires actifs** 18–35 ans, urbains, smartphone-first
2. **Bailleurs multi-biens** et **agences** 5–50 annonces
3. **Diaspora** FR/BE/CA/US cherchant pour famille ou investissement

### 7.2 Canaux (alignés produit & docs marketing)

| Canal | Tactique | Mesure (admin panel) |
|-------|----------|----------------------|
| **SEO / GEO** | Pages ville `/immobilier/*`, sitemap, JSON-LD, blog | Trafic organique, conversion inscription |
| **PWA** | Install prompts client + owner, offline partiel owner | Taux install, rétention |
| **Réseaux sociaux** | Calendrier contenus (`docs/marketing/`), affiches 1080×1350, CTA keyhome.app | UTM campagnes |
| **QR terrain** | Pancartes A5 bailleurs → scan annonce | `utm_medium=qr`, scans |
| **Partenariats agences** | Onboarding équipe, plan Premium/Enterprise | Abonnements actifs |
| **Push / email** | Alertes recherche, rétention, newsletter | Ouvertures, reactivation |
| **Bouche-à-oreille** | Crédits bienvenue, Trust Score | NPS, referral (à instrumenter) |

### 7.3 Attribution & analytics

- **UTM** + `AcquisitionChannelClassifier` / `UtmAttributionService`
- Dashboard admin : visiteurs, source principale, conversion visiteur → inscription, canal d’inscription
- **Google Tag Manager** / analytics (CSP configurée côté Next.js)

### 7.4 Message & branding

- **Tagline :** « Votre patrimoine immobilier en poche »
- **Promesses marketing** (flyers) : annonces vérifiées, visites 3D, bailleurs certifiés, location & vente — **sans** Mobile Money sur visuels (VO crédits possible)
- **CTA standard :** « Accéder à la plateforme » + `keyhome.app`

### 7.5 Plan lancement (90 jours type)

| Semaine | Action |
|---------|--------|
| S1–2 | Soft launch Douala : 10 agences pilotes, 200 annonces modérées |
| S3–4 | Campagne social + QR pancartes quartiers cibles |
| S5–8 | SEO villes + influenceurs immo locaux + live Twitch (doc marketing) |
| S9–12 | Extension Yaoundé, premier bilan KPI, ajustement pricing promo |

---

## 8. Opérations & technologie

### 8.1 Architecture technique (monorepo)

| Composant | Technologie |
|-----------|-------------|
| API | Laravel 12, PHP 8.5, Sanctum, API `/api/v1/` |
| Frontend | Next.js 16, React 19, MUI v7, TanStack Query |
| Admin | Filament 4 (Admin, Agency, Bailleur panels) |
| DB | PostgreSQL 15 + PostGIS |
| Recherche | Laravel Scout + Meilisearch |
| Cache / queues | Redis (critical, payments, emails, tours, default) |
| Temps réel | Laravel Reverb (WebSockets), Echo/Pusher client |
| Stockage | Cloudflare R2 (prod), Spatie Media Library (WebP) |
| Paiements | Flutterwave + Stripe (Cashier 16) |
| Auth | Sanctum, Clerk (OAuth exchange), WebAuthn passkeys, Socialite |
| IA | Groq, OpenAI, Gemini, Together, Mistral (search + vision + description) |
| PDF | DomPDF (contrats, reçus, QR placarde, carte visite) |
| Observabilité | Sentry, Pulse, Telescope, Nightwatch |
| CI/CD | GitLab CI → VPS ; frontend Vercel (`cedrickdev` / `main`) |
| Edge | Traefik TLS, Cloudflare CDN API + R2 |

### 8.2 Sécurité & conformité (état documenté)

- **Chiffrement chat serveur** AES-256-CBC + HMAC ; canal privé authentifié
- **Turnstile** login/register ; rate limiting API par rôle
- **MFA** admin Filament (TOTP + email) ; passkeys WebAuthn
- **RGPD :** export/suppression données, consentement Trust Score
- **Webhooks paiements** : transactions DB, idempotence `payment_id`
- **Points d’attention audit interne :** e-signature bail (renforcement OTP/hash), MFA API admin routes

### 8.3 Opérations quotidiennes

| Fonction | Processus |
|----------|-----------|
| Modération annonces | File `PENDING` → `AVAILABLE` ; emails approbation bailleur |
| Support | Email + in-app ; priorité abonnés Premium/Enterprise |
| Signalements | Workflow `AdReport` → masquage / suspension |
| Paiements | Réconciliation Flutterwave/Stripe, remboursements `RefundService` |
| Contenu | Newsletter, sondages publics/anonymes |
| Sauvegardes | Spatie Backup → S3 |

### 8.4 Infrastructure & coûts ops (ordre de grandeur)

- **VPS Docker** : app, worker×2, reverb, nginx, postgres, redis, meilisearch (limits mémoire documentées)
- **Vercel** : frontend
- **APIs variables :** LLM, Mapbox, Flutterwave/Stripe fees, FCM gratuit, R2 egress
- **Coût marginal utilisateur actif** : dominé par IA (cache 24h search) et stockage média — **à optimiser** par quotas et modèles légers (Groq/Llama déjà priorisé)

---

## 9. Équipe & gouvernance

> **Note :** Le dépôt ne contient **pas de données publiques** sur les fondateurs ou effectifs. Structure **recommandée** pour exécution du plan.

### 9.1 Organigramme cible (18–24 mois)

```
CEO / Fondateur
├── Produit & Tech (CTO)
│   ├── Backend Laravel (2)
│   ├── Frontend Next.js (1–2)
│   └── DevOps / SRE (0,5–1)
├── Growth & Marketing (CMO)
│   ├── Content / SEO / Social (1)
│   └── Partenariats agences (1 BDR)
├── Opérations & Trust (COO)
│   ├── Modération & support (2–4)
│   └── KYC / compliance (0,5)
└── Finance & Admin (CFO part-time)
```

### 9.2 Rôles critiques à pourvoir

| Rôle | Mission |
|------|---------|
| **Head of Growth CM** | Acquisition locale, partenariats agences Douala/Yaoundé |
| **Lead modération** | SLA < 24h sur annonces, qualité catalogue |
| **Ingénieur paiements** | Flutterwave/Stripe, réconciliation, fraude |
| **Product designer** | Continuité double PWA, accessibilité WCAG |

### 9.3 Gouvernance

- **Société :** [à compléter — forme juridique Cameroun / holding]
- **Comité produit** : hebdomadaire (roadmap, métriques KPI §13)
- **Comité risques** : trimestriel (paiements, légal, sécurité)
- **Advisors souhaités :** immobilier local, fintech Mobile Money, proptech Afrique

### 9.4 Propriété intellectuelle

- Marque **KeyHome**, codebase propriétaire (monorepo NeoCraftTeam)
- Données agrégées marché (heatmap, estimations) — actif à protéger contractuellement

---

## 10. Finances prévisionnelles

> **⚠️ AVERTISSEMENT :** Tous les chiffres de cette section sont des **hypothèses illustratives** pour structurer une discussion investisseur. Ils **ne reflètent pas** des résultats financiers réels publiés dans le dépôt.

### 10.1 Hypothèses de marché (TAM / SAM / SOM)

**Définitions :**

- **TAM** (Cameroun urbain locatif + vente digitale adressable) : ~**50–80 Mrd FCFA/an** d’équivalent « services autour de la transaction » (annonces, agences, outils) — ordre de grandeur **10–15 M EUR** si on monetise 1–3 % de la valeur locative annuelle urbaine estimée (~500k–1M ménages locataires × loyer moyen 80–150k FCFA/mois × taux outillage). *Fourchette large volontairement.*
- **SAM** (utilisateurs smartphone cherchant en ligne CM) : **~500k–1M** personnes/an touchables digital
- **SOM 3 ans** (part de marché KeyHome) : **2–5 %** du SAM actif → **10k–50k** comptes enregistrés, **3k–15k** MAU

### 10.2 Hypothèses d’activité (base)

| Paramètre | Année 1 | Année 2 | Année 3 |
|-----------|---------|---------|---------|
| Comptes enregistrés (cumul) | 8 000 | 25 000 | 60 000 |
| MAU | 2 500 | 9 000 | 22 000 |
| Déblocages/mois (moyenne) | 1 500 | 8 000 | 25 000 |
| Revenu moyen/déblocage (FCFA) | 150 | 140 | 130 |
| Agences payantes (fin année) | 15 | 60 | 150 |
| ARPU agence/mois (FCFA) | 25 000 | 30 000 | 35 000 |

### 10.3 Compte de résultat simplifié (FCFA millions) — scénario **BASE**

| Poste | An 1 | An 2 | An 3 |
|-------|------|------|------|
| **Revenus crédits** | 27 | 134 | 390 |
| **Revenus abonnements** | 5 | 22 | 63 |
| **Autres (boost, promo)** | 3 | 12 | 35 |
| **Revenus totaux** | **35** | **168** | **488** |
| Coûts variables (passerelles ~35 %) | 12 | 59 | 171 |
| **Marge brute** | **23** | **109** | **317** |
| Salaires & équipe | 45 | 72 | 95 |
| Marketing | 18 | 35 | 50 |
| Infra & APIs | 8 | 15 | 22 |
| Admin & légal | 4 | 6 | 8 |
| **EBITDA** | **-52** | **-19** | **142** |

*Année 1 fortement déficitaire = normal phase lancement SaaS marketplace.*

### 10.4 Scénarios

| Scénario | Hypothèse clé | Revenus An 3 |
|----------|---------------|--------------|
| **Prudent** | MAU ×0,6, conversion crédit -30 % | ~290 M FCFA |
| **Base** | Tableau ci-dessus | ~488 M FCFA |
| **Ambitieux** | Viral diaspora + 200 agences | ~750 M FCFA |

### 10.5 Besoin de trésorerie (lien §12)

- **Burn An 1** (base) : ~50–60 M FCFA hors revenus
- **Runway cible levée seed :** 18–24 mois jusqu’à break-even opérationnel (An 2–3)

### 10.6 Indicateurs financiers cibles

| Ratio | Cible An 3 |
|-------|------------|
| Marge brute | > 65 % |
| LTV/CAC (client crédits) | > 3× |
| Churn agences mensuel | < 5 % |
| Part revenus B2B (abos) | > 25 % |

---

## 11. Risques & mitigations

| Risque | Probabilité | Impact | Mitigation |
|--------|-------------|--------|------------|
| **Liquidité chicken-and-egg** (peu d’annonces) | Élevée | Critique | Agences pilotes, QR terrain, modération rapide, contenu SEO ville |
| **Arnaques / réputation** | Moyenne | Élevé | KYC, modération, signalements, KeyScore, assurance partenaire (future) |
| **Fraude paiements / webhooks** | Moyenne | Élevé | `DB::transaction`, idempotence abonnements, réconciliation |
| **Dépendance APIs IA** | Moyenne | Moyen | Multi-provider, cache 24h, regex fallback |
| **Concurrence portails établis** | Moyenne | Moyen | Différenciation 3D + IA + MM ; focus hyper-local CM |
| **Réglementation données / KYC** | Moyenne | Moyen | RGPD-like, DPO, hébergement, registres traitement |
| **Coûts infra / LLM** | Moyenne | Moyen | CDN cache, limites quotas, modèles légers |
| **Scaling technique** | Faible–moyen | Élevé | Indexes DB, PgBouncer, pagination RecommendationEngine (travaux audit) |
| **Taux de change / Stripe EUR** | Faible | Moyen | Peg BEAC documenté, métadonnées audit XAF |
| **E-signature bail faible valeur légale** | Moyenne | Moyen | Roadmap OTP + hash + audit (audit interne Mai 2026) |

---

## 12. Besoins de financement & utilisation des fonds

### 12.1 Montant indicatif (seed / pré-seed)

**Fourchette illustrative :** **150–350 M FCFA** (≈ **230k–530k EUR**) pour **18–24 mois** de runway en scénario base.

*À ajuster selon taille équipe réelle et coûts salariaux Douala/Yaoundé.*

### 12.2 Utilisation des fonds (% cible)

| Poste | % | Détail |
|-------|---|--------|
| **Produit & tech** | 35 % | Renfort dev, mobile RN, dette technique audit (perf, sécurité) |
| **Acquisition & brand** | 30 % | Social, SEO, partenariats agences, événements |
| **Opérations trust** | 20 % | Modération, KYC, support FR |
| **Infra & APIs** | 10 % | VPS, Meilisearch, Mapbox, LLM |
| **Légal & admin** | 5 % | Statuts, CGU, conformité données |

### 12.3 Jalons déblocage tranches (exemple)

| Tranche | Condition | % fonds |
|---------|-----------|---------|
| T1 | Closing | 40 % |
| T2 | 5k MAU + 500 annonces actives | 30 % |
| T3 | MRR > 15 M FCFA (3 mois glissants) | 30 % |

### 12.4 Sorties & vision investisseur

- **Stratégique :** acquisition par groupe immobilier, telco (Orange/MTN ecosystem), ou marketplace régionale
- **Financière :** série A zone UEMOA après dominance CM (~24–36 mois)
- **Pas d’IPO court terme** — marché fragmenté

---

## 13. KPIs & jalons

### 13.1 KPIs produit & croissance

| KPI | Définition | Cible M6 | Cible M12 |
|-----|------------|----------|-----------|
| **Annonces AVAILABLE** | Catalogue actif modéré | 800 | 3 000 |
| **Ratio annonces vérifiées KYC** | % bailleurs badge | > 40 % | > 60 % |
| **MAU** | Comptes actifs/mois | 2 000 | 8 000 |
| **Déblocages/mois** | `UnlockedAd` créés | 1 200 | 6 000 |
| **Conversion visiteur → inscription** | Admin acquisition widget | > 3 % | > 5 % |
| **PWA installs** | Events install prompt | 500 | 3 000 |
| **Taux complétion wizard annonce** | Brouillon → PENDING | > 50 % | > 65 % |
| **NPS locataires** | Enquête trimestrielle | > 30 | > 40 |

### 13.2 KPIs revenus

| KPI | Cible M6 | Cible M12 |
|-----|----------|-----------|
| **MRR total (FCFA)** | 2 M | 10 M |
| **MRR abonnements** | 0,5 M | 3 M |
| **ARPU déblocage** | 150 F | 145 F |
| **Agences payantes** | 12 | 45 |

### 13.3 KPIs qualité & ops

| KPI | Cible |
|-----|-------|
| Délai modération médiane | < 12 h |
| Taux signalements / annonces | < 2 % |
| Uptime API | > 99,5 % |
| Temps réponse support | < 4 h ouvrées |

### 13.4 Jalons majeurs

| Date | Jalon |
|------|-------|
| M0 | Lancement public keyhome.app CM |
| M3 | 300 annonces, 10 agences payantes |
| M6 | Break-even contribution marge sur déblocages (hors fixe) |
| M9 | Ouverture 2e ville pays OU 2e pays pilote |
| M12 | Série seed ou A si MRR > 10 M FCFA |
| M18 | App native bailleur store (si KPI PWA) |
| M24 | 150+ agences zone francophone |

---

## 14. Annexes

### 14.1 Glossaire

| Terme | Définition |
|-------|------------|
| **CEMAC** | Communauté Économique et Monétaire de l’Afrique Centrale (XAF) |
| **UEMOA** | Union Économique et Monétaire Ouest-Africaine (XOF) |
| **FCFA** | Franc CFA (XAF ou XOF selon zone) |
| **KeyScore** | Score 0–100 qualité/fiabilité d’une **annonce** |
| **Trust Score** | Score 0–100 réputation **utilisateur** (locataire ou bailleur), opt-in |
| **Déblocage** | Action consommant des crédits pour accéder contact + données exclusives |
| **Boost** | Mise en avant annonce (`boost_score`, expiration) |
| **PWA** | Progressive Web App installable |
| **KYC** | Vérification identité bailleur |
| **Mobile Money** | Paiement téléphonique MTN/Orange via Flutterwave |
| **Peg BEAC** | 1 EUR = 655,957 XAF (facturation Stripe) |

### 14.2 Sources documentaires (dépôt)

| Document | Chemin |
|----------|--------|
| Pitch produit FR | `docs/officialDocs/PITCH_KEYHOME_FR.md` |
| Pitch admin | `docs/officialDocs/ADMIN_PANEL_PITCH_FR.md` |
| Guide agents / architecture | `AGENTS.md` |
| README technique | `README.md` |
| Marketing visuels | `docs/marketing/plan-contenus-visuels-keyhome.md` |
| Tarifs crédits (seed) | `database/seeders/PointSystemSeeder.php` |
| Tarifs abonnements (seed) | `database/seeders/SubscriptionPlanSeeder.php` |

### 14.3 Stack versions (référence AGENTS.md)

PHP 8.5.4 · Laravel 12 · Filament 4 · Next.js 16 · React 19 · Meilisearch v10 · Sanctum 4 · Pest 4

### 14.4 Contacts & prochaines étapes document

- Compléter §9 (équipe réelle, cap table)
- Remplacer hypothèses §10 par **données analytics** post-lancement (admin dashboard, Stripe, Flutterwave)
- Valider grille boost à la carte (non seedée)
- Revue juridique CGU / contrats PDF / e-signature

---

**Document préparé à partir des capacités produit et seeds documentés dans le monorepo KeyHome (Mai 2026).**

*© KeyHome — Votre patrimoine immobilier en poche*
