[![ImmoApp-Backend CI](https://github.com/NeoCraftTeam/ImmoApp-Backend/actions/workflows/main.yml/badge.svg)](https://github.com/NeoCraftTeam/ImmoApp-Backend/actions/workflows/main.yml)

# 📘 Cahier des Charges – Application de Gestion Immobilière

## 📋 Table des Matières

- [1. Introduction](#1-introduction)
- [2. Objectifs du projet](#2-objectifs-du-projet)
- [3. Acteurs du projet](#3-acteurs-du-projet)
- [4. Périmètre fonctionnel](#4-périmètre-fonctionnel)
  - [4.1 Pour les utilisateurs (clients)](#41-pour-les-utilisateurs-clients)
  - [4.2 Pour les bailleurs / agents](#42-pour-les-bailleurs--agents)
  - [4.3 Pour l'administrateur](#43-pour-ladministrateur)
- [5. Technologies utilisées](#5-technologies-utilisées)
- [6. Modèle économique & monétisation](#6-modèle-économique--monétisation)
- [7. Besoins fonctionnels](#7-besoins-fonctionnels)
- [8. Besoins non fonctionnels](#8-besoins-non-fonctionnels)
- [9. Ergonomie & Design](#9-ergonomie--design)
- [10. Planification (7 mois)](#10-planification-7-mois)
- [11. Livrables attendus](#11-livrables-attendus)
- [12. Table des dependances](#12-table-des-dependances)

---

## 1. Introduction

L'application de gestion immobilière vise à digitaliser et fluidifier la recherche, la publication et la gestion des logements à louer. Elle s'adresse aux particuliers, bailleurs et agents immobiliers. Le projet prévoit une plateforme Web ainsi qu'une application mobile Android.

## 2. Objectifs du projet

- Fournir une **interface intuitive** Web et mobile de recherche et publication d'annonces immobilières.
- Permettre un accès **géolocalisé** aux logements sur carte.
- Proposer un **système sécurisé de paiement mobile (MoMo / Orange Money)** basé sur l'accès aux annonces.
- Créer un lien rapide entre bailleurs, agents et clients potentiels.

## 3. Acteurs du projet

- **Clients (utilisateurs à la recherche de logements)**
- **Bailleurs/Agents immobiliers**
- **Administrateur de la plateforme**

## 4. Périmètre fonctionnel

### 4.1 Pour les utilisateurs (clients)

- Création de compte et connexion
- Navigation sur les logements par type, localisation, prix
- Géolocalisation des logements
- Visualisation **partielle** des annonces (infos masquées)
- Paiement de 200 FCFA pour débloquer une annonce (coordonnées, adresse exacte, numéro agent)
- Historique des annonces déverrouillées
- Notifications sur nouvelles annonces (en option)

### 4.2 Pour les bailleurs / agents

- Inscription / Connexion
- Publication d'annonces avec photos, vidéos, descriptifs, prix, type, localisation GPS
- Statistiques de vues, clics, déverrouillages
- Gestion du portefeuille d'annonces
- Boost d'annonce (option payante , en option)

### 4.3 Pour l'administrateur

- Tableau de bord global
- Gestion utilisateurs (clients et bailleurs)
- Modération des annonces
- Suivi des paiements et statistiques financières

## 5. Technologies utilisées

| Composant | Technologie |
|----------|-------------|
| Frontend Web | Vue.js 3, Bootstrap 5, Pinia,  Toastr
| Backend | Laravel 12 |
| Base de données | PostgreSQL | 
| Application Mobile | Flutter avec Nylo |
| Géolocalisation | Google Maps / OpenStreetMap, Leaflet
| Paiement | API Mobile Money / Orange Money |
| Notifications | Email avec Resend (Plus tard avec amazon SES pour l'envoi en masse)

## 6. Modèle économique & monétisation

- **200 FCFA** pour débloquer une annonce unique.
- **Boost pour bailleurs** : mise en avant d'une annonce (5 000 FCFA/an).

## 7. Besoins fonctionnels

- Authentification JWT + oAuth (Laravel Passport) 
- Interface responsive Web + mobile
- Carte interactive avec filtres avancés (Localisation, type, prix, nombre de pieces)
- Interface publication d'annonce complète
- Paiement intégré et gestion des crédits
- Historiques des annonces
- Statistiques et dashboards
- Modération des annonces (admin)

## 8. Besoins non fonctionnels

- Performance : chargement rapide, pagination, cache
- Sécurité : HTTPS, CSRF, validation serveur, sanitisation des données, protection API
- Multilingue : FR (EN à venir)
- Disponibilité 24h/24
- Système de logs et sauvegardes

## 9. Ergonomie & Design

- UI moderne, responsive, fluide avec les icones intuitives(lucidevue)
- Carte interactive claire et intuitive
- Boutons clairs, feedback utilisateur visible
- Utilisation cohérente de la charte graphique NeoCraft
- Design mobile-first


## 10. Planification (7 mois)

| Étape | Durée | Livrables |
|-------|--------|-----------|
| Analyse & Conception | 2 semaines | Maquettes, schéma BDD, specs techniques |
| Backend & API REST | 2 mois | Auth, CRUD, paiement, sécurité |
| Frontend Web | 1.5 mois | Interface utilisateur + carte |
| Application mobile | 1.5 mois | App Flutter + MoMo |
| Paiement & Géolocalisation | 1 mois | Intégration APIs |
| Tests & validation | 2 semaines | QA, correctifs |
| Déploiement & formation | 1 semaine | Mise en ligne + guide utilisateur |

## 11. Livrables attendus

- Application Web fonctionnelle (Vue + Laravel)
- Application mobile Flutter (APK + source)
- Documentation technique & utilisateur
- Manuel d'installation & déploiement

---

## 12. Table des dependances

| Étape  | Tache                      | Dépendance |
| -----  | -------------------------- |---|
| A      | Analyse & Conception       | - |
| B      | Backend & API REST         | A |
| C      | Frontend Web               | B |
| D      | Application mobile         | B |
| E      | Paiement & Géolocalisation | C, D |
| F      | Tests & validation         | E |
| G      | Déploiement & formation    | F |

---

**© 2025 NeoCraftTeam - Application de Gestion Immobilière**



