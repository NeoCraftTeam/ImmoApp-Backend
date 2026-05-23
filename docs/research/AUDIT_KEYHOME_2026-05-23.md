# KeyHome — Audit & Research Report
**Date :** 23 Mai 2026 · **Scope :** Standard (5 domaines) · **Marché cible :** Cameroun / CEMAC / UEMOA

---

## Résumé Exécutif

| # | Domaine | Statut actuel | Priorité |
|---|---------|--------------|---------|
| 1 | Messages packs crédits (landing + client panel) | ⚠️ Textes plats, pas de hiérarchie valeur | 🔴 Critique |
| 2 | Attributs d'une location (catalogue complet) | ⚠️ Orienté Airbnb — manque 25+ attrs africains | 🔴 Critique |
| 3 | Carte visite / QR code / palmcard (owner panel) | ❌ Non implémenté | 🟡 Moyen |
| 4 | Contrat de bail électronique + e-signature | ❌ Non implémenté | 🔴 Critique |
| 5 | Stratégie business PropTech Afrique francophone | ⚠️ Modèle partiel — opportunités manquées | 🟡 Moyen |

---

## MODULE 1 — Packs Crédits (messages & UX)

### État actuel KeyHome

**Packs clients (déverrouillage contacts) :**
| Pack | Prix | Crédits | Prix/crédit |
|------|------|---------|------------|
| Starter | 1 000 FCFA | 10 | 100 FCFA |
| Pro ⭐ | 4 000 FCFA | 50 | 80 FCFA |
| Premium | 7 000 FCFA | 120 | ~58 FCFA |

**Packs boost bailleur :**
| Pack | Durée | Reach | Prix crédits |
|------|-------|-------|-------------|
| Starter | 7 j | 500 pers. | 10 cr |
| Pro ⭐ | 7 j | 2 000 pers. | 20 cr |
| Premium | 30 j | 10 000 pers. | 60 cr |

**Plans agences :**
| Plan | Mensuel | Annuel | Annonces max |
|------|---------|--------|-------------|
| Basic | 15 000 FCFA | 150 000 | 20 |
| Premium | 35 000 FCFA | 350 000 | 50 |
| Enterprise | 75 000 FCFA | 750 000 | Illimité |

### Meilleures pratiques trouvées

