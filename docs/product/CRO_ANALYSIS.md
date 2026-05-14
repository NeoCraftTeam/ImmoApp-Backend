# Analyse d'Optimisation du Taux de Conversion (CRO) — KeyHome ImmoApp

> **Date** : 15 mars 2026  
> **Application** : KeyHome — Marketplace immobilière panafricaine  
> **Devises** : XAF / XOF (Franc CFA)  
> **Passerelle de paiement** : Flutterwave (Mobile Money + Carte)

---

## Table des matières

- [VERSION FRANÇAISE](#version-française)
  - [1. Description de l'application](#1-description-de-lapplication)
  - [2. Cartographie du funnel & analyse de performance](#2-cartographie-du-funnel--analyse-de-performance)
  - [3. Analyse granulaire des abandons](#3-analyse-granulaire-des-abandons)
  - [4. Recommandations d'optimisation priorisées](#4-recommandations-doptimisation-priorisées)
  - [5. Portfolio de tests A/B](#5-portfolio-de-tests-ab)
  - [6. Optimisation stratégique de la page tarifs](#6-optimisation-stratégique-de-la-page-tarifs)
- [ENGLISH VERSION](#english-version)
  - [1. Application Description](#1-application-description)
  - [2. Funnel Mapping & Performance Analysis](#2-funnel-mapping--performance-analysis)
  - [3. Granular Drop-off Analysis](#3-granular-drop-off-analysis)
  - [4. Prioritized Optimization Recommendations](#4-prioritized-optimization-recommendations)
  - [5. A/B Test Portfolio](#5-ab-test-portfolio)
  - [6. Pricing Page Strategic Optimization](#6-pricing-page-strategic-optimization)
- [SUGGESTIONS D'IMPLÉMENTATION / IMPLEMENTATION SUGGESTIONS](#suggestions-dimplémentation--implementation-suggestions)

---

# VERSION FRANÇAISE

---

## 1. Description de l'application

KeyHome est une marketplace immobilière panafricaine connectant locataires et propriétaires/agents. Le revenu provient de deux sources :

1. **Packs de crédits** — Les clients achètent des crédits pour débloquer les coordonnées des propriétaires (2 crédits/déblocage).
2. **Abonnements agences** — Les agents paient un abonnement mensuel/annuel pour publier et booster leurs annonces.

**Structure tarifaire :**

| Plan | Mensuel | Annuel | Annonces max | Boost |
|------|---------|--------|--------------|-------|
| **Basic** | 15 000 FCFA | 150 000 FCFA | 20/mois | +10 pts / 7j |
| **Premium** | 35 000 FCFA | 350 000 FCFA | 50/mois | +25 pts / 14j |
| **Enterprise** | 75 000 FCFA | 750 000 FCFA | Illimité | +50 pts / 30j |

| Pack crédits | Prix | Crédits | Coût/crédit | Déblocages |
|--------------|------|---------|-------------|------------|
| **Starter** | 1 000 FCFA | 10 | 100 FCFA | 5 contacts |
| **Pro** ⭐ | 4 000 FCFA | 50 | 80 FCFA | 25 contacts |
| **Premium** | 7 000 FCFA | 120 | 58 FCFA | 60 contacts |

**Offre gratuite :** 5 crédits de bienvenue à l'inscription (= 2–3 déblocages maximum).

---

## 2. Cartographie du funnel & analyse de performance

### 2.1 Funnel principal : Client → Conversion payante

> *Taux estimés à partir de benchmarks sectoriels pour les marketplaces freemium en marchés émergents. À valider avec vos données analytiques réelles (SiteVisit, AdInteraction).*

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    FUNNEL CLIENT KEYHOME                                  │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌───────────────────┐                                                   │
│  │  VISITE PAGE       │  100%          (base : 10 000 visiteurs)         │
│  │  D'ACCUEIL         │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── taux de conversion 8,0% ────                            │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  INSCRIPTION       │  8,0%          (800 utilisateurs)                │
│  │  Formulaire 3 étapes│                                                 │
│  └───────┬───────────┘                                                   │
│          │  ──── taux de conversion 55% ────                             │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  EMAIL VÉRIFIÉ     │  4,4%          (440 utilisateurs)                │
│  │  OTP / Lien        │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── taux de conversion 72% ────                             │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  ONBOARDING        │  3,17%         (317 utilisateurs)                │
│  │  TERMINÉ           │                                                  │
│  │  Modal + Visite    │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── taux de conversion 65% ────                             │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  PREMIER           │  2,06%         (206 utilisateurs)                │
│  │  DÉBLOCAGE         │                                                  │
│  │  Crédits gratuits  │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── taux de conversion 14% ────                             │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  CONVERSION        │  0,29%         (29 utilisateurs)                 │
│  │  PAYANTE           │                                                  │
│  │  Achat de crédits  │                                                  │
│  └───────────────────┘                                                   │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Funnel secondaire : Agent → Abonnement

```
Visite ──(2,0%)──▶ Inscription Agent ──(58%)──▶ Email Vérifié ──(15%)──▶ Abonné ──(72%)──▶ 1ère Annonce
                   200 agents               116 agents              17 agents           12 agents
```

### 2.3 Métriques clés

| Métrique | Valeur |
|----------|--------|
| **Abandon le plus élevé** | **Premier déblocage → Conversion payante (86% d'abandon)** |
| **Deuxième abandon** | Visite → Inscription (92% d'abandon) |
| **Troisième abandon** | Inscription → Email vérifié (45% d'abandon) |
| **Taux global Visite → Payant** | **0,29%** |
| **Taux Inscription → Payant** | **3,6%** |

---

## 3. Analyse granulaire des abandons

### 3.1 ABANDON #1 : Premier déblocage → Conversion payante (86% d'attrition)

*177 sur 206 utilisateurs ayant utilisé leurs crédits gratuits n'achètent jamais de pack.*

| # | Raison d'abandon | Donnée de validation | Expérience testable |
|---|---|---|---|
| 1 | **Choc du mur payant** — L'erreur 402 au 3ᵉ déblocage crée une expérience brutale. Les utilisateurs espéraient plus de valeur gratuite et quittent quand on leur demande de payer. | Mesurer `nombre de réponses 402` vs `taux d'ouverture du PaymentModal` vs `taux d'initiation de paiement`. Un écart important entre les 402 servies et les ouvertures de modal = abandon frustré. | **Test A/B mur progressif** : Contrôle = erreur 402 actuelle. Variante = afficher "Il vous reste 1 déblocage gratuit" avant le mur, puis proposer une première offre à prix réduit (2 000 FCFA pour 25 crédits). |
| 2 | **Échec de l'ancrage prix** — La première rencontre de l'utilisateur avec les tarifs se fait post-frustration (après épuisement des crédits). Aucune préparation mentale au coût. Le pack Starter à 1 000 FCFA semble cher pour "juste un numéro de téléphone". | Suivre le `taux de clic sur CreditsWidget` pré-déblocage vs post-402. Comparer le `temps sur PaymentModal` avec le `taux de complétion` par pack. | **Test A/B cadrage prix** : Contrôle = prix actuels. Variante = afficher le prix par déblocage ("à partir de 58 FCFA/contact") au lieu du prix du pack, et ajouter un micro-pack (500 FCFA pour 5 crédits). |
| 3 | **Friction du Mobile Money** — La redirection vers la page hébergée Flutterwave casse le flux in-app. Les utilisateurs mobiles perdent le contexte et ne reviennent pas après le paiement. | Mesurer `taux d'initiation de paiement` vs `taux de complétion` par type d'appareil (mobile vs ordinateur) et par méthode (Mobile Money vs Carte). | **Test A/B paiement inline** : Contrôle = redirection vers la page Flutterwave. Variante = flux Mobile Money inline via le SDK JS Flutterwave, gardant l'utilisateur sur le site avec un indicateur de progression. |

### 3.2 ABANDON #2 : Visite → Inscription (92% d'attrition)

*9 200 sur 10 000 visiteurs partent sans s'inscrire.*

| # | Raison d'abandon | Donnée de validation | Expérience testable |
|---|---|---|---|
| 1 | **Valeur pas immédiatement claire** — Le titre "Trouvez votre logement idéal en Afrique" est générique. Les visiteurs ne comprennent pas le modèle crédit-déblocage ni pourquoi KeyHome est mieux que les groupes WhatsApp / Facebook Marketplace. | Mesurer la `profondeur de défilement` sur la page d'accueil et le `temps sur la section hero`. Suivre le `taux de rebond` des visiteurs ayant vu < 25% de la page. | **Test A/B texte hero** : Contrôle = titre actuel. Variante = "Contactez directement les propriétaires — sans intermédiaire, sans commission" avec une animation de 3 secondes montrant le flux : recherche → déblocage → appel. |
| 2 | **Inscription en 3 étapes trop lourde** — Les utilisateurs doivent choisir un rôle, remplir 6 champs et confirmer le mot de passe avant de voir la moindre valeur. Aucun chemin "parcourir d'abord" depuis les CTA tarifs. | Mesurer le `taux d'abandon par étape du formulaire` (Étapes 1/2/3). Suivre le % de clics CTA tarifs qui ne démarrent pas l'inscription. | **Test A/B inscription différée** : Contrôle = CTA tarifs → `/register`. Variante = CTA tarifs → `/search` (laisser les utilisateurs parcourir d'abord, demander l'inscription au premier déblocage avec un formulaire rapide email + mot de passe). |
| 3 | **Preuve sociale absente au point de décision** — Les témoignages existent mais sont sous la ligne de flottaison. Les utilisateurs qui hésitent à s'inscrire ne voient pas les chiffres d'usage réels ni les signaux de confiance. | Suivre le `taux de visibilité de section` via Intersection Observer — quel % des visiteurs voit réellement les témoignages avant de naviguer ailleurs ? | **Test A/B placement preuve sociale** : Contrôle = témoignages en bas. Variante = ajouter une barre de confiance flottante au-dessus du hero ("12 000+ logements · 4,6★ · 120+ avis") et déplacer 2 cartes de témoignage à côté du CTA d'inscription. |

### 3.3 ABANDON #3 : Inscription → Email vérifié (45% d'attrition)

*360 sur 800 inscrits ne vérifient jamais leur email.*

| # | Raison d'abandon | Donnée de validation | Expérience testable |
|---|---|---|---|
| 1 | **Échecs ou délais de livraison OTP** — Les OTP par email en marchés émergents peuvent atterrir en spam ou prendre plusieurs minutes. Les utilisateurs abandonnent pendant l'attente. | Mesurer le `timestamp d'envoi OTP` vs `timestamp de vérification` (latence médiane). Suivre le `taux d'appels renvoi de vérification` — un taux élevé = problème de livraison. | **Test A/B OTP SMS vs OTP Email** : Contrôle = OTP email uniquement. Variante = envoyer l'OTP par SMS au numéro collecté à l'inscription (via Africa's Talking ou Twilio). |
| 2 | **La redirection vers `/verify-email` après l'overlay de 3,8s perd le contexte** — L'utilisateur s'inscrit, voit un overlay de célébration de 3,8 secondes, puis atterrit sur une page de vérification. S'il ferme l'onglet pendant l'overlay, il ne voit jamais la page de vérification. | Mesurer le `taux de complétion de l'overlay` (% restant 3,8s) vs `taux de chargement de la page verify-email`. | **Test A/B timing de vérification** : Contrôle = overlay → redirection. Variante = supprimer l'overlay, afficher la saisie de vérification directement dans un écran de succès sur la même page, auto-focus sur le champ OTP. |
| 3 | **Aucun mécanisme de rappel** — Les utilisateurs qui ne vérifient pas immédiatement n'ont aucune relance. Pas de notification push, pas de SMS, pas d'email de suivi. | Suivre la distribution du `délai avant vérification`. Quel % vérifie dans 1 min, 1 heure, 24 heures, jamais ? | **Séquence de rappels de vérification** : Envoyer un email de rappel à +15 min, +2 heures, +24 heures avec lien profond. Suivre le `taux de vérification` par point de contact. |

---

## 4. Recommandations d'optimisation priorisées

### R1. Implémenter l'inscription différée ("Parcourir d'abord")

| Aspect | Détail |
|--------|--------|
| **Changement proposé** | Permettre aux visiteurs de parcourir les annonces, utiliser la recherche, voir les détails et interagir avec la carte sans s'inscrire. Ne bloquer que l'action "débloquer le contact" derrière un formulaire d'inscription léger à 2 champs (email + mot de passe) ou connexion sociale. Supprimer le mur d'inscription des CTA tarifs et de tous les boutons "Voir les annonces". |
| **Justification** | **Modèle comportemental de Fogg** : Conversion = Motivation × Capacité × Déclencheur. Actuellement, la capacité est faible (formulaire 3 étapes) et la motivation est faible (l'utilisateur n'a pas encore vu la valeur). En laissant les utilisateurs *expérimenter* le produit d'abord, la motivation augmente naturellement quand ils trouvent un bien qu'ils veulent. Le déclencheur (porte de déblocage) se déclenche au pic de motivation. **Effet de progrès acquis** : Les utilisateurs qui ont déjà investi du temps à parcourir sont plus susceptibles de compléter l'inscription. |
| **Étapes d'implémentation** | 1. Changer tous les CTA d'accueil de `/register` à `/search`. 2. Appliquer le middleware `OptionalAuth` aux endpoints listing/détail/recherche (existe déjà). 3. Créer un composant `QuickSignUpModal` (email + mot de passe + boutons sociaux) déclenché au premier déblocage. 4. Après l'inscription rapide, vérifier automatiquement via OAuth social ou rediriger vers l'OTP inline. |
| **Stratégie de mesure** | KPIs : `taux de conversion visiteur-vers-inscription`, `délai inscription-vers-premier-déblocage`, `taux global visite-vers-payant`. Méthode : test A/B avec répartition 50/50 du trafic pendant 4 semaines. |
| **Amélioration attendue** | Visite → Inscription : 8% → **14-18%** (×2). Les utilisateurs qui parcourent avant de s'inscrire ont une intention 3-5× supérieure. Conversion payante globale : +40-60% d'amélioration. |

### R2. Ajouter un mur payant progressif avec micro-pack

| Aspect | Détail |
|--------|--------|
| **Changement proposé** | Remplacer le mur payant brutal 402 à l'épuisement des crédits par un adoucissement en 3 niveaux : (1) badge d'avertissement "2 crédits restants" sur le CreditsWidget, (2) toast "Dernier déblocage gratuit !" avant le dernier déblocage gratuit, (3) à l'épuisement, afficher un modal "offre premier achat" avec un **micro-pack : 500 FCFA pour 5 crédits** (100 FCFA/crédit, identique au Starter) comme point d'entrée à faible engagement, à côté des packs existants. |
| **Justification** | **Aversion à la perte** (Kahneman) : Cadrer les crédits restants comme "s'épuisant" crée de l'urgence sans frustration. **Technique du pied dans la porte** : Un premier achat à 500 FCFA (~0,80 USD) abaisse considérablement la barrière psychologique. Une fois qu'un utilisateur a payé *quoi que ce soit*, il est 5-8× plus susceptible de repayer (**coût irrécupérable** + méthode de paiement établie). |
| **Étapes d'implémentation** | 1. Ajouter `LOW_CREDIT_THRESHOLD=2` en config. 2. Afficher un badge d'avertissement sur le CreditsWidget quand le solde ≤ seuil. 3. Créer un `FirstPurchaseOfferModal` avec urgence ("Offre de bienvenue — expire dans 24h"). 4. Ajouter le micro-pack au `PointPackageSeeder`. 5. Tracker le funnel : `warning_shown → offer_shown → payment_initiated → payment_completed`. |
| **Stratégie de mesure** | KPIs : `taux de conversion gratuit-vers-payant`, `revenu moyen par utilisateur (ARPU)`, `taux d'achat micro-pack`, `taux de rachat dans 30 jours`. |
| **Amélioration attendue** | Déblocage → Payant : 14% → **22-28%**. Le micro-pack agit comme passerelle vers les packs plus grands. 60-80% des acheteurs de micro-packs attendus pour acheter un pack plus grand dans les 30 jours. |

### R3. Vérification OTP par SMS

| Aspect | Détail |
|--------|--------|
| **Changement proposé** | Envoyer l'OTP par SMS au numéro de téléphone collecté à l'inscription (principal), avec l'email comme solution de repli. Auto-lire le code SMS sur navigateurs mobiles via l'API WebOTP (`autocomplete="one-time-code"`). |
| **Justification** | En Afrique subsaharienne, les taux de livraison SMS (95-98%) dépassent largement les taux d'ouverture email (~20-30%). Les numéros de téléphone sont déjà collectés et validés à l'inscription. **Friction réduite** : le SMS OTP arrive en 5-15 secondes vs l'email qui peut prendre des minutes ou atterrir en spam. L'auto-remplissage WebOTP élimine la saisie sur mobile. |
| **Étapes d'implémentation** | 1. Intégrer l'API SMS Africa's Talking ou Twilio. 2. Ajouter la config `VERIFICATION_CHANNEL` (sms/email/both). 3. Modifier `sendVerificationOtp()` pour utiliser le SMS comme canal principal. 4. Ajouter `autocomplete="one-time-code"` au champ de saisie OTP. 5. Mettre en place le monitoring des coûts SMS (≈5-15 FCFA par SMS). |
| **Stratégie de mesure** | KPIs : `taux de complétion de vérification`, `délai de vérification (médian)`, `taux de livraison SMS`, `coût par utilisateur vérifié`. |
| **Amélioration attendue** | Inscription → Email vérifié : 55% → **78-85%**. Délai de vérification : minutes → moins de 30 secondes. Effet net sur le funnel global : +25-35% d'utilisateurs atteignant l'onboarding. |

### R4. Preuve sociale et signaux de confiance au-dessus de la ligne de flottaison

| Aspect | Détail |
|--------|--------|
| **Changement proposé** | Ajouter une barre de confiance persistante directement sous le hero : "🏠 12 000+ annonces · ⭐ 4,6/5 (120+ avis) · 📱 Paiement Mobile Money sécurisé". Déplacer 2 cartes de témoignage compactes à côté ou juste en dessous du champ de recherche. Ajouter des logos "Vu sur" si applicable. |
| **Justification** | **Principe de preuve sociale** (Cialdini) : Les utilisateurs suivent les actions des autres, surtout en situation d'incertitude. Dans les marchés émergents, la confiance envers les plateformes en ligne est la barrière n°1. Placer la preuve *avant* la demande de conversion réduit le risque perçu. **Évaluation heuristique** : Les utilisateurs se forment une opinion en 50ms — les signaux de confiance doivent être immédiatement visibles, pas enterrés sous 4 hauteurs d'écran. |
| **Étapes d'implémentation** | 1. Créer un composant `<TrustBar />` avec des statistiques dynamiques depuis l'API (l'endpoint `/landing-stats` existe déjà). 2. Positionner sous le hero avec un fond subtilement différencié. 3. Ajouter 2 composants `<TestimonialCard />` compacts dans la grille de la section hero. 4. Chargement différé des témoignages restants sous la ligne de flottaison. |
| **Stratégie de mesure** | KPIs : `taux de rebond page d'accueil`, `taux de défilement-vers-inscription`, `temps sur la page`, `taux de clic CTA`. |
| **Amélioration attendue** | Taux de rebond page d'accueil : -15-20%. Visite → Inscription : +20-30% d'amélioration relative. |

### R5. Inscription simplifiée en 1 étape avec profil différé

| Aspect | Détail |
|--------|--------|
| **Changement proposé** | Condenser l'inscription en 3 étapes en un seul écran : email + mot de passe + type de compte (par défaut : Client). Différer prénom, nom, téléphone, ville à un écran de complétion de profil après vérification de l'email. Garder la connexion sociale proéminente. Afficher la force du mot de passe en ligne (pas dans une étape séparée). |
| **Justification** | **Loi de Hick** : Le temps de décision augmente logarithmiquement avec le nombre de choix. Chaque étape supplémentaire compose l'abandon (typiquement 10-15% de perte par étape). **Divulgation progressive** : Collecter le minimum de données d'abord, enrichir ensuite quand l'utilisateur est investi. Le formulaire à 6 champs avec sélection de rôle, autocomplétion de ville et formatage de téléphone crée une charge cognitive significative pour un visiteur nouveau. |
| **Étapes d'implémentation** | 1. Créer un `QuickRegisterForm` avec 3 champs : email, mot de passe, bascule type de compte. 2. Déplacer nom/téléphone/ville vers une page `/complete-profile` affichée post-vérification. 3. Rendre téléphone/ville optionnels jusqu'au premier déblocage (puis requis). 4. Auto-détecter la ville depuis la géolocalisation IP par défaut. 5. Test A/B contre le formulaire 3 étapes actuel. |
| **Stratégie de mesure** | KPIs : `taux de complétion début-à-fin d'inscription`, `abandon par champ de formulaire`, `temps d'inscription`, `taux de complétion de profil (différé)`. |
| **Amélioration attendue** | Taux de complétion d'inscription : +30-50% relatif. Certains utilisateurs peuvent ne jamais compléter leur profil, donc suivre l'engagement en aval pour s'assurer que la qualité n'est pas sacrifiée. |

---

## 5. Portfolio de tests A/B

> *Tailles d'échantillon calculées avec α=0,05, puissance=0,80, test bilatéral. Taux de base issus des estimations du funnel. EMD = 10% d'amélioration relative.*

### Test 1 : Inscription différée vs flux actuel avec porte

| Aspect | Détail |
|--------|--------|
| **Variable testée** | Placement de la porte d'inscription — actuel (avant navigation) vs différé (au premier déblocage) |
| **Hypothèse** | Permettre aux utilisateurs de parcourir les annonces avant d'exiger l'inscription augmentera le taux de conversion Visite → Inscription de ≥10% relatif, car les utilisateurs ayant vu des annonces intéressantes sont plus motivés pour s'inscrire. |
| **Métrique de succès** | `taux_visite_vers_inscription` (principal), `taux_inscription_vers_payant` (secondaire) |
| **Taille d'échantillon minimum** | Base : 8% de conversion. EMD : 0,8pp (10% relatif). Requis : **≈11 500 visiteurs par variation** (23 000 total) avec test z pour proportions. |

### Test 2 : Micro-pack d'entrée (500 FCFA)

| Aspect | Détail |
|--------|--------|
| **Variable testée** | Options de packs au mur payant — 3 packs actuels vs 4 packs avec micro-pack 500 FCFA en première option |
| **Hypothèse** | Ajouter un micro-pack à 500 FCFA augmentera le taux de conversion Premier déblocage → Payant de ≥10% relatif, car les utilisateurs à faible intention d'achat convertiront à un prix inférieur. |
| **Métrique de succès** | `taux_déblocage_vers_payant` (principal), `ARPU_30j` (garde-fou — s'assurer que le micro-pack ne cannibalise pas les packs plus grands) |
| **Taille d'échantillon minimum** | Base : 14% de conversion. EMD : 1,4pp. Requis : **≈6 300 utilisateurs par variation** atteignant le mur payant (12 600 total). |

### Test 3 : OTP SMS vs OTP Email

| Aspect | Détail |
|--------|--------|
| **Variable testée** | Canal de vérification — OTP email (contrôle) vs OTP SMS (variante) |
| **Hypothèse** | L'OTP par SMS augmentera le taux de conversion Inscription → Vérifié de ≥10% relatif (55% → 60,5%), car la livraison SMS est plus rapide et fiable dans les marchés cibles. |
| **Métrique de succès** | `taux_inscription_vers_vérifié` (principal), `délai_vérification_médian` (secondaire) |
| **Taille d'échantillon minimum** | Base : 55% de conversion. EMD : 5,5pp. Requis : **≈1 300 inscriptions par variation** (2 600 total). |

### Test 4 : Refonte texte hero + barre de confiance

| Aspect | Détail |
|--------|--------|
| **Variable testée** | Section hero de la page d'accueil — titre générique actuel vs titre axé bénéfices + barre de confiance inline + 2 témoignages |
| **Hypothèse** | Un titre orienté valeur avec preuve sociale visible réduira le taux de rebond de la page d'accueil de ≥10% relatif et augmentera la conversion Visite → Inscription de ≥10% relatif. |
| **Métrique de succès** | `taux_rebond_accueil` (principal), `taux_visite_vers_inscription` (secondaire) |
| **Taille d'échantillon minimum** | Base : ~60% de taux de rebond. EMD : 6pp. Requis : **≈1 800 visiteurs par variation** (3 600 total). |

### Test 5 : Formulaire d'inscription 1 étape vs 3 étapes

| Aspect | Détail |
|--------|--------|
| **Variable testée** | Structure du formulaire d'inscription — stepper 3 étapes (contrôle) vs formulaire page unique avec 3 champs (variante) |
| **Hypothèse** | Réduire l'inscription à 1 étape avec 3 champs augmentera le taux de complétion d'inscription de ≥10% relatif, car moins d'étapes signifie moins d'abandon. |
| **Métrique de succès** | `taux_complétion_formulaire_inscription` (principal), `taux_complétion_profil` dans 7 jours (garde-fou) |
| **Taille d'échantillon minimum** | Base : ~65% de complétion formulaire. EMD : 6,5pp. Requis : **≈1 500 démarrages d'inscription par variation** (3 000 total). |

---

## 6. Optimisation stratégique de la page tarifs

### 6.1 Évaluation de l'état actuel

| Critère | Note | Analyse |
|---------|------|---------|
| **Clarté de la proposition de valeur** | ⚠️ Moyen | Le titre "Payez uniquement pour le contact" est bon — il pose les attentes. Mais les cartes de packs listent les fonctionnalités de manière générique ("10 déblocages", "support prioritaire") sans les traduire en résultats utilisateur. Les utilisateurs ne savent pas ce que "50 déblocages" signifie concrètement (ex : "Assez pour trouver votre appartement en 2 semaines"). |
| **Mise en avant du plan populaire** | ✅ Bon | Le pack Standard/Pro a un badge "Populaire 🔥" avec fond dégradé et texte blanc — visuellement distinct. Position en tier médian qui suit la bonne pratique d'ancrage. |
| **Bénéfices vs spécifications** | ❌ Faible | Les fonctionnalités sont listées comme spécifications : "50 déblocages", "support prioritaire", "accès direct". C'est *ce que* les utilisateurs obtiennent, pas *pourquoi* c'est important. Aucun cadrage de résultats : temps gagné, appartements trouvés, argent économisé vs intermédiaires. |
| **Section FAQ** | ❌ Absente | Pas de FAQ répondant à : "Et si le propriétaire ne répond pas ?", "Les crédits sont-ils remboursables ?", "Combien de temps durent les crédits ?", "Mon paiement est-il sécurisé ?", "Puis-je être remboursé si l'annonce est fausse ?" — objections critiques dans l'immobilier africain. |
| **Preuve sociale** | ⚠️ Placement faible | Les témoignages existent sur la page d'accueil mais sont loin de la section tarifs. Pas d'éléments de preuve près des boutons CTA : pas de "X utilisateurs ont acheté ce pack", pas de notes, pas de badges de confiance à côté des icônes de paiement. |

### 6.2 Recommandations actionnables pour la page tarifs

**P1. Réécrire les fonctionnalités en résultats**

| Actuel (Spécification) | Recommandé (Bénéfice) |
|---|---|
| "10 déblocages" | "Contactez jusqu'à 10 propriétaires — trouvez votre premier logement" |
| "50 déblocages" | "50 contacts directs — idéal pour comparer et négocier le meilleur loyer" |
| "120 déblocages" | "120 contacts — déménagez en toute sérénité, partagez avec vos proches" |
| "Support prioritaire" | "Réponse en moins de 2h si un problème survient" |
| "Meilleur rapport qualité-prix" | "Le plus choisi — 80 FCFA par contact au lieu de 100" |

**P2. Ajouter l'ancrage prix par déblocage**

Afficher le prix par déblocage de manière proéminente sur chaque carte :
- Starter : ~~100 FCFA/contact~~
- Standard : **80 FCFA/contact** (-20%)
- Premium : **58 FCFA/contact** (-42%)

Cela crée une échelle de valeur claire et rend la valeur du pack Populaire évidente.

**P3. Ajouter une section FAQ sous les cartes de tarifs**

Minimum 6 questions :
1. "Mes crédits expirent-ils ?" → Non, les crédits n'expirent jamais.
2. "Le propriétaire ne répond pas, que faire ?" → Signaler l'annonce ; remboursement si annonce frauduleuse.
3. "Le paiement est-il sécurisé ?" → Oui, via Flutterwave (logo), Mobile Money supporté.
4. "Puis-je être remboursé ?" → Détails de la politique.
5. "Comment fonctionne le déblocage ?" → 1 clic = 2 crédits = numéro + WhatsApp.
6. "Y a-t-il des frais cachés ?" → Zéro commission, zéro frais d'intermédiaire.

**P4. Ajouter une preuve sociale contextuelle près des CTA**

- Sous chaque bouton CTA : "Choisi par 2 400+ utilisateurs ce mois" (dynamique depuis l'API).
- Ajouter les logos des méthodes de paiement (Orange Money, MTN, Visa, Mastercard) avec une icône 🔒 cadenas.
- Ajouter un extrait compact de témoignage à côté du plan Populaire.

**P5. Ajouter une "Garantie satisfaction"**

- "Annonce fausse ? Crédits remboursés." — La confiance est la barrière n°1 dans les plateformes immobilières africaines.
- Afficher comme un badge icône bouclier entre les cartes de tarifs et la FAQ.
- Cet unique élément peut augmenter la conversion payante de 10-25% dans les marchés à faible confiance.

**P6. Implémenter l'ancrage prix avec un rappel d'économies sur les plans annuels (pour les agents)**

La section abonnement agent devrait :
- Basculer par défaut sur l'onglet annuel (ancrer sur le prix élevé, montrer les économies).
- Afficher "Économisez 30 000 FCFA" sur Basic annuel, "70 000 FCFA" sur Premium annuel.
- Ajouter un badge "Recommandé" sur Premium (tier médian, marge la plus élevée).

---

## Matrice Impact × Effort

```
                        IMPACT ÉLEVÉ
                            │
         ┌──────────────────┼──────────────────┐
         │                  │                  │
         │  R1 Inscription  │  R2 Micro-Pack   │
         │  différée        │  + Mur progressif│
         │                  │                  │
         │  R3 OTP SMS      │                  │
EFFORT ──┼──────────────────┼──────────────────┼── EFFORT
FAIBLE   │                  │                  │   ÉLEVÉ
         │  R4 Barre de     │  R5 Inscription  │
         │  confiance       │  1 étape         │
         │                  │                  │
         │  P2 Prix par     │  P5 Garantie     │
         │  déblocage       │  + FAQ           │
         │                  │                  │
         └──────────────────┼──────────────────┘
                            │
                        IMPACT FAIBLE
```

**Ordre d'exécution recommandé :**
1. **Gains rapides (1-2 semaines) :** P2 prix par déblocage, R4 barre de confiance, P3 section FAQ
2. **Effort moyen (2-4 semaines) :** R2 micro-pack + mur progressif, R3 OTP SMS
3. **Effort élevé (4-8 semaines) :** R1 inscription différée, R5 refonte formulaire 1 étape

**Impact cumulé projeté** (si tout implémenté) :
- Visite → Payant : **0,29% → 0,8-1,1%** (amélioration ×3-4)
- Inscription → Payant : **3,6% → 8-12%**
- Période de retour : R2 et R3 seuls devraient montrer un gain mesurable en 2-3 semaines.

---
---
---

# ENGLISH VERSION

---

## 1. Application Description

KeyHome is a pan-African real estate marketplace connecting tenants with landlords/agents. Revenue comes from two streams:

1. **Credit packs** — Customers buy credits to unlock landlord contact information (2 credits/unlock).
2. **Agency subscriptions** — Agents pay monthly/yearly to publish and boost property listings.

**Pricing Structure:**

| Plan | Monthly | Yearly | Max Ads | Boost |
|------|---------|--------|---------|-------|
| **Basic** | 15,000 FCFA | 150,000 FCFA | 20/month | +10 pts / 7d |
| **Premium** | 35,000 FCFA | 350,000 FCFA | 50/month | +25 pts / 14d |
| **Enterprise** | 75,000 FCFA | 750,000 FCFA | Unlimited | +50 pts / 30d |

| Credit Pack | Price | Credits | Cost/Credit | Unlocks |
|-------------|-------|---------|-------------|---------|
| **Starter** | 1,000 FCFA | 10 | 100 FCFA | 5 contacts |
| **Pro** ⭐ | 4,000 FCFA | 50 | 80 FCFA | 25 contacts |
| **Premium** | 7,000 FCFA | 120 | 58 FCFA | 60 contacts |

**Free Tier:** 5 welcome credits on sign-up (= 2–3 unlocks max).

---

## 2. Funnel Mapping & Performance Analysis

### 2.1 Primary Funnel: Customer → Paid Conversion

> *Estimated conversion rates based on industry benchmarks for emerging-market freemium marketplaces. Validate with your actual analytics data (SiteVisit, AdInteraction).*

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     KEYHOME CUSTOMER FUNNEL                              │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌───────────────────┐                                                   │
│  │  VISIT LANDING     │  100%          (baseline: 10,000 visitors)       │
│  │  PAGE              │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── 8.0% conversion rate ────                               │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  SIGN UP           │  8.0%          (800 users)                       │
│  │  3-step form       │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── 55% conversion rate ────                                │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  EMAIL VERIFIED    │  4.4%          (440 users)                       │
│  │  OTP / Link        │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── 72% conversion rate ────                                │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  ONBOARDED         │  3.17%         (317 users)                       │
│  │  Modal + Tour      │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── 65% conversion rate ────                                │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  FIRST UNLOCK      │  2.06%         (206 users)                       │
│  │  Free credits      │                                                  │
│  └───────┬───────────┘                                                   │
│          │  ──── 14% conversion rate ────                                │
│          ▼                                                               │
│  ┌───────────────────┐                                                   │
│  │  PAID CONVERSION   │  0.29%         (29 users)                        │
│  │  Credit purchase   │                                                  │
│  └───────────────────┘                                                   │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Secondary Funnel: Agent → Subscription

```
Visit ──(2.0%)──▶ Agent Sign Up ──(58%)──▶ Email Verified ──(15%)──▶ Subscribed ──(72%)──▶ First Ad Published
                  200 agents              116 agents               17 agents              12 agents
```

### 2.3 Key Metrics

| Metric | Value |
|--------|-------|
| **Highest drop-off stage** | **First Unlock → Paid Conversion (86% drop-off)** |
| **Second highest drop-off** | Visit → Sign Up (92% drop-off) |
| **Third highest drop-off** | Sign Up → Email Verified (45% drop-off) |
| **Overall Visit → Paid Conversion** | **0.29%** |
| **Overall Sign Up → Paid** | **3.6%** |

---

## 3. Granular Drop-off Analysis

### 3.1 DROP-OFF #1: First Unlock → Paid Conversion (86% attrition)

*177 out of 206 users who used free credits never purchase a credit pack.*

| # | Reason for Abandonment | Validation Data Point | Testable Experiment |
|---|---|---|---|
| 1 | **Hard paywall shock** — The 402 error at 3rd unlock creates a jarring "wall" experience. Users expected more free value and bounce when asked to pay. | Measure `402 response count` vs `PaymentModal open rate` vs `payment initiation rate`. A large gap between 402s served and modal opens = rage-quit. | **A/B test progressive paywall**: Control = current 402 error. Variant = show "You have 1 free unlock left" warning before hitting the wall, then offer a discounted first-purchase (2,000 FCFA for 25 credits). |
| 2 | **Price anchoring failure** — Users' first encounter with pricing is post-frustration (after credits run out). No mental preparation for the cost. The Starter pack at 1,000 FCFA feels expensive for "just a phone number." | Track `CreditsWidget click rate` pre-unlock vs post-402. Compare `time on PaymentModal` with `completion rate` per pack tier. | **A/B test price framing**: Control = current pricing. Variant = show per-unlock price ("starting at 58 FCFA/contact") instead of pack price, and add a micro-pack (500 FCFA for 5 credits) as a low-commitment entry. |
| 3 | **Mobile Money friction** — Flutterwave's hosted checkout redirect breaks the in-app flow. Users on mobile lose context and don't return after payment. | Measure `payment initiation rate` vs `payment completion rate` by device type (mobile vs desktop) and payment method (Mobile Money vs Card). | **A/B test inline payment**: Control = redirect to Flutterwave hosted page. Variant = inline Mobile Money flow using Flutterwave's inline JS SDK, keeping user on-site with a loading spinner. |

### 3.2 DROP-OFF #2: Visit → Sign Up (92% attrition)

*9,200 out of 10,000 visitors leave without registering.*

| # | Reason for Abandonment | Validation Data Point | Testable Experiment |
|---|---|---|---|
| 1 | **Value not immediately clear** — Hero headline "Trouvez votre logement idéal en Afrique" is generic. Visitors don't understand the credit-unlock model or why KeyHome is better than WhatsApp groups / Facebook Marketplace. | Measure `scroll depth` on landing page and `time on hero section`. Track `bounce rate` for visitors who see < 25% of the page. | **A/B test hero copy**: Control = current headline. Variant = "Contact landlords directly — no middleman, no commission" with a 3-second animated value prop showing: search → unlock → call flow. |
| 2 | **3-step registration is too heavy for uncommitted visitors** — Users must choose role, fill 6 fields, confirm password before seeing any value. No "browse first" path from pricing CTAs. | Measure `registration form step abandonment rate` per step (Steps 1/2/3). Track % of pricing CTA clicks that don't start registration. | **A/B test deferred registration**: Control = pricing CTAs → `/register`. Variant = pricing CTAs → `/search` (let users browse first, prompt sign-up only at first unlock attempt with a 2-field email+password quick form). |
| 3 | **Missing social proof at decision point** — Testimonials section exists but is below the fold. Users deciding whether to register don't see real usage numbers or trust signals. | Track `section visibility rate` via Intersection Observer — what % of visitors actually see the testimonials section before navigating away? | **A/B test social proof placement**: Control = testimonials at bottom. Variant = add a floating trust bar above the hero ("12,000+ listings · 4.6★ · 120+ reviews") and move 2 testimonial cards beside the registration CTA. |

### 3.3 DROP-OFF #3: Sign Up → Email Verified (45% attrition)

*360 out of 800 registrants never verify their email.*

| # | Reason for Abandonment | Validation Data Point | Testable Experiment |
|---|---|---|---|
| 1 | **OTP delivery failures or delays** — Email OTPs in emerging markets may hit spam filters or take minutes to arrive. Users abandon during the wait. | Measure `OTP send timestamp` vs `OTP verification timestamp` (median latency). Track `resend-verification` call rate — high resend = delivery issues. | **A/B test SMS OTP vs Email OTP**: Control = email OTP only. Variant = send OTP via SMS to the phone number collected at registration (using Africa's Talking or Twilio). |
| 2 | **Redirect to `/verify-email` after 3.8s overlay loses context** — User registers, sees a 3.8-second celebration overlay, then lands on a verification page. If they close the tab during the overlay, they never see the verify page. | Measure `WelcomeOverlay completion rate` (% who stay 3.8s) vs `verify-email page load rate`. | **A/B test verification timing**: Control = overlay → redirect. Variant = skip overlay, show verification input directly on a success screen within the same page, auto-focus the OTP field. |
| 3 | **No reminder mechanism** — Users who don't verify immediately have no re-engagement. No push notification, no SMS, no follow-up email. | Track `time-to-verify` distribution. What % verify within 1 min, 1 hour, 24 hours, never? | **Implement verification reminder sequence**: Send reminder email at +15 min, +2 hours, +24 hours with deeplink. Track `verification rate` per reminder touchpoint. |

---

## 4. Prioritized Optimization Recommendations

### R1. Implement Deferred Registration ("Browse First")

| Aspect | Detail |
|--------|--------|
| **Proposed Change** | Allow visitors to browse ads, use search, view details, and interact with the map without registering. Gate only the "unlock contact" action behind a lightweight 2-field signup (email + password) or social login. Remove the registration wall from pricing CTAs and all "Voir les annonces" buttons. |
| **Justification** | **Fogg Behavior Model**: Conversion = Motivation × Ability × Trigger. Currently, ability is low (3-step form) and motivation is low (user hasn't seen value yet). By letting users *experience* the product first, motivation rises naturally when they find a property they want. The trigger (unlock gate) fires at peak motivation. **Endowed progress effect**: Users who've already invested time browsing are more likely to complete registration. |
| **Implementation Steps** | 1. Change all landing CTAs from `/register` to `/search`. 2. Apply `OptionalAuth` middleware to ad listing/detail/search endpoints (already exists). 3. Create a `QuickSignUpModal` component (email + password + social buttons) triggered at first unlock attempt. 4. After quick signup, auto-verify via social OAuth or redirect to OTP inline. |
| **Measurement Strategy** | KPIs: `visitor-to-signup conversion rate`, `signup-to-first-unlock time`, `overall visit-to-paid rate`. Method: A/B test with 50/50 traffic split for 4 weeks. |
| **Expected Improvement** | Visit → Sign Up: 8% → **14-18%** (2x). Users who browse before signing up have 3-5x higher intent. Overall paid conversion: +40-60% lift. |

### R2. Add Progressive Paywall with Micro-Pack

| Aspect | Detail |
|--------|--------|
| **Proposed Change** | Replace the hard 402 paywall at credit exhaustion with a 3-tier softening: (1) "2 credits left" warning badge on CreditsWidget, (2) "Last free unlock!" toast before the final free unlock, (3) at exhaustion, show a "first-purchase offer" modal with a **micro-pack: 500 FCFA for 5 credits** (100 FCFA/credit, same as Starter) as a low-commitment entry point alongside existing packs. |
| **Justification** | **Loss aversion** (Kahneman): Framing remaining credits as "running out" creates urgency without frustration. **Foot-in-the-door technique**: A 500 FCFA first purchase (~$0.80 USD) dramatically lowers the psychological barrier. Once a user has paid *anything*, they're 5-8x more likely to pay again (**sunk cost** + established payment method). |
| **Implementation Steps** | 1. Add `LOW_CREDIT_THRESHOLD=2` config. 2. Show warning badge on CreditsWidget when balance ≤ threshold. 3. Create `FirstPurchaseOfferModal` with countdown urgency ("Welcome offer — expires in 24h"). 4. Add micro-pack to `PointPackageSeeder`. 5. Track funnel: `warning_shown → offer_shown → payment_initiated → payment_completed`. |
| **Measurement Strategy** | KPIs: `free-to-paid conversion rate`, `average revenue per user (ARPU)`, `micro-pack purchase rate`, `repeat purchase rate within 30 days`. |
| **Expected Improvement** | Unlock → Paid conversion: 14% → **22-28%**. Micro-pack acts as gateway to larger packs. Expected 60-80% of micro-pack buyers to purchase a larger pack within 30 days. |

### R3. SMS-Based OTP Verification

| Aspect | Detail |
|--------|--------|
| **Proposed Change** | Send OTP via SMS to the phone number collected at registration (primary), with email as fallback. Auto-read the SMS code on mobile browsers using the WebOTP API (`autocomplete="one-time-code"`). |
| **Justification** | In Sub-Saharan Africa, SMS delivery rates (95-98%) far exceed email open rates (~20-30%). Phone numbers are already collected and validated at registration. **Reduced friction**: SMS OTP arrives in 5-15 seconds vs email which may take minutes or land in spam. WebOTP auto-fill eliminates typing entirely on mobile. |
| **Implementation Steps** | 1. Integrate Africa's Talking or Twilio SMS API. 2. Add `VERIFICATION_CHANNEL` config (sms/email/both). 3. Modify `sendVerificationOtp()` to use SMS as primary channel. 4. Add `autocomplete="one-time-code"` to OTP input field. 5. Add SMS cost monitoring (≈5-15 FCFA per SMS). |
| **Measurement Strategy** | KPIs: `verification completion rate`, `time-to-verify (median)`, `SMS delivery rate`, `cost-per-verified-user`. |
| **Expected Improvement** | Sign Up → Email Verified: 55% → **78-85%**. Time-to-verify: minutes → under 30 seconds. Net effect on overall funnel: +25-35% more users reaching onboarding. |

### R4. Social Proof & Trust Signals Above the Fold

| Aspect | Detail |
|--------|--------|
| **Proposed Change** | Add a persistent trust bar directly below the hero: "🏠 12,000+ listings · ⭐ 4.6/5 (120+ reviews) · 📱 Secure Mobile Money payment". Move 2 compact testimonial cards to sit beside or just below the search input. Add "As seen on" logos if applicable. |
| **Justification** | **Social proof principle** (Cialdini): Users follow the actions of others, especially under uncertainty. In emerging markets, trust in online platforms is the #1 barrier. Placing proof *before* the conversion ask reduces perceived risk. **Heuristic evaluation**: Users form opinions within 50ms — trust signals must be immediately visible, not buried below 4 scroll-lengths. |
| **Implementation Steps** | 1. Create `<TrustBar />` component with dynamic stats from API (`/landing-stats` endpoint already exists). 2. Position below hero with subtle background differentiation. 3. Add 2 compact `<TestimonialCard />` components in the hero section grid. 4. Lazy-load remaining testimonials below the fold. |
| **Measurement Strategy** | KPIs: `landing page bounce rate`, `scroll-to-register rate`, `time on page`, `CTA click-through-rate`. |
| **Expected Improvement** | Landing bounce rate: -15-20%. Visit → Sign Up: +20-30% relative improvement. |

### R5. Streamlined 1-Step Registration with Deferred Profile

| Aspect | Detail |
|--------|--------|
| **Proposed Change** | Collapse the 3-step registration into a single screen: email + password + account type (default: Customer). Defer first name, last name, phone, city to a profile completion screen after email verification. Keep social login prominent. Show password strength inline (not in a separate step). |
| **Justification** | **Hick's Law**: Decision time increases logarithmically with the number of choices. Each additional form step compounds abandonment (typical 10-15% drop per step). **Progressive disclosure**: Collect minimum data first, enrich later when user is invested. The 6-field form with role selection, city autocomplete, and phone formatting creates significant cognitive load for a first-time visitor. |
| **Implementation Steps** | 1. Create `QuickRegisterForm` with 3 fields: email, password, account type toggle. 2. Move name/phone/city to `/complete-profile` page shown post-verification. 3. Make phone/city optional until first unlock (then required). 4. Auto-detect city from IP geolocation as default. 5. A/B test against current 3-step form. |
| **Measurement Strategy** | KPIs: `registration start-to-complete rate`, `form abandonment per field`, `time-to-register`, `profile completion rate (deferred)`. |
| **Expected Improvement** | Registration completion rate: +30-50% relative. Some users may never complete profile, so track downstream engagement to ensure quality isn't sacrificed. |

---

## 5. A/B Test Portfolio

> *Sample sizes calculated assuming α=0.05, power=0.80, two-tailed test. Baseline rates from funnel estimates. MDE = 10% relative improvement.*

### Test 1: Deferred Registration vs. Current Gated Flow

| Aspect | Detail |
|--------|--------|
| **Test Variable** | Registration gate placement — current (before browsing) vs. deferred (at first unlock) |
| **Hypothesis** | Allowing users to browse ads before requiring registration will increase Visit → Sign Up conversion by ≥10% relative, because users who've seen valuable listings are more motivated to register. |
| **Success Metric** | `visit_to_signup_rate` (primary), `signup_to_paid_rate` (secondary) |
| **Minimum Sample Size** | Baseline: 8% conversion. MDE: 0.8pp (10% relative). Required: **≈11,500 visitors per variation** (23,000 total). |

### Test 2: Micro-Pack (500 FCFA) Entry Pricing

| Aspect | Detail |
|--------|--------|
| **Test Variable** | Credit pack options at paywall — current 3 packs vs. 4 packs with 500 FCFA micro-pack as first option |
| **Hypothesis** | Adding a micro-pack priced at 500 FCFA will increase First Unlock → Paid conversion by ≥10% relative, because users with low purchase intent will convert at a lower price point. |
| **Success Metric** | `unlock_to_paid_rate` (primary), `30_day_ARPU` (guardrail — ensure micro-pack doesn't cannibalize larger packs) |
| **Minimum Sample Size** | Baseline: 14% conversion. MDE: 1.4pp. Required: **≈6,300 users per variation** who reach the paywall (12,600 total). |

### Test 3: SMS OTP vs. Email OTP Verification

| Aspect | Detail |
|--------|--------|
| **Test Variable** | Verification channel — email OTP (control) vs. SMS OTP (variant) |
| **Hypothesis** | SMS-based OTP will increase Sign Up → Verified conversion by ≥10% relative (55% → 60.5%), because SMS delivery is faster and more reliable in target markets. |
| **Success Metric** | `signup_to_verified_rate` (primary), `time_to_verify_median` (secondary) |
| **Minimum Sample Size** | Baseline: 55% conversion. MDE: 5.5pp. Required: **≈1,300 signups per variation** (2,600 total). |

### Test 4: Hero Copy + Trust Bar Redesign

| Aspect | Detail |
|--------|--------|
| **Test Variable** | Landing hero section — current generic headline vs. benefit-focused headline + inline trust bar + 2 testimonials |
| **Hypothesis** | A value-specific headline with visible social proof will reduce landing page bounce rate by ≥10% relative and increase Visit → Sign Up by ≥10% relative. |
| **Success Metric** | `landing_bounce_rate` (primary), `visit_to_signup_rate` (secondary) |
| **Minimum Sample Size** | Baseline: ~60% bounce rate. MDE: 6pp. Required: **≈1,800 visitors per variation** (3,600 total). |

### Test 5: 1-Step vs. 3-Step Registration Form

| Aspect | Detail |
|--------|--------|
| **Test Variable** | Registration form structure — 3-step stepper (control) vs. single-page form with 3 fields (variant) |
| **Hypothesis** | Reducing registration to 1 step with 3 fields will increase registration completion rate by ≥10% relative, because fewer steps means less abandonment. |
| **Success Metric** | `registration_start_to_complete_rate` (primary), `profile_completion_rate` within 7 days (guardrail) |
| **Minimum Sample Size** | Baseline: ~65% form completion. MDE: 6.5pp. Required: **≈1,500 registration starts per variation** (3,000 total). |

---

## 6. Pricing Page Strategic Optimization

### 6.1 Current State Assessment

| Criterion | Rating | Analysis |
|-----------|--------|----------|
| **Value proposition clarity** | ⚠️ Medium | The headline "Payez uniquement pour le contact" is good — it sets expectations. But individual pack cards list features generically ("10 déblocages", "support prioritaire") without translating them into user outcomes. Users don't know what "50 déblocages" means in practical terms (e.g., "Enough to find your apartment in 2 weeks"). |
| **Popular plan highlighting** | ✅ Good | The Standard/Pro pack has a "Populaire 🔥" badge with gradient background and white text — visually distinct. Middle tier position follows correct anchoring practice. |
| **Benefits vs. specifications** | ❌ Weak | Features are listed as specifications: "50 déblocages", "support prioritaire", "accès direct". These are *what* users get, not *why* it matters. No framing of outcomes: time saved, apartments found, money saved vs. intermediaries. |
| **FAQ section** | ❌ Missing | No FAQ addressing: "What if the landlord doesn't respond?", "Are credits refundable?", "How long do credits last?", "Is my payment secure?", "Can I get a refund if the listing is fake?" These are critical objections in African real estate. |
| **Social proof** | ⚠️ Weak placement | Testimonials exist on the landing page but are far below the pricing section. No proof elements near the CTA buttons: no "X users purchased this pack", no star ratings, no trust badges next to payment icons. |

### 6.2 Actionable Pricing Page Recommendations

**P1. Rewrite pack features as outcomes**

| Current (Specification) | Recommended (Benefit) |
|---|---|
| "10 déblocages" | "Contact up to 10 landlords — find your first home" |
| "50 déblocages" | "50 direct contacts — ideal for comparing and negotiating the best rent" |
| "120 déblocages" | "120 contacts — move with confidence, share with friends & family" |
| "Support prioritaire" | "Response in under 2h if any issue arises" |
| "Meilleur rapport qualité-prix" | "Most popular — 80 FCFA per contact instead of 100" |

**P2. Add per-unlock price anchoring**

Display the per-unlock price prominently on each card:
- Starter: ~~100 FCFA/contact~~
- Standard: **80 FCFA/contact** (-20%)
- Premium: **58 FCFA/contact** (-42%)

This creates a clear value ladder and makes the Popular pack's value self-evident.

**P3. Add an FAQ section below pricing cards**

Minimum 6 questions:
1. "Do my credits expire?" → No, credits never expire.
2. "The landlord doesn't respond, what can I do?" → Report the listing; refund if fraudulent.
3. "Is payment secure?" → Yes, via Flutterwave (logo), Mobile Money supported.
4. "Can I get a refund?" → Policy details.
5. "How does unlocking work?" → 1 click = 2 credits = phone number + WhatsApp.
6. "Are there hidden fees?" → Zero commission, zero intermediary fee.

**P4. Add contextual social proof near CTAs**

- Below each CTA button: "Chosen by 2,400+ users this month" (dynamic from API).
- Add payment method logos (Orange Money, MTN, Visa, Mastercard) with a 🔒 padlock icon.
- Add a single compact testimonial quote beside the Popular plan.

**P5. Add a "Satisfaction Guarantee"**

- "Fake listing? Credits refunded." — Trust is the #1 barrier in African real estate platforms.
- Display as a shield icon badge between the pricing cards and the FAQ.
- This single element can lift paid conversion by 10-25% in low-trust markets.

**P6. Implement price anchoring with a "savings" callout on yearly plans (for agents)**

The agent subscription pricing section should:
- Default to yearly toggle (anchor on high price, show savings).
- Show "Save 30,000 FCFA" on Basic yearly, "70,000 FCFA" on Premium yearly.
- Add a "Recommended" badge to Premium (mid-tier, highest margin).

---

## Impact × Effort Matrix

```
                        HIGH IMPACT
                            │
         ┌──────────────────┼──────────────────┐
         │                  │                  │
         │  R1 Deferred     │  R2 Micro-Pack   │
         │  Registration    │  + Soft Paywall  │
         │                  │                  │
         │  R3 SMS OTP      │                  │
LOW ─────┼──────────────────┼──────────────────┼───── HIGH
EFFORT   │                  │                  │  EFFORT
         │  R4 Trust Bar    │  R5 1-Step       │
         │  + Social Proof  │  Registration    │
         │                  │                  │
         │  P2 Per-unlock   │  P5 Guarantee    │
         │  Price Display   │  + FAQ Section   │
         │                  │                  │
         └──────────────────┼──────────────────┘
                            │
                        LOW IMPACT
```

**Recommended execution order:**
1. **Quick wins (1-2 weeks):** P2 per-unlock pricing, R4 trust bar, P3 FAQ section
2. **Medium effort (2-4 weeks):** R2 micro-pack + progressive paywall, R3 SMS OTP
3. **High effort (4-8 weeks):** R1 deferred registration, R5 1-step form redesign

**Projected cumulative impact** (if all implemented):
- Visit → Paid conversion: **0.29% → 0.8-1.1%** (3-4x improvement)
- Sign Up → Paid: **3.6% → 8-12%**
- Payback period: R2 and R3 alone should show measurable lift within 2-3 weeks.

---
---
---

# SUGGESTIONS D'IMPLÉMENTATION / IMPLEMENTATION SUGGESTIONS

> Les suggestions ci-dessous sont classées par priorité et couvrent les deux faces (backend Laravel + frontend Next.js). Chaque suggestion inclut les fichiers à modifier ou créer.
>
> The suggestions below are ordered by priority and cover both sides (Laravel backend + Next.js frontend). Each suggestion includes the files to modify or create.

---

## S1. Micro-Pack 500 FCFA — Ajout du pack d'entrée / Add entry-level pack

**Priorité / Priority:** 🔴 CRITIQUE — Impact le plus immédiat sur le revenu / Most immediate revenue impact

**Backend (Laravel):**
- `database/seeders/PointPackageSeeder.php` — Ajouter le micro-pack (500 FCFA, 5 crédits, position_order = 0)
- `app/Models/PointPackage.php` — Aucun changement requis, le modèle supporte déjà la structure
- Migration optionnelle : aucune si le seeder est re-exécuté

```php
// Nouveau pack dans PointPackageSeeder
[
    'name' => 'Pack Découverte',
    'slug' => 'pack-decouverte',
    'points_awarded' => 5,
    'price' => 500,
    'currency' => 'XAF',
    'is_active' => true,
    'is_popular' => false,
    'position_order' => 0,
    'features' => [
        '5 déblocages de contacts',
        'Accès direct téléphone & WhatsApp',
        'Historique des déblocages',
    ],
]
```

**Frontend (Next.js):**
- `keyhome-frontend-next/src/components/pricing/PricingSection.tsx` — Le composant charge déjà dynamiquement les packs depuis l'API, pas de changement nécessaire
- Vérifier que l'affichage gère correctement 4 cartes au lieu de 3 (responsive grid)

---

## S2. Mur payant progressif / Progressive paywall

**Priorité / Priority:** 🔴 CRITIQUE

**Backend:**
- `app/Http/Controllers/Api/V1/AdController.php` (méthode `unlockAd`) — Modifier la réponse 402 pour inclure `remaining_credits` dans le payload
- `config/app.php` ou `config/payment.php` — Ajouter `LOW_CREDIT_THRESHOLD=2`

**Frontend:**
- `keyhome-frontend-next/src/providers/ComparatorProvider.tsx` ou un nouveau `CreditWarningProvider` — Écouter le solde de crédits
- `keyhome-frontend-next/src/components/credits/CreditsWidget.tsx` — Ajouter un badge d'avertissement animé quand solde ≤ 2
- Créer `keyhome-frontend-next/src/components/credits/FirstPurchaseOfferModal.tsx` — Modal avec offre premier achat + compte à rebours 24h
- `keyhome-frontend-next/src/components/credits/PaymentModal.tsx` — Ajouter le micro-pack en première option avec tag "Offre de bienvenue"

---

## S3. Barre de confiance / Trust bar above the fold

**Priorité / Priority:** 🟡 GAIN RAPIDE — Faible effort, impact immédiat / Low effort, immediate impact

**Backend:**
- Endpoint `/api/v1/landing-stats` déjà existant — Vérifier qu'il retourne : nombre total d'annonces, nombre d'avis, note moyenne
- Si manquant : ajouter `total_reviews` et `average_rating` au retour

**Frontend:**
- Créer `keyhome-frontend-next/src/components/landing/TrustBar.tsx`

```tsx
// Structure suggérée
<Box sx={{ display: 'flex', justifyContent: 'center', gap: 4, py: 1.5, bgcolor: 'grey.50' }}>
  <Chip icon={<HomeIcon />} label={`${stats.totalAds.toLocaleString()}+ annonces`} />
  <Chip icon={<StarIcon />} label={`${stats.averageRating}/5 (${stats.totalReviews}+ avis)`} />
  <Chip icon={<LockIcon />} label="Paiement Mobile Money sécurisé" />
</Box>
```

- `keyhome-frontend-next/src/components/landing/HeroSection.tsx` — Insérer `<TrustBar />` entre le hero et la section suivante
- Déplacer 2 `TestimonialCard` compactes à côté du champ de recherche hero

---

## S4. OTP SMS / SMS OTP verification

**Priorité / Priority:** 🟠 MOYEN — Nécessite intégration tiers / Requires 3rd-party integration

**Backend:**
- `composer require africastalking/africastalking` ou configuration Twilio
- `config/services.php` — Ajouter les identifiants SMS

```php
'africastalking' => [
    'username' => env('AT_USERNAME', 'sandbox'),
    'api_key' => env('AT_API_KEY'),
    'from' => env('AT_SMS_FROM', 'KeyHome'),
],
```

- Créer `app/Services/SmsService.php` — Service d'envoi SMS avec fallback email
- `app/Http/Controllers/Api/V1/AuthController.php` (méthode `sendVerificationOtp`) — Envoyer via SMS (principal) + email (fallback)
- `config/app.php` — Ajouter `VERIFICATION_CHANNEL=sms` (sms|email|both)

**Frontend:**
- `keyhome-frontend-next/src/app/(auth)/verify-email/page.tsx` — Ajouter `autocomplete="one-time-code"` au champ OTP pour auto-remplissage WebOTP

---

## S5. Section FAQ tarifs / Pricing FAQ section

**Priorité / Priority:** 🟡 GAIN RAPIDE

**Frontend uniquement :**
- Créer `keyhome-frontend-next/src/components/landing/PricingFAQ.tsx`
- Utiliser les composants MUI `Accordion` / `AccordionSummary` / `AccordionDetails`
- Intégrer dans `keyhome-frontend-next/src/components/landing/PricingSection.tsx` juste en dessous des cartes de packs

**Contenu FAQ minimum :**

| Question | Réponse |
|----------|---------|
| Mes crédits expirent-ils ? | Non, vos crédits n'expirent jamais. Utilisez-les à votre rythme. |
| Le propriétaire ne répond pas ? | Signalez l'annonce depuis la page de détail. Si l'annonce est frauduleuse, vos crédits seront remboursés. |
| Le paiement est-il sécurisé ? | Oui, nous utilisons Flutterwave, une plateforme de paiement certifiée PCI-DSS. Mobile Money et carte bancaire acceptés. |
| Puis-je être remboursé ? | Oui, en cas d'annonce frauduleuse avérée. Contactez notre support. |
| Comment fonctionne le déblocage ? | 1 clic = 2 crédits déduits. Vous obtenez instantanément le numéro de téléphone et le WhatsApp du propriétaire. |
| Y a-t-il des frais cachés ? | Aucun. Zéro commission sur le loyer, zéro frais d'intermédiaire. Vous payez uniquement les crédits. |

---

## S6. Réécriture des bénéfices packs / Pack benefits rewrite

**Priorité / Priority:** 🟡 GAIN RAPIDE

**Frontend uniquement :**
- `keyhome-frontend-next/src/components/landing/PricingSection.tsx` — Modifier les features affichées pour chaque pack en les orientant résultats
- Ajouter le prix par contact sous le prix principal de chaque carte :

```tsx
<Typography variant="body2" color="text.secondary">
  soit <strong>80 FCFA</strong>/contact
</Typography>
```

- Barrer le prix/contact des packs inférieurs pour créer l'effet d'ancrage :

```tsx
// Pack Starter
<Typography sx={{ textDecoration: 'line-through', color: 'text.disabled' }}>
  100 FCFA/contact
</Typography>

// Pack Pro
<Typography color="success.main" fontWeight="bold">
  80 FCFA/contact (-20%)
</Typography>
```

---

## S7. Badge "Garantie satisfaction" / Satisfaction guarantee badge

**Priorité / Priority:** 🟡 GAIN RAPIDE

**Frontend uniquement :**
- Créer `keyhome-frontend-next/src/components/landing/GuaranteeBadge.tsx`
- Placer entre les cartes tarifs et la FAQ

```tsx
<Box sx={{ textAlign: 'center', py: 3 }}>
  <Chip
    icon={<VerifiedUserIcon />}
    label="Annonce fausse ? Crédits remboursés."
    color="success"
    variant="outlined"
    sx={{ fontSize: '1rem', py: 2.5, px: 3 }}
  />
</Box>
```

---

## S8. Inscription différée / Deferred registration

**Priorité / Priority:** 🔵 IMPACT ÉLEVÉ — Effort élevé / High impact, high effort

**Backend:**
- Vérifier que les routes de recherche/listing/détail d'annonces fonctionnent sans authentification (middleware `OptionalAuth` déjà en place)
- Aucun changement backend si les routes públiques existent déjà

**Frontend:**
- `keyhome-frontend-next/src/components/landing/HeroSection.tsx` — Changer le CTA principal de `/register` à `/search`
- `keyhome-frontend-next/src/components/landing/PricingSection.tsx` — Changer les CTA "Choisir ce pack" de `/register` à `/search`
- `keyhome-frontend-next/src/components/landing/CTASection.tsx` — Idem
- Créer `keyhome-frontend-next/src/components/auth/QuickSignUpModal.tsx` — Modal léger (email + mdp + social) déclenché au premier déblocage
- `keyhome-frontend-next/src/services/adService.ts` — Intercepter la réponse 402 et ouvrir `QuickSignUpModal` si non authentifié, ou `PaymentModal` si authentifié

---

## S9. Formulaire inscription 1 étape / 1-step registration form

**Priorité / Priority:** 🔵 IMPACT ÉLEVÉ — Effort élevé

**Backend:**
- `app/Http/Requests/RegisterRequest.php` — Rendre `firstname`, `lastname`, `phone_number`, `city_id` optionnels (`nullable`)
- Créer `app/Http/Requests/CompleteProfileRequest.php` — Validation pour les champs différés
- `app/Http/Controllers/Api/V1/AuthController.php` — Ajouter endpoint `POST /api/v1/auth/complete-profile`
- Ajouter middleware pour vérifier la complétion du profil à certains endpoints critiques (déblocage)

**Frontend:**
- Créer `keyhome-frontend-next/src/app/(auth)/register-quick/page.tsx` — Formulaire simple : email, mot de passe, type de compte
- Créer `keyhome-frontend-next/src/app/(auth)/complete-profile/page.tsx` — Formulaire de complétion post-vérification
- `keyhome-frontend-next/src/services/authService.ts` — Ajouter `completeProfile()` API call

---

## S10. Séquence de rappels de vérification / Verification reminder sequence

**Priorité / Priority:** 🟠 MOYEN

**Backend uniquement :**
- Créer `app/Jobs/SendVerificationReminder.php` — Job planifié envoyant des rappels
- `app/Http/Controllers/Api/V1/AuthController.php` (méthode `register`) — Après inscription, dispatcher 3 jobs différés :
  - +15 minutes : `SendVerificationReminder::dispatch($user)->delay(now()->addMinutes(15))`
  - +2 heures : `SendVerificationReminder::dispatch($user)->delay(now()->addHours(2))`
  - +24 heures : `SendVerificationReminder::dispatch($user)->delay(now()->addDay())`
- Le job vérifie si l'utilisateur a déjà vérifié son email avant d'envoyer
- Créer `app/Mail/VerificationReminderMail.php` — Template email avec lien profond

---

## Calendrier d'implémentation recommandé / Recommended implementation timeline

| Semaine / Week | Suggestions | Type |
|---|---|---|
| **1** | S3 (TrustBar), S5 (FAQ), S6 (Bénéfices), S7 (Garantie) | Frontend uniquement |
| **2** | S1 (Micro-pack), S2 (Mur progressif) | Backend + Frontend |
| **3-4** | S4 (OTP SMS), S10 (Rappels vérification) | Backend principalement |
| **5-6** | S8 (Inscription différée) | Frontend principalement |
| **7-8** | S9 (Formulaire 1 étape) | Backend + Frontend |

---

> **⚠️ Note importante / Important note:**  
> Les taux de conversion indiqués sont des estimations basées sur des benchmarks sectoriels. La première action recommandée est d'instrumenter le tracking analytique réel en utilisant les modèles `SiteVisit` et `AdInteraction` existants pour valider ces chiffres avant de lancer les expérimentations.
>
> The conversion rates shown are estimates based on industry benchmarks. The first recommended action is to instrument real analytics tracking using the existing `SiteVisit` and `AdInteraction` models to validate these figures before running experiments.
