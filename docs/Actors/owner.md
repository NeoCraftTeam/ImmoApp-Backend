# Guide complet du Panel Owner (Bailleur)

Ce document decrit le parcours complet d'un bailleur sur KeyHome, de la creation de son compte jusqu'a la gestion quotidienne de ses biens.

**URL d'acces** : `https://owner.keyhome.app`

---

## Table des matieres

1. [Creation de compte](#1-creation-de-compte)
2. [Connexion](#2-connexion)
3. [Profil utilisateur](#3-profil-utilisateur)
4. [Tableau de bord](#4-tableau-de-bord)
5. [Gestion des annonces](#5-gestion-des-annonces)
6. [Visite virtuelle 3D](#6-visite-virtuelle-3d)
7. [Gestion des disponibilites](#7-gestion-des-disponibilites)
8. [Demandes de visite](#8-demandes-de-visite)
9. [Contrats de bail](#9-contrats-de-bail)
10. [Avis clients](#10-avis-clients)
11. [Notifications](#11-notifications)

---

## 1. Creation de compte


Le bailleur cree son compte via le formulaire d'inscription. Tout le monde peut s'inscrire comme bailleur, y compris les agents immobiliers independants.

### Champs du formulaire

| Champ | Obligatoire | Description |
|-------|:-----------:|-------------|
| Prenom | Oui | Prenom du bailleur |
| Nom | Oui | Nom de famille |
| Adresse e-mail | Oui | Email unique, servira d'identifiant de connexion |
| Numero de telephone | Oui | Numero affiche aux clients qui debloquent les annonces |
| Ce numero est aussi WhatsApp | Non | Checkbox — permet aux clients de contacter via WhatsApp |
| Ville | Oui | Select searchable — ville de residence du bailleur |
| Mot de passe | Oui | Mot de passe du compte |
| Confirmer le mot de passe | Oui | Doit correspondre au mot de passe |

### Connexion sociale

Le bailleur peut aussi s'inscrire via **Google** (OAuth).

### Apres l'inscription

- Le compte est cree avec le role `CUSTOMER` puis promu automatiquement en `BAILLEUR` via le service `AgencyService::promoteToBailleur()`
- Un portefeuille (agency) personnel est cree pour le bailleur
- Un email de verification est envoye
- Le bailleur est redirige vers le tableau de bord

---

## 2. Connexion

**URL** : `/owner/login`

| Champ | Description |
|-------|-------------|
| Adresse e-mail | Email du compte |
| Mot de passe | Mot de passe |
| Se souvenir de moi | Checkbox pour maintenir la session |

Options disponibles :
- **Mot de passe oublie** : envoie un lien de reinitialisation par email
- **Connexion Google** : OAuth
- **Authentification multi-facteurs** : optionnelle (App TOTP ou email)

---

## 3. Profil utilisateur

**Acces** : icone utilisateur en haut a droite > Profil

Le profil est organise en 4 sections :

### Section 1 : Photo de profil

| Champ | Description |
|-------|-------------|
| Avatar | Upload d'image (JPEG, PNG, WebP). Max 2 Mo. Editeur d'image integre avec crop circulaire. Optimisation automatique en WebP. Stocke dans `storage/app/public/avatars/` |

### Section 2 : Informations personnelles

| Champ | Obligatoire | Description |
|-------|:-----------:|-------------|
| Prenom | Oui | Prenom |
| Nom | Oui | Nom de famille |
| Adresse e-mail | Oui | Modification possible, necessite re-verification |

### Section 3 : Contact

| Champ | Obligatoire | Description |
|-------|:-----------:|-------------|
| Numero de telephone | Oui | Format : +237 6XX XXX XXX. Ce numero est affiche aux clients |
| Ce numero est disponible sur WhatsApp | Non | Checkbox |

### Section 4 : Securite (repliable)

| Champ | Description |
|-------|-------------|
| Nouveau mot de passe | Laisser vide pour ne pas changer |
| Confirmer le mot de passe | Doit correspondre |

---

## 4. Tableau de bord

**URL** : `/owner` (page d'accueil apres connexion)

Le tableau de bord affiche 3 widgets :

### Widget 1 : Statistiques globales (30 derniers jours)

4 cartes avec mini-graphiques de tendance :

| Statistique | Description | Graphique |
|-------------|-------------|-----------|
| Mes Biens | Nombre total d'annonces du bailleur | Courbe sur 7 mois (annonces creees par mois) |
| Vues | Nombre total de vues sur toutes les annonces | Courbe sur 7 semaines |
| Favoris | Nombre total de mises en favoris | Courbe sur 7 semaines |
| Engagement | Ratio `(favoris / impressions) x 100` en % | Vert si > 5%, gris sinon |

### Widget 2 : Graphique Vues & Favoris

Graphique en ligne sur 30 jours avec 2 courbes :
- **Vues** (teal) : nombre de vues par jour
- **Favoris** (bleu) : nombre de favoris par jour

### Widget 3 : Top Annonces

Tableau des 5 meilleures annonces triees par vues :

| Colonne | Description |
|---------|-------------|
| Annonce | Titre (limite 40 caracteres) |
| Statut | Badge colore (Disponible = vert, En attente = orange, etc.) |
| Vues | Nombre de vues sur 30 jours |
| Favoris | Nombre de favoris sur 30 jours |

---

## 5. Gestion des annonces

**Menu** : Mes Biens > Mes Annonces

### 5.1 Liste des annonces

Tableau avec les colonnes :

| Colonne | Description |
|---------|-------------|
| Photos | Miniatures empilees (max 3) |
| Titre | Titre de l'annonce, recherchable |
| Adresse | Adresse du bien, recherchable |
| Prix | En FCFA, triable |
| Surface | En m², triable |
| Statut | Badge colore |
| Vues | Nombre de vues |
| Visible | Icone oui/non |
| Cree le | Date de creation (cache par defaut) |

Filtre disponible : **Corbeille** (afficher les annonces supprimees)

### 5.2 Creation / Modification d'une annonce

Le formulaire s'ouvre en slide-over. Voici tous les champs organises par section :

#### Section : Informations principales

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Titre de l'annonce | Texte | Oui | Ex: "Appartement 3 pieces vue mer — Bonanjo" |
| Description | Zone de texte | Oui | Description detaillee du bien. Bouton **"Ameliorer avec l'IA"** disponible pour reformuler automatiquement |

#### Section : Photos du bien

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Photos | Upload multiple | Non | Jusqu'a 10 photos (JPEG, PNG, WebP). Max 5 Mo chacune. Glisser-deposer pour reordonner |

#### Section : Quartier & Categorie

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Quartier | Select searchable | Oui | Liste des quartiers disponibles avec recherche |
| Categorie d'annonce | Select searchable | Oui | Type de bien (Appartement, Studio, Chambre, Villa, Bureau, Local commercial, etc.) |

#### Section : Caracteristiques

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Adresse | Texte | Oui | Ex: "Rue de la Liberte, Bonanjo" |
| Prix | Nombre | Oui | Prix en FCFA. Minimum : 0 |
| Surface | Nombre | Oui | Surface en m². Minimum : 1 |
| Chambres | Nombre | Oui | Nombre de chambres. Minimum : 0 |
| Salles de bain | Nombre | Oui | Nombre de salles de bain. Minimum : 0 |
| Parking inclus | Toggle | Non | Oui/Non |

#### Section : Equipements & Services

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Equipements | Select multiple | Non | Liste d'equipements groupes par categorie (climatisation, wifi, gardiennage, piscine, etc.). Recherchable |

#### Section : Informations supplementaires

**Conditions du bail :**

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Depot de garantie | Select | Non | 1 mois, 2 mois, 3 mois, 4 mois ou 5 mois |
| Duree minimum du bail | Select | Non | 6 mois, 1 an renouvelable, 2 ans renouvelable, 3 ans renouvelable |

**Charges detaillees :**

| Champ | Type | Obligatoire | Condition | Description |
|-------|------|:-----------:|-----------|-------------|
| Charges au forfait | Toggle | Non | — | Activer si les charges sont un montant fixe mensuel |
| Montant forfaitaire mensuel | Nombre | Non | Si forfait active | Montant en FCFA. Ex: 25 000 |
| Frais d'eau (mensuel) | Nombre | Non | Si forfait desactive | Montant en FCFA. Ex: 10 000 |
| Frais d'electricite (mensuel) | Nombre | Non | Si forfait desactive | Montant en FCFA. Ex: 15 000 |
| Autres charges | Zone de texte | Non | — | Ex: "Gardiennage: 5 000 FCFA/mois, Ordures: 2 000 FCFA/mois" |
| Etat des lieux (PDF) | Upload fichier | Non | — | Document PDF. Max 10 Mo |

#### Section : Localisation sur la carte

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Localisation | Carte interactive | Non | Carte Mapbox avec marqueur deplacable. Position par defaut : Douala (4.0511, 9.7679). Le bailleur peut deplacer le marqueur ou utiliser "Ma position" |

#### Section : Statut de l'annonce

Visible uniquement apres la creation et si l'annonce n'est plus en attente :

| Champ | Type | Description |
|-------|------|-------------|
| Statut | Boutons toggle | Disponible, Reserve, En location, Vendu |

### 5.3 Cycle de vie d'une annonce

```
Creation → En attente (PENDING)
                ↓
        [Admin verifie]
           ↙        ↘
    Approuvee      Refusee (DECLINED)
   (AVAILABLE)         ↓
       ↓          [Bailleur corrige]
       ↓               ↓
       ↓          "Soumettre a nouveau"
       ↓               ↓
       ↓          → En attente (PENDING)
       ↓
  Le bailleur peut changer le statut :
       ↓
  AVAILABLE → RESERVED → RENTED → AVAILABLE
       ↓
     SOLD (fin)
```

### 5.4 Actions sur une annonce

| Action | Condition | Description |
|--------|-----------|-------------|
| Voir | Toujours | Affiche le detail complet de l'annonce |
| Modifier | Toujours | Ouvre le formulaire en slide-over |
| Contrat de bail | Statut = Disponible ou Reserve | Genere un contrat PDF (voir section 9) |
| Soumettre a nouveau | Statut = Refusee | Renvoie l'annonce en moderation |
| Supprimer | Toujours | Suppression douce (corbeille) |

---

## 6. Visite virtuelle 3D

La visite 3D est integree dans le formulaire de l'annonce, en 2 etapes.

### Etape 1 : Gestion des pieces

Le bailleur ajoute des "scenes" (pieces) via un repeater :

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Nom de la piece | Texte | Oui | Ex: "Salon", "Chambre parentale", "Cuisine" |
| Photo 360° | Upload image | Oui | Photo panoramique au format equirectangulaire (ratio 2:1 recommande). Max 30 Mo |

Le bailleur peut :
- Ajouter plusieurs pieces (minimum 1)
- Reordonner les pieces par glisser-deposer
- Dupliquer une piece
- Replier/deplier chaque piece

### Etape 2 : Liens entre les pieces

Un editeur visuel (hotspot editor) permet de :
- Placer des points de navigation sur chaque photo 360°
- Relier les pieces entre elles (ex: cliquer sur une porte dans le salon mene a la cuisine)
- Definir l'angle de vue initial de chaque piece (pitch, yaw, champ de vision)

La visite 3D est visible par les clients sur le frontend et l'app mobile.

---

## 7. Gestion des disponibilites

**Menu** : Visites > Mes disponibilites

Le bailleur definit ses creneaux de visite pour que les clients puissent reserver.

### 7.1 Creer une disponibilite

Bouton **"Nouvelle disponibilite"** en haut de la page. Formulaire :

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Annonce | Select | Oui | Choisir l'annonce concernee parmi ses annonces |
| Nom | Texte | Oui | Nom interne (ex: "Disponibilites semaine 12") |
| Duree d'un creneau | Select | Oui | 15 min, 20 min, 30 min, 45 min, 1h, 1h30, 2h |
| Tampon entre creneaux | Nombre | Non | Temps de pause entre 2 visites (0 a 60 min). Defaut : 0 |
| Date de debut | Date | Oui | Premiere date de disponibilite |
| Date de fin | Date | Non | Derniere date (laisser vide = indefini) |

**Recurrence (optionnel) :**

| Champ | Type | Condition | Description |
|-------|------|-----------|-------------|
| Recurrence | Toggle | — | Activer pour repeter les creneaux |
| Frequence | Select | Si recurrence activee | Quotidien, Hebdomadaire, Bi-hebdomadaire, Mensuel |
| Jours | Checkboxes | Si hebdomadaire ou bi-hebdomadaire | Lundi, Mardi, Mercredi, Jeudi, Vendredi, Samedi, Dimanche |

**Plages horaires (minimum 1) :**

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Heure de debut | Heure | Oui | Ex: 09:00 |
| Heure de fin | Heure | Oui | Ex: 12:00 |

Le bailleur peut ajouter plusieurs plages horaires (ex: 09:00-12:00 et 14:00-17:00).

### 7.2 Liste des disponibilites

| Colonne | Description |
|---------|-------------|
| Nom | Nom interne de la disponibilite |
| Annonce | Titre de l'annonce concernee |
| Type | Badge "Recurrent" ou "Ponctuel" |
| Plages | Nombre de plages horaires |
| Reservations actives | Nombre de reservations en attente ou confirmees |
| Creee le | Date de creation (cache par defaut) |

Filtres : par type (Ponctuel/Recurrent), par annonce.

### 7.3 Modification

Le bailleur peut modifier une disponibilite **uniquement si elle n'a pas de reservations actives**. Si des reservations existent, un message d'erreur s'affiche.

---

## 8. Demandes de visite

**Menu** : Visites > Demandes de visite

Les demandes de visite sont creees par les clients via l'API ou le frontend. Le bailleur les gere ici.

### 8.1 Liste des demandes

| Colonne | Description |
|---------|-------------|
| Date de visite | Date du creneau demande (format jj/mm/aaaa) |
| Horaire | Heure debut — Heure fin |
| Annonce | Titre de l'annonce (limite 30 caracteres) |
| Locataire | Prenom et nom du client |
| Statut | Badge : En attente (orange), Confirmee (vert), Annulee (rouge), Expiree (gris) |
| Expire | Temps restant avant expiration (format relatif) |

Filtres : par statut, par annonce.

Badge de navigation : nombre de demandes **en attente** (couleur orange).

### 8.2 Detail d'une demande (vue)

En cliquant sur une demande, un panneau lateral affiche :

**Creneau demande :**
- Date, heure de debut, heure de fin
- Statut
- Date d'expiration

**Locataire :**
- Prenom, nom
- Email
- Telephone
- Message du locataire (s'il en a laisse un)

**Annonce concernee :**
- Titre
- Loyer

**Notes bailleur :**
- Notes privees (visibles uniquement par le bailleur)

**Annulation** (si la demande a ete annulee) :
- Annule par : Client / Bailleur / Systeme
- Motif d'annulation

### 8.3 Actions du bailleur

| Action | Condition | Description |
|--------|-----------|-------------|
| Voir | Toujours | Ouvre le detail en panneau lateral |
| Confirmer | Statut = En attente | Confirme la visite. Le client recoit une notification |
| Notes | Toujours | Ajouter/modifier des notes privees |
| Annuler | Statut = En attente ou Confirmee | Annule avec motif optionnel. Le client recoit un email et une notification |

### 8.4 Flux de reservation

1. Le **client** consulte les creneaux disponibles sur le frontend
2. Le **client** reserve un creneau (il doit avoir debloque l'annonce)
3. Une **demande** est creee avec le statut "En attente" et une expiration de 24h
4. Le **bailleur** recoit une notification
5. Le **bailleur** confirme ou annule la demande
6. Si aucune action sous 24h, la demande **expire automatiquement** et le creneau est libere

---

## 9. Contrats de bail

### 9.1 Generer un contrat

**Depuis** : Mes Annonces > action "Contrat de bail" sur une annonce (statut Disponible ou Reserve)

Le formulaire de generation :

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| Reference du logement | Texte | Non | Numero de chambre, local, appartement. Ex: "Chambre 12", "Local B3", "Appartement 2A". Utile pour les cites ou locaux commerciaux |
| Nom complet du locataire | Texte | Oui | Nom et prenom du locataire |
| Telephone du locataire | Telephone | Oui | Numero de telephone |
| Email du locataire | Email | Non | Adresse email |
| N° CNI / Passeport | Texte | Non | Numero de piece d'identite |
| Date de debut du bail | Date | Oui | Defaut : aujourd'hui |
| Duree du bail | Select | Oui | 6 mois, 12 mois (1 an), 24 mois (2 ans), 36 mois (3 ans). Defaut : 12 mois |
| Conditions particulieres | Zone de texte | Non | Clauses supplementaires |

Les informations de l'annonce sont **automatiquement pre-remplies** dans le PDF :
- Nom et coordonnees du bailleur
- Designation du bien (titre, type, adresse, quartier, ville)
- Reference du logement (si renseignee)
- Caracteristiques (chambres, SDB, surface)
- Conditions financieres (loyer, depot, charges)

### 9.2 Contenu du PDF genere

Le contrat PDF contient :

1. **En-tete** : "CONTRAT DE BAIL D'HABITATION" + reference (ex: KH-A1B2C3D4-20260314)
2. **Article 1 — Les Parties** : coordonnees du bailleur et du locataire
3. **Article 2 — Designation du bien** : toutes les caracteristiques du logement
4. **Article 3 — Duree du bail** : dates de debut et fin, clause de reconduction tacite
5. **Article 4 — Conditions financieres** : tableau avec loyer, depot, charges detaillees
6. **Article 5 — Obligations du bailleur** : delivrance, jouissance paisible, reparations, quittances
7. **Article 6 — Obligations du locataire** : paiement, usage, degradations, sous-location, restitution
8. **Article 7 — Resiliation** : conditions de resiliation, preavis de 3 mois
9. **Article 8 — Conditions particulieres** (si renseignees)
10. **Signatures** : espaces pour bailleur et locataire
11. **Pied de page** : mention KeyHome + date de generation

### 9.3 Retrouver ses contrats

**Menu** : Mes Biens > Mes Contrats

Tous les contrats generes sont sauvegardes et accessibles :

| Colonne | Description |
|---------|-------------|
| Reference | Numero unique du contrat (copiable) |
| Annonce | Titre de l'annonce associee |
| Locataire | Nom du locataire |
| Loyer | Montant mensuel en FCFA |
| Debut | Date de debut du bail |
| Fin | Date de fin du bail |
| Cree le | Date de generation (cache par defaut) |

**Actions :**

| Action | Description |
|--------|-------------|
| Voir | Affiche le detail complet (reference, locataire, conditions, etc.) |
| Telecharger | Re-telecharge le PDF du contrat |

---

## 10. Avis clients

**Menu** : Retours > Avis clients

Les clients laissent des avis sur les annonces du bailleur via l'API ou le frontend. Le bailleur peut les consulter ici (lecture seule).

### 10.1 Liste des avis

Les avis sont groupes par annonce par defaut. Regroupement alternatif : par note.

| Colonne | Description |
|---------|-------------|
| Note | Affichage en etoiles (1 a 5). Couleur : vert (4-5), orange (3), rouge (1-2) |
| Client | Nom complet du client |
| Date | Date relative (ex: "il y a 3 jours") avec tooltip date exacte |

Filtre : par note (Excellent 5, Tres bien 4, Bien 3, Moyen 2, Mauvais 1).

Badge de navigation : nombre total d'avis recus.

### 10.2 Detail d'un avis

**Avis du client :**
- Note (etoiles + couleur)
- Date de publication
- Commentaire (si present)

**Client :**
- Nom complet
- Email (copiable)

**Annonce concernee :**
- Titre
- Prix

### 10.3 Regles

- Un client peut laisser **un seul avis par annonce**
- L'avis contient une note (1 a 5) et un commentaire optionnel (max 1000 caracteres)
- Le bailleur **ne peut pas supprimer** les avis

---

## 11. Notifications

Les notifications sont accessibles via l'**icone cloche** en haut a droite de l'interface.

Le bailleur recoit des notifications pour :
- Nouvelle demande de visite
- Confirmation/annulation de visite
- Moderation d'annonce (approbation, refus)
- Autres evenements systeme

Les notifications sont stockees en base de donnees et mises a jour automatiquement toutes les 30 secondes.

---

## Resume de la navigation

```
Tableau de bord
│
├── Mes Biens
│   ├── Mes Annonces (creation, modification, contrat, visite 3D)
│   └── Mes Contrats (historique des contrats generes)
│
├── Retours
│   └── Avis clients (lecture seule)
│
└── Visites
    ├── Demandes de visite (confirmer, annuler, notes)
    └── Mes disponibilites (creneaux de visite)

Profil (icone en haut a droite)
├── Photo de profil
├── Informations personnelles
├── Contact
└── Securite

Notifications (icone cloche en haut a droite)
```