**Règles UX fondamentales (Jakob's Law + études SaaS 2025) :**

1. **3–4 plans maximum** — Au-delà = paralysie décisionnelle. KeyHome OK (3 packs).
2. **1 plan = 1 persona** — Chaque tier doit s'adresser à un profil distinct.
3. **Mettre en avant "Le + populaire"** — Badge visuel sur le plan médian → +27% conversion.
4. **Afficher le gain par rapport au tier inférieur** — "Économisez 420 FCFA par contact" plutôt que "58 FCFA/contact".
5. **Montrer l'annuel avec 2 mois offerts** — Toggle mensuel/annuel dès la landing.
6. **FAQ intégrée** — Adresser : que se passe-t-il si j'épuise mes crédits ? Expirent-ils ?
7. **Trust signals** — Logos partenaires ou "X annonces déverrouillées ce mois".

### Gaps identifiés & suggestions de copy amélioré

#### Landing Page — Section prix (côté client)

**Actuel (Starter) :**
> "Parfait pour débloquer vos premiers contacts propriétaires"

**Recommandé :**
> **"Trouvez votre premier logement"**
> Idéal si vous débutez votre recherche. 10 contacts propriétaires directs — sans intermédiaire.
> ✓ Numéros & WhatsApp directs · ✓ Sans commission · ✓ Valable 6 mois

---

**Actuel (Pro) :**
> "Le meilleur ratio pour accélérer votre recherche immobilière"

**Recommandé :**
> **"Cherchez sans vous limiter"** 🔥 *Le + populaire*
> 50 contacts pour le prix de 40 — accélérez votre recherche avec le meilleur rapport qualité/prix.
> ✓ -20% par contact vs Starter · ✓ Support prioritaire · ✓ Historique complet

---

**Actuel (Premium) :**
> "Conçu pour les pros et les recherches à fort volume"

**Recommandé :**
> **"Pour les familles et les professionnels en mobilité"**
> 120 contacts — assez pour toute une famille ou un déménagement d'entreprise.
> ✓ -42% par contact vs Starter · ✓ Support 24h/7j · ✓ Partage entre proches

---

#### Panel Client (post-achat, dans le dashboard)

**Message de confirmation d'achat recommandé :**
> ✅ **Pack Pro activé — 50 crédits disponibles**
> Chaque contact déverrouillé vous coûte 80 FCFA. [Voir les annonces →]

**Compteur de crédits dans le header :**
> 🔑 **34 crédits** · [Recharger]

**Alerte faible solde (< 5 crédits) :**
> ⚠️ Il vous reste **3 crédits**. Rechargez pour continuer à débloquer des contacts.
> [Voir les packs →]

**Message lors du déverrouillage :**
> -2 crédits · Il vous reste **32 crédits** · Contact déverrouillé avec succès

#### Boost bailleur — landing (panel owner)

**Actuel (Starter boost) :**
> "Votre bien apparaît en tête des résultats pendant 7 jours."

**Recommandé :**
> **"Donnez un coup de pouce à votre annonce"**
> Passez en tête des résultats pendant 7 jours et touchez jusqu'à 500 locataires potentiels.
> ✓ Visible immédiatement · ✓ +30 pts de visibilité · ✓ Sans engagement

**Actuel (Premium boost) :**
> "Visibilité maximale pendant tout un mois."

**Recommandé :**
> **"Louez en moins de 30 jours, garanti"** ⭐ *Meilleur rapport*
> Un mois en tête du fil + des recommandations — 10 000 locataires potentiels.
> ✓ Priorité fil principal · ✓ +100 pts · ✓ Statistiques détaillées incluses

### Gap Matrix — Module 1

| Gap | Sévérité | Effort | Action |
|-----|----------|--------|--------|
| Copy trop générique, pas de valeur chiffrée | 🔴 | 2h | Mettre à jour PointSystemSeeder + BoostPackSeeder |
| Pas d'alerte faible solde | 🟡 | 4h | Composant SoldeAlert dans le dashboard |
| Pas de toggle mensuel/annuel sur agences | 🟡 | 3h | Ajouter sur SubscriptionPlanSeeder pricing |
| Pas de FAQ dédiée sur la page crédits | 🟡 | 2h | Section FAQ accordion landing |
| Pas de partage de crédits entre proches | 🟢 | 1j | Feature future |
| Expiration des crédits non affichée | 🔴 | 2h | Ajouter expires_at à PointPackage |

---

## MODULE 2 — Attributs d'une Location (catalogue complet)

### État actuel KeyHome

**Catalogue existant — 11 catégories, ~130 attributs :**

| Catégorie | Nb attrs | Focus |
|-----------|----------|-------|
| Salle de bain | 14 | ✅ Complet |
| Chambre et linge | 13 | ✅ Complet |
| Cuisine | 19 | ✅ Complet |
| Séjour | 10 | ✅ OK |
| Divertissement | 13 | ✅ OK |
| Connectivité | 7 | ✅ OK |
| Climatisation & chauffage | 9 | ✅ OK |
| Sécurité | 12 | ✅ OK |
| Extérieur | 14 | ✅ OK |
| Stationnement & accès | 13 | ✅ OK |
| Buanderie | 5 | ✅ OK |
| Animaux & famille | 6 | ✅ OK |

### Gaps critiques — Attributs manquants pour le marché africain

Le catalogue actuel est **excellent pour les locations meublées courte durée (type Airbnb)** mais **manque cruellement des attributs fondamentaux pour la location longue durée en Afrique centrale.**

#### 🔴 CATÉGORIE MANQUANTE : Informations générales du bien

Ces attributs DOIVENT apparaître sur chaque annonce et sont filtrables en recherche :

```
STRUCTURE DE BASE (attrs filtrables / champs de l'annonce Ad)
├── Type de logement : Studio / Appartement / Villa / Duplex / Chambre / Palier
├── Surface habitable (m²)
├── Nombre de pièces
├── Nombre de chambres
├── Nombre de salles de bain / douches
├── Étage (RDC / 1er / 2e / Dernier)
├── Nombre d'étages du bâtiment
├── Année de construction (approximative)
└── État général : Neuf / Rénové / Bon état / À rénover

FINANCES (obligatoires)
├── Loyer mensuel (FCFA)
├── Charges incluses : Oui / Non / Partiellement
├── Détail charges : eau, électricité, gardien, ordures
├── Caution (nombre de mois)
├── Avance demandée (nombre de mois)
├── Frais d'agence : Oui / Non / Montant
└── Honoraires de visite

TYPE DE BAIL
├── Durée : Court terme / Long terme / Indéfinie
├── Durée minimale (mois)
└── Renouvellement automatique : Oui / Non
```

#### 🔴 CATÉGORIE MANQUANTE : Infrastructure & Autonomie (critique Afrique)

```
ÉLECTRICITÉ
├── Alimentation principale : AES-SONEL / Enéo / Groupe électrogène uniquement
├── Groupe électrogène : Inclus / Partagé / Non
├── Fréquence coupures : Rare / Occasionnelle / Fréquente
├── Onduleur / Batterie de secours
└── Panneaux solaires

EAU
├── Eau courante : Permanente / Intermittente / Puits
├── Château d'eau / Réservoir : Inclus / Non
├── Capacité du réservoir (litres)
├── Pompe à eau électrique
└── Source d'eau : CDE/CAMWATER / Puits / Forage / Citerne

ASSAINISSEMENT
├── Type : Tout-à-l'égout / Fosse septique / Latrine
└── Vide-ordures / Point collecte ordures
```

#### 🟡 CATÉGORIE MANQUANTE : Sécurité & Gardiennage (priorité Cameroun)

```
├── Mur de clôture : Oui / Non
├── Portail sécurisé
├── Gardien résidant : Oui / Non / Partagé
├── Gardien nocturne
├── Quartier sécurisé / Résidence fermée
├── Caméras de surveillance extérieures
└── Éclairage extérieur
```

#### 🟡 CATÉGORIE MANQUANTE : Localisation & Accessibilité

```
├── Quartier / Arrondissement
├── Distance centre-ville (km ou min)
├── Proximité marché
├── Proximité école / université
├── Proximité hôpital / pharmacie
├── Transport commun proche : Taxi / Moto-taxi / Bus
├── Rue goudronnée : Oui / Non
└── Accessible en voiture (piste praticable saison des pluies)
```

#### 🟡 CATÉGORIE MANQUANTE : Animaux & Règles de vie

```
├── Animaux acceptés : Oui / Non / Petits animaux uniquement
├── Fumeur accepté : Oui / Non
├── Sous-location autorisée
├── Colocataires acceptés
├── Personnes seules / Couple / Famille
└── Nombre maximum d'occupants
```

#### 🟢 ATTRIBUTS ADDITIONNELS RECOMMANDÉS

```
├── Cuisine séparée / Ouverte / Kitchenette / Pas de cuisine
├── Salon séparé : Oui / Non
├── Magasin / Débarras
├── Véranda / Couloir
├── Personnel de maison inclus : Femme de ménage / Gardien payé par proprio
├── Ordures ménagères : Inclus / À charge du locataire
└── Peinture incluse en fin de bail : Oui / Non
```

### Benchmark concurrents

| Plateforme | Attrs infrastructure | Attrs Afrique | Attrs juridiques |
|-----------|---------------------|--------------|-----------------|
| Mubawab (Maroc) | ⚠️ Partiel | ✅ Bon | ✅ Caution/Avance |
| Property24 (Afrique Sud) | ✅ Complet | ✅ Bon | ✅ Complet |
| SeLoger (France) | ✅ Complet | ❌ N/A | ✅ Complet |
| **KeyHome actuel** | ❌ Absent | ❌ Absent | ❌ Absent |
| **KeyHome recommandé** | ✅ Voir ci-dessus | ✅ Voir ci-dessus | ✅ Voir ci-dessus |

### Gap Matrix — Module 2

| Gap | Sévérité | Effort | Action |
|-----|----------|--------|--------|
| Pas d'attrs infrastructure (eau, électricité, groupe) | 🔴 | 1j | Ajouter catégorie dans PropertyAttributeCatalog |
| Pas d'attrs finances (caution, avance, charges) | 🔴 | 4h | Champs structurés sur le modèle Ad |
| Pas d'attrs type de bail / durée | 🔴 | 4h | Champs enum sur Ad |
| Pas de localisation fine (quartier, rue goudronnée) | 🟡 | 4h | Lié à Quarter model existant |
| Pas d'attrs sécurité africains (mur, gardien) | 🟡 | 2h | Nouvelle catégorie PropertyAttributeCatalog |
| Catalogue trop orienté location courte durée | 🟡 | 1j | Distinguer attrs "meublé" vs "location longue durée" |

---

## MODULE 3 — Carte de Visite / Palmcard / QR Code (Owner Panel)

### État actuel KeyHome

❌ **Non implémenté.** Aucun système de carte de visite digitale, palmcard, ni QR code pour les biens ou les bailleurs.

### Meilleures pratiques trouvées

**Source : Supercode.com (2026), données secteur immobilier**

#### Pourquoi les QR codes sont devenus standards en immobilier

- **37% CTR** sur les parcours initiés par QR code (vs 2–5% pour la pub display)
- **79%** des utilisateurs smartphone ont scanné un QR code dans l'année
- **13,6%** de taux de conversion lead-to-listing sur QR codes immobiliers
- Marché global QR : **$13 Mds en 2025**, +433% de scans depuis 2021

#### 8 types de QR codes pour l'immobilier

| Type | Usage KeyHome recommandé |
|------|-------------------------|
| **URL dynamique** | Lien vers la fiche de l'annonce avec photos, prix, visite 3D |
| **PDF** | Brochure de l'annonce téléchargeable sur le téléphone |
| **vCard** | Contact complet du bailleur (nom, tél, WhatsApp, email) |
| **WhatsApp** | Ouvrir une conversation WhatsApp pré-remplie |
| **SMS** | Demande de visite par SMS pré-rédigé |
| **Galerie images** | Galerie photos du bien avec CTA |
| **Feedback** | Formulaire avis post-visite |
| **Social Media** | Profil bailleur / agence sur Facebook/Instagram |

#### Ce que doit contenir une Palmcard (fiche propriété imprimable)

```
┌─────────────────────────────────────────┐
│  [Photo principale du bien]             │
│                                         │
│  Villa 4P · Bastos, Yaoundé             │
│  350 000 FCFA/mois · Ref: KH-2024-0456 │
│                                         │
│  ✓ 4 ch · 2 SDB · Parking · Groupe    │
│  ✓ Eau permanente · Gardien · WiFi     │
│                                         │
│  [QR CODE]        [Logo KeyHome]        │
│  Scannez pour     keyhome.app           │
│  voir + de photos                       │
│                                         │
│  📞 +237 6XX XX XX XX                  │
│  💬 WhatsApp disponible                 │
└─────────────────────────────────────────┘
```

#### Recommandations techniques pour KeyHome

**Génération PDF côté serveur :**
- Utiliser DomPDF (déjà présent dans le projet) pour la palmcard
- Format : A5 (148×210mm) ou 10×15cm (format carte postale)
- QR code : encoder l'URL `keyhome.app/ads/{slug}` — minimum **5×5 cm** pour scan à distance
- Inclure logo vectoriel + fond dégradé crimson `#F6475F`

**Données à encoder dans le QR :**
```json
{
  "url": "https://keyhome.app/ads/{slug}?utm_source=qr&utm_medium=print",
  "fallback": "https://keyhome.app/ads/{slug}"
}
```

**vCard du bailleur :**
```
BEGIN:VCARD
VERSION:3.0
FN:Jean Dupont
ORG:KeyHome Bailleur
TEL;TYPE=CELL:+237612345678
EMAIL:jean@example.com
URL:https://keyhome.app/owner/{id}
END:VCARD
```

**Implémentation owner panel :**
- Bouton "Télécharger la palmcard" sur chaque annonce active
- Bouton "Ma carte de visite digitale" dans le profil bailleur
- QR code inline visible sur la fiche annonce (modal ou section dédiée)
- Partage direct WhatsApp avec lien + description

### Gap Matrix — Module 3

| Gap | Sévérité | Effort | Action |
|-----|----------|--------|--------|
| Pas de génération palmcard PDF | 🟡 | 2j | Controller DomPDF → Ad palmcard |
| Pas de QR code pour les annonces | 🟡 | 4h | Package `simplesoftwareio/simple-qrcode` |
| Pas de vCard bailleur | 🟢 | 4h | Générer vCard depuis le profil User |
| Pas de CTA WhatsApp depuis l'annonce | 🔴 | 2h | Bouton wa.me sur la fiche annonce |
| QR code non trackable (UTM) | 🟢 | 2h | Ajouter utm_source=qr sur tous les liens |

---

## MODULE 4 — Contrat de Bail Électronique & E-Signature

### État actuel KeyHome

❌ **Non implémenté.** Le modèle `LeaseContract` existe mais sans génération PDF ni signature électronique.

### Cadre juridique

#### France / eIDAS (référence internationale)

**3 niveaux de signature électronique (règlement eIDAS 910/2014) :**

| Niveau | Description | Valeur juridique | Usage immobilier |
|--------|-------------|-----------------|-----------------|
| Simple | Clic / scan / case à cocher | Faible | Non recommandé |
| **Avancée** | Authentification renforcée + certificat | **Fort — standard du marché** | ✅ Recommandé |
| Qualifiée | Certificat qualifié + dispositif sécurisé | Très fort | Trop contraignant |

**Règle d'or :** La **signature avancée** est la norme pour les baux numériques. Validée par la Cour de cassation française (arrêt 2023).

#### Cameroun / OHADA

**Sources :**
- Loi camerounaise n°2010/021 sur le commerce électronique : reconnaissance de la signature électronique obtenue par **algorithme de chiffrement asymétrique**
- Acte Uniforme OHADA (révisé) : reconnaît la **validité des contrats dématérialisés et des signatures électroniques**
- Article 4 loi camerounaise 2010 : signature électronique légalement valide si :
  1. Identité du signataire garantie
  2. Intégrité du document préservée
  3. Consentement éclairé prouvable

**Limite principale :** L'OHADA ne règle pas entièrement les questions de preuve numérique — utiliser un prestataire tiers certifié réduit le risque.

### Contenu obligatoire d'un Contrat de Bail (Cameroun)

#### Clauses obligatoires

```
IDENTIFICATION DES PARTIES
├── Identité complète bailleur (NI, adresse)
├── Identité complète locataire (NI, adresse)
└── Garant éventuel (NI, adresse, engagement)

DESCRIPTION DU BIEN
├── Adresse complète
├── Type de logement
├── Surface (si connue)
├── État des lieux : référence document joint
└── Numéro de parcelle cadastrale (si disponible)

CONDITIONS FINANCIÈRES
├── Loyer mensuel (FCFA, en lettres et chiffres)
├── Charges comprises ou non (détail)
├── Caution (montant, modalités restitution)
├── Avance sur loyer (nombre de mois)
└── Indexation annuelle (si prévue)

DURÉE
├── Date de début
├── Durée (déterminée / indéterminée)
├── Conditions de renouvellement
└── Préavis de congé (bailleur et locataire)

OBLIGATIONS DU BAILLEUR
├── Délivrance du logement en bon état
├── Réalisation des grosses réparations
└── Quittance de loyer mensuelle

OBLIGATIONS DU LOCATAIRE  
├── Paiement ponctuel du loyer
├── Entretien courant
├── Pas de sous-location sans accord écrit
└── Restitution en bon état

CLAUSES RÉSOLUTOIRES (OHADA)
├── Non-paiement loyer (> X jours)
├── Trouble de voisinage grave
└── Usage non conforme

ANNEXES OBLIGATOIRES
├── État des lieux d'entrée (signé contradictoire)
├── Inventaire du mobilier (si meublé)
├── Règlement de copropriété (si applicable)
└── Diagnostics (si disponibles)
```

### Workflow e-signature recommandé pour KeyHome

```
[Bailleur crée le contrat dans l'admin panel]
        ↓
[Système génère PDF avec DomPDF]
        ↓
[Envoi lien sécurisé par email + SMS au locataire]
        ↓
[Locataire ouvre le document → vérifie son identité]
   (OTP SMS + photo pièce d'identité)
        ↓
[Locataire signe → horodatage + hash SHA256 du document]
        ↓
[Bailleur contre-signe]
        ↓
[Document final archivé + disponible pour les 2 parties]
        ↓
[Email récapitulatif avec PDF signé + audit trail]
```

### Solutions e-signature compatibles

| Solution | Niveau | Afrique | Prix | Recommandation |
|----------|--------|---------|------|---------------|
| **YouSign** | Avancée | ✅ API REST | ~€0.10/sign | ✅ Idéal |
| **DocuSign** | Avancée/Qualifiée | ✅ | ~$1/sign | ✅ Entreprise |
| **Universign** | Avancée | ✅ | ~€0.30/sign | ✅ Bon |
| **Implémentation maison** | Simple | ✅ | Développement | ⚠️ Risque juridique |

**Recommandation KeyHome :** Intégrer **YouSign API** (REST, webhooks, stockage certifié eIDAS).

### Archivage & Preuve numérique

Exigences légales (Code civil fr. art 1366-1369, OHADA) :
- Document **non altérable** après signature (hash SHA256)
- **Horodatage** certifié (timestamp RFC 3161)
- **Audit trail** complet (qui, quand, depuis où)
- Conservation : **minimum 5 ans** (bail résidentiel standard)
- Stockage : coffre-fort numérique certifié **ou** Cloudflare R2 + hash stocké en base

### Gap Matrix — Module 4

| Gap | Sévérité | Effort | Action |
|-----|----------|--------|--------|
| Pas de génération PDF de contrat de bail | 🔴 | 2j | DomPDF + template Blade |
| Pas de signature électronique | 🔴 | 3j | Intégration YouSign API |
| Pas d'audit trail de signature | 🔴 | 1j | Colonnes signed_at, signature_hash, ip_address |
| Pas d'état des lieux numérique | 🟡 | 2j | Formulaire + photos + signature |
| Pas d'archivage certifié | 🟡 | 1j | R2 + hash SHA256 en DB |
| Workflow multi-signataires non géré | 🟡 | 1j | LeaseSignatureRequest model (déjà présent ✅) |

---

## MODULE 5 — Stratégie Business PropTech Afrique Francophone

### Contexte de marché

#### Données clés (2024)

- **Financement PropTech Afrique :** $16,7M en 2022 / $16,2M en 2023 / Top 7 = $45M+ cumulés
- **Croissance urbaine :** Afrique = croissance urbaine la plus rapide au monde d'ici 2050 (+950M habitants urbains)
- **Construction africaine :** +6,4% de croissance 2019–2024 (Mordor Intelligence)
- **PropTech global :** $2Mds en 2013 → $18Mds en 2018 → $13Mds QR codes seuls en 2025

#### Concurrents directs en Afrique francophone

| Plateforme | Pays | Modèle | Financement |
|-----------|------|--------|------------|
| **Mubawab** | Maroc (+ MENA) | Portail listing + leads | $17,9M |
| **Jumia House** | Multi-pays | Portail listing | Groupe Jumia |
| **SenegImmo** | Sénégal | Portail listing | Bootstrapped |
| **TopImmo** | Cameroun | Portail listing | Local |
| **Avito Maroc** | Maroc | Marketplace généraliste | Naspers |
| **SmallSmall** | Nigeria | Paiement mensuel loyer | $3M |

#### Ce que SmallSmall a découvert (Nigeria) — applicable au Cameroun

- **80% des locataires préfèrent payer mensuellement** (vs l'avance annuelle/semestrielle habituelle)
- < 7% de taux de défaut avec paiement mensuel
- $5M de revenus en 3 ans / profit atteint / 476 000 utilisateurs inscrits

### Modèle de revenus recommandé pour KeyHome

#### Revenus existants (à optimiser)

```
1. Crédits déverrouillage (côté locataire) ✅
   → Gap : pas d'expiration, pas d'alerte, copy faible

2. Boosts annonce (côté bailleur) ✅  
   → Gap : reach non prouvable, pas de statistiques post-boost

3. Abonnements agences ✅
   → Gap : pas de trial gratuit, pas d'annual discount clair
```

#### Revenus additionnels à implémenter (priorités)

```
PRIORITÉ 1 — Court terme (3 mois)
├── Visite 3D premium (bailleur paie pour visite 3D incluse)
├── Contrat de bail digital (forfait/bail ou abonnement)
├── Badge "Bailleur vérifié" (payant, instruction manuelle)
└── Mise en avant "À la une" sur la landing (slot fixe)

PRIORITÉ 2 — Moyen terme (6 mois)
├── Commission sur premier loyer (lead qualifié → conversion)
├── Service état des lieux numérique
├── Assurance loyer impayé (partenariat assureur)
└── Gestion locative SaaS (quittances, incidents, comptabilité)

PRIORITÉ 3 — Long terme (12 mois)
├── Service déménagement (déjà en moving-service)
├── Crédit immobilier (partenariat banque / microcrédit)
├── Investissement fractionné (immobilier tokenisé)
└── API B2B (agences, promoteurs, notaires)
```

### Stratégie d'acquisition adaptée à l'Afrique

#### Ce qui fonctionne (benchmarks continent)

| Canal | ROI | Exemple |
|-------|-----|---------|
| **WhatsApp Groups** | ⭐⭐⭐⭐⭐ | Partage viral de fiches annonces |
| **Facebook / Instagram** | ⭐⭐⭐⭐ | Ciblage géo Douala/Yaoundé |
| **Réseau agents terrain** | ⭐⭐⭐⭐ | Commission apporteur d'affaires |
| **Parrainage locataire** | ⭐⭐⭐⭐ | +5 crédits pour chaque ami inscrit |
| **TikTok (visite 3D)** | ⭐⭐⭐ | Contenu viral biens de luxe |
| **SEO local** | ⭐⭐⭐ | "Appartement à louer Douala Bonapriso" |
| Pub Google/Meta payante | ⭐⭐ | CPC élevé pour volume faible |

#### Mobile Money — Clé de la conversion

**Ordre de priorité paiements Cameroun :**
1. **Orange Money** (dominant Cameroun)
2. **MTN Mobile Money** (fort en zone anglophone)
3. **Wave** (en expansion)
4. Virement bancaire (minoritaire)
5. Carte bancaire Stripe (diaspora / expatriés)

→ KeyHome a GeniusPay (Orange/MTN) ✅ — **s'assurer que les packs crédits sont achetables en 1 tap depuis l'app mobile.**

#### Pricing stratégique (benchmarks)

| Service | Prix marché Cameroun | KeyHome actuel | Recommandation |
|---------|---------------------|----------------|---------------|
| Annonce gratuite | Standard | ✅ Gratuit | Garder |
| Boost 7j | 3 000–5 000 FCFA | 10 cr (~800F) | ⚠️ Trop bas → Remonter ou ajouter valeur |
| Déverrouillage 1 contact | 100–300 FCFA/contact | 100 FCFA | ✅ Compétitif |
| Abonnement agence | 15 000–50 000 FCFA | 15 000–75 000 | ✅ OK |
| Bail digital | 2 000–10 000 FCFA/bail | Non | Ajouter |

### Gap Matrix — Module 5

| Gap | Sévérité | Effort | Action |
|-----|----------|--------|--------|
| Boost bailleur trop bon marché (crédits) | 🟡 | 2h | Rebalancer prix boost ou ajouter valeur |
| Pas de "paiement mensuel loyer" (SmallSmall model) | 🟡 | 2 sem | Feature forte différenciatrice |
| Pas de commission sur conversion (lead fee) | 🟡 | 1 sem | Tracking confirmation bail |
| Pas de badge "Bailleur Vérifié" payant | 🟡 | 3j | Trust signal + revenu récurrent |
| Pas de parrainage locataire | 🟢 | 3j | +crédits pour chaque ami inscrit |
| Pas de statistiques post-boost | 🔴 | 1 sem | Vue analytics pour l'annonce boostée |

---

## Plan d'Action Prioritaire

| # | Action | Module | Sévérité | Effort estimé | Sprint |
|---|--------|--------|----------|--------------|--------|
| 1 | Ajouter catégories infra (eau, élec, groupe) au PropertyAttributeCatalog | Attributs | 🔴 | 4h | S1 |
| 2 | Ajouter champs finances (caution, avance, charges) au modèle Ad | Attributs | 🔴 | 1j | S1 |
| 3 | Améliorer copy des 3 packs crédits (seeder + admin) | Crédits | 🔴 | 2h | S1 |
| 4 | Alerte faible solde (< 5 crédits) dans le dashboard client | Crédits | 🟡 | 4h | S1 |
| 5 | Bouton WhatsApp direct sur chaque annonce | QR/Carte | 🔴 | 2h | S1 |
| 6 | Génération QR code pour les annonces | QR/Carte | 🟡 | 4h | S2 |
| 7 | Template PDF palmcard (DomPDF) | QR/Carte | 🟡 | 2j | S2 |
| 8 | Template PDF contrat de bail (DomPDF) | Bail | 🔴 | 2j | S2 |
| 9 | Intégration YouSign API (e-signature) | Bail | 🔴 | 3j | S2 |
| 10 | Audit trail signature (colonnes DB + hash) | Bail | 🔴 | 1j | S2 |
| 11 | Statistiques post-boost (vues, clics) | Business | 🔴 | 1 sem | S3 |
| 12 | Badge "Bailleur Vérifié" payant | Business | 🟡 | 3j | S3 |
| 13 | Programme parrainage locataire (+crédits) | Business | 🟢 | 3j | S3 |
| 14 | Paiement mensuel loyer (SmallSmall model) | Business | 🟡 | 2 sem | S4 |

---

## Sources & Références

| Source | Domaine | URL |
|--------|---------|-----|
| Lollypop Design Blog (2025) | Pricing UX | lollypop.design/blog/2025/may/saas-pricing-page-design |
| GérerSeul.com (2025) | Bail électronique | gererseul.com/bail-electronique-loi-2025 |
| PropTech RoundUp Substack (2024) | Business Africa | proptechroundup.substack.com |
| Supercode.com (2026) | QR codes immobilier | supercode.com/blog/real-estate-marketing |
| Baobab Network (2020) | PropTech Africa map | thebaobabnetwork.com |
| Scribd — Modèle bail Cameroun | Bail OHADA | scribd.com |
| DHAvocats.com | E-signature Cameroun | dhavocats.com |
| Kalieu-Elongo.com | Preuve élec. Cameroun | kalieu-elongo.com |
| Knight Frank Africa Report 2024/25 | Marché immobilier | knightfrank.com |
| AVCA Francophone Africa 2024 | Investissement | avca.africa |
| PropertyAttributeCatalog.php (KeyHome) | Attrs existants | app/Support/ |
| PointSystemSeeder.php (KeyHome) | Crédits existants | database/seeders/ |
| BoostPackSeeder.php (KeyHome) | Boosts existants | database/seeders/ |

---

*Rapport généré le 23 Mai 2026 — KeyHome Research Auditor · NéoCraft Team*
