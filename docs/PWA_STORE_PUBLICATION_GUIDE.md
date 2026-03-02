# Guide complet — Publier KeyHome PWA sur Google Play Store & Apple App Store

> **Application** : KeyHome - Gestion Immobilière  
> **Type** : PWA (Progressive Web App) — Panel admin Filament  
> **Domaine de production** : `https://keyhome.cm` (à adapter)  
> **Date** : Mars 2026

---

## Table des matières

1. [Prérequis communs](#1-prérequis-communs)
2. [Préparer les assets graphiques](#2-préparer-les-assets-graphiques)
3. [Google Play Store (TWA)](#3-google-play-store-twa)
4. [Apple App Store (WKWebView wrapper)](#4-apple-app-store-wkwebview-wrapper)
5. [Textes de description & Fiche store](#5-textes-de-description--fiche-store)
6. [Informations développeur](#6-informations-développeur)
7. [Post-publication & Mises à jour](#7-post-publication--mises-à-jour)
8. [Checklist finale](#8-checklist-finale)

---

## 1. Prérequis communs

### 1.1 PWA opérationnelle

Votre PWA doit être en production sur HTTPS avec :

| Élément | État actuel | Requis |
|---------|------------|--------|
| `manifest.json` valide | ✅ Présent | Obligatoire |
| Service Worker avec offline | ✅ `sw.js` (379 lignes) | Obligatoire |
| HTTPS | ✅ (prod) | Obligatoire |
| Icônes 512×512 | ✅ `icon-512x512.png` + `maskable-512x512.png` | Obligatoire |
| `display: standalone` | ✅ | Obligatoire |
| `start_url` défini | ✅ `/admin` | Obligatoire |
| Digital Asset Links | ✅ `public/.well-known/assetlinks.json` | Play Store uniquement |

### 1.2 Vérifier le manifest

```bash
# Outil en ligne
https://pwabuilder.com  → coller l'URL de prod → rapport automatique

# Lighthouse dans Chrome DevTools
F12 → Lighthouse → PWA → Generate report
```

### 1.3 Comptes développeur

| Store | Coût | URL d'inscription |
|-------|------|-------------------|
| Google Play | **25 $ (une fois)** | https://play.google.com/console/signup |
| Apple App Store | **99 $/an** | https://developer.apple.com/programs/enroll |

> **Important** : L'inscription Apple nécessite un Apple ID, et pour un compte **Organisation**, un numéro D-U-N-S (gratuit, 2-4 semaines d'obtention). Pour un compte **Individuel**, une pièce d'identité suffit.

---

## 2. Préparer les assets graphiques

### 2.1 Icône de l'application

L'icône est le visuel le plus important. Elle apparaît sur l'écran d'accueil, dans les stores, et dans les résultats de recherche.

#### Fichiers source à préparer

| Usage | Taille | Format | Fichier actuel |
|-------|--------|--------|----------------|
| Icône standard | 512×512 px | PNG (RGBA) | `icon-512x512.png` ✅ |
| Icône maskable (Android) | 512×512 px | PNG, zone safe 80% | `maskable-512x512.png` ✅ |
| Haute résolution Play Store | **1024×1024 px** | PNG 32-bit (RGBA) | ❌ **À créer** |
| App Store iOS | **1024×1024 px** | PNG, **sans transparence**, **sans coins arrondis** | ❌ **À créer** |

#### Comment créer l'icône 1024×1024

```
Option A — Figma / Canva / Photoshop
→ Re-exporter votre logo KeyHome en 1024×1024

Option B — Depuis l'icône existante (upscale)
→ Utiliser un outil comme https://www.iloveimg.com/resize-image
→ Upscaler icon-512x512.png → 1024×1024
⚠️ Risque de flou — préférez re-exporter depuis la source vectorielle

Option C — Outil en ligne PWA
→ https://maskable.app/editor → télécharger l'icône source → exporter en 1024×1024
```

> **Règles Apple** : Pas de transparence (fond blanc/couleur solide), pas de coins arrondis (iOS les ajoute automatiquement), pas de texte "beta" ou "test".

> **Règle Android maskable** : Le contenu important doit être dans les **80% centraux** (la zone safe). Les bords extérieurs (10% de chaque côté) peuvent être coupés par le masque adaptatif.

#### Vérifier votre icône maskable

```
https://maskable.app → Upload maskable-512x512.png → vérifier le rendu avec différents masques
```

### 2.2 Screenshots (captures d'écran)

Les screenshots sont **obligatoires** pour les deux stores.

#### Google Play Store

| Type | Dimensions recommandées | Quantité |
|------|------------------------|----------|
| Téléphone | 1080×1920 px (9:16) | **2 minimum**, 8 max |
| Tablette 7" | 1200×1920 px | Optionnel (recommandé) |
| Tablette 10" | 1920×1200 px | Optionnel |

#### Apple App Store

| Appareil | Dimensions exactes | Quantité |
|----------|-------------------|----------|
| iPhone 6.9" (iPhone 16 Pro Max) | 1320×2868 px | **2 minimum**, 10 max |
| iPhone 6.7" (iPhone 15 Pro Max) | 1290×2796 px | **2 minimum**, 10 max |
| iPhone 6.5" (iPhone 11 Pro Max) | 1284×2778 px | **2 minimum**, 10 max |
| iPhone 5.5" (iPhone 8 Plus) | 1242×2208 px | Optionnel |
| iPad Pro 12.9" (6th gen) | 2048×2732 px | **Requis si app iPad** |

> **Astuce** : Créez les screenshots pour la plus grande taille (6.9") et App Store Connect vous permet de les réutiliser pour les tailles inférieures.

#### Comment capturer les screenshots

```bash
# Méthode 1 — Chrome DevTools
1. Ouvrir votre app en prod : https://keyhome.cm/admin
2. F12 → Toggle Device Toolbar (Ctrl+Shift+M)
3. Sélectionner un appareil (iPhone 14 Pro Max, Pixel 7, etc.)
4. Capturer : Ctrl+Shift+P → "Capture full size screenshot"

# Méthode 2 — Outils de mise en scène (mockups)
→ https://shots.so — Encadre vos screenshots dans un mockup de téléphone
→ https://previewed.app — Mockups 3D
→ https://www.figma.com — Templates App Store gratuits

# Méthode 3 — Simulateur iOS (si vous avez un Mac)
1. Xcode → Simulateur → Votre app
2. Cmd+S pour capturer
```

#### Screenshots recommandés pour KeyHome

Préparez ces 5-6 écrans :

1. **Écran de connexion** — Montre la sécurité (MFA)
2. **Tableau de bord** — Vue d'ensemble avec KPIs
3. **Liste des annonces** — Gestion des propriétés
4. **Détail d'une annonce** — Vue complète avec photos
5. **Notifications push** — Montrer la notification reçue
6. **Vue mobile responsive** — Prouver que c'est utilisable sur mobile

### 2.3 Feature Graphic (Play Store uniquement)

| Asset | Dimensions | Format |
|-------|-----------|--------|
| Feature Graphic | **1024×500 px** | PNG ou JPEG |

C'est la bannière qui apparaît en haut de votre fiche Play Store. Elle doit contenir :
- Le logo KeyHome
- Un tagline court ("Gestion immobilière simplifiée")
- Le fond aux couleurs de la marque (`#F6475F`)

---

## 3. Google Play Store (TWA)

### 3.1 Générer le package Android avec PWABuilder

```
1. Aller sur https://www.pwabuilder.com
2. Coller l'URL de production : https://keyhome.cm/admin
3. Attendre l'analyse → corriger les avertissements éventuels
4. Cliquer "Package for stores" → "Android"
5. Configurer :
```

#### Configuration PWABuilder pour Android

| Champ | Valeur |
|-------|--------|
| **Package ID** | `cm.keyhome.admin` |
| **App name** | `KeyHome - Gestion Immobilière` |
| **Short name** | `KeyHome` |
| **App version** | `1.0.0` |
| **App version code** | `1` |
| **Host** | `keyhome.cm` |
| **Start URL** | `/admin` |
| **Theme color** | `#F6475F` |
| **Background color** | `#F6475F` |
| **Status bar color** | `#F6475F` |
| **Splash screen fade out** | `300ms` |
| **Icon** | Upload `icon-512x512.png` |
| **Maskable icon** | Upload `maskable-512x512.png` |
| **Notification delegation** | ✅ **Activé** (pour les push notifications) |
| **Signing key** | "New" → laisser PWABuilder générer |

> **⚠️ CONSERVEZ LE FICHIER DE SIGNING KEY (.keystore)** — Vous en aurez besoin pour chaque mise à jour. Perdre cette clé = impossible de mettre à jour l'app.

6. Cliquer **"Generate"** → Télécharger le fichier `.zip`
7. Extraire → vous obtenez un `.aab` (Android App Bundle)

### 3.2 Configurer Digital Asset Links

PWABuilder vous donne le **SHA-256 fingerprint** de votre signing key. Mettez à jour le fichier :

**Fichier** : `public/.well-known/assetlinks.json`

```json
[
    {
        "relation": [
            "delegate_permission/common.handle_all_urls"
        ],
        "target": {
            "namespace": "android_app",
            "package_name": "cm.keyhome.admin",
            "sha256_cert_fingerprints": [
                "AB:CD:EF:12:34:... (le SHA-256 fourni par PWABuilder)"
            ]
        }
    }
]
```

**Déployer ce fichier en production AVANT de soumettre l'app.**

Vérifier :
```bash
curl -s https://keyhome.cm/.well-known/assetlinks.json | python3 -m json.tool
# Doit retourner le JSON ci-dessus

# Outil de vérification Google :
https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://keyhome.cm&relation=delegate_permission/common.handle_all_urls
```

### 3.3 Mettre à jour le manifest.json

Remplacer les placeholders dans `public/manifest.json` :

```json
"related_applications": [
    {
        "platform": "play",
        "url": "https://play.google.com/store/apps/details?id=cm.keyhome.admin",
        "id": "cm.keyhome.admin"
    }
]
```

### 3.4 Publier sur Google Play Console

```
1. Aller sur https://play.google.com/console
2. "Créer une application"
3. Remplir les infos de base (voir section 5)
4. Aller dans "Production" → "Créer une release"
5. Uploader le fichier .aab
6. Remplir les notes de version
7. Soumettre pour review
```

#### Canaux de distribution recommandés

| Canal | Usage | Visibilité |
|-------|-------|-----------|
| **Test interne** | Tester avec l'équipe (max 100 testeurs) | Privé |
| **Test fermé (alpha)** | Beta avec des utilisateurs choisis | Sur invitation |
| **Test ouvert (beta)** | Beta publique | Visible sur le store |
| **Production** | Release finale | Public |

> **Recommandation pour un admin panel** : Utilisez le **test interne** ou **test fermé**. Un panel admin n'a pas besoin d'être public sur le Play Store.

### 3.5 Formulaire de contenu Play Store

Google exige de remplir un questionnaire de classement :

| Question | Réponse pour KeyHome |
|----------|---------------------|
| Contenu violent ? | Non |
| Contenu sexuel ? | Non |
| Langage vulgaire ? | Non |
| Substances contrôlées ? | Non |
| Données personnelles collectées ? | Oui (email, nom, téléphone) |
| App destinée aux enfants ? | Non |
| Contient des publicités ? | Non |
| App gouvernementale ? | Non |

---

## 4. Apple App Store (WKWebView Wrapper)

### 4.1 Pourquoi c'est différent d'Android

Apple **ne supporte pas TWA**. Pour publier une PWA sur iOS, vous devez créer un **wrapper natif Swift** utilisant `WKWebView`. Les options :

| Méthode | Complexité | Push Notifications |
|---------|-----------|-------------------|
| **PWABuilder** (génère un projet Xcode) | Facile | ⚠️ Limité (Web Push supporté depuis iOS 16.4+) |
| **Capacitor** (Ionic) | Moyen | ✅ Via plugin natif |
| **Wrapper Swift manuel** | Avancé | ✅ Full control |

### 4.2 Méthode recommandée : PWABuilder pour iOS

```
1. Aller sur https://www.pwabuilder.com
2. Coller l'URL : https://keyhome.cm/admin
3. Cliquer "Package for stores" → "iOS"
4. Télécharger le projet Xcode généré
```

### 4.3 Ouvrir et configurer dans Xcode

```
1. Extraire le .zip téléchargé
2. Ouvrir le fichier .xcodeproj dans Xcode
3. Configurer :
   - Bundle Identifier : cm.keyhome.admin
   - Team : Votre Apple Developer Team
   - Deployment Target : iOS 16.4+ (pour Web Push)
   - Device orientation : Portrait (principal)
4. Icônes : Xcode → Assets.xcassets → AppIcon
   → Glisser-déposer l'icône 1024×1024 (Xcode génère les tailles automatiquement)
```

### 4.4 Compiler et soumettre

```bash
# Dans Xcode :
1. Product → Archive
2. Window → Organizer → Distribute App
3. Choisir "App Store Connect"
4. Upload

# OU via command line :
xcodebuild -exportArchive \
  -archivePath KeyHome.xcarchive \
  -exportPath ./export \
  -exportOptionsPlist exportOptions.plist
```

### 4.5 App Store Connect

```
1. Aller sur https://appstoreconnect.apple.com
2. "Mes apps" → "+" → "Nouvelle app"
3. Remplir :
   - Nom : KeyHome - Gestion Immobilière
   - SKU : cm.keyhome.admin
   - Bundle ID : cm.keyhome.admin (celui configuré dans Xcode)
   - Langue principale : Français
4. Uploader les screenshots (voir section 2.2)
5. Remplir la description (voir section 5)
6. Soumettre pour review
```

### 4.6 Particularités Apple

| Contrainte | Impact |
|-----------|--------|
| **Web Push iOS** | Supporté depuis iOS/iPadOS 16.4+ et Safari 16.4+. L'utilisateur doit "Ajouter à l'écran" pour que les push fonctionnent. Dans un wrapper WKWebView, ça fonctionne nativement. |
| **Review plus stricte** | Apple peut rejeter une app "wrapper web" si elle n'apporte pas de valeur ajoutée par rapport au site web. Argument : push natif + accès offline + expérience immersive. |
| **Pas de moteur tiers** | Sur iOS, WKWebView utilise WebKit (pas Chrome). Testez bien le rendu. |
| **Politique 4.2** | "Minimum Functionality" — votre admin panel avec CRUD, dashboards, et push est suffisamment riche. |

---

## 5. Textes de description & Fiche store

### 5.1 Fiche Google Play Store

```
📌 Titre (max 30 caractères) :
KeyHome Admin

📌 Description courte (max 80 caractères) :
Gérez vos annonces immobilières, agences et transactions en temps réel.

📌 Description complète (max 4000 caractères) :

KeyHome est la plateforme de gestion immobilière tout-en-un pour les administrateurs et gestionnaires de biens.

🏠 GESTION DES ANNONCES
• Validez, modifiez et publiez les annonces immobilières
• Suivez le statut de chaque propriété en temps réel
• Recevez des notifications instantanées pour les nouvelles soumissions

👥 GESTION DES UTILISATEURS
• Administrez les comptes bailleurs, agents et agences
• Gérez les abonnements et les crédits
• Consultez l'historique des activités

📊 TABLEAU DE BORD
• Visualisez les KPIs en un coup d'œil
• Suivez les paiements et transactions
• Analysez les performances des annonces

🔔 NOTIFICATIONS EN TEMPS RÉEL
• Push notifications pour les actions importantes
• Alertes pour les nouvelles annonces en attente
• Notifications de paiements et d'abonnements

🔒 SÉCURITÉ
• Authentification multi-facteurs (MFA)
• Sessions sécurisées
• Journalisation complète des actions admin

📱 EXPÉRIENCE MOBILE OPTIMISÉE
• Interface responsive adaptée à tous les écrans
• Mode hors-ligne pour les fonctionnalités essentielles
• Performances optimisées

Développé par NeoCraft pour les professionnels de l'immobilier au Cameroun et en Afrique.

📌 Catégorie : Professionnel / Productivité
📌 Classification : PEGI 3 / Tout public
```

### 5.2 Fiche Apple App Store

```
📌 Nom (max 30 caractères) :
KeyHome Admin

📌 Sous-titre (max 30 caractères) :
Gestion Immobilière Pro

📌 Mots-clés (max 100 caractères, séparés par des virgules) :
immobilier,gestion,annonces,agence,admin,propriété,location,cameroun,afrique

📌 Description (max 4000 caractères) :
(Même texte que Play Store ci-dessus)

📌 Texte promotionnel (max 170 caractères) — modifiable sans review :
Gérez vos annonces immobilières depuis votre mobile. Notifications push, tableau de bord, et gestion complète en temps réel.

📌 URL de support :
https://keyhome.cm/support

📌 URL de politique de confidentialité :
https://keyhome.cm/privacy (OBLIGATOIRE)

📌 URL marketing :
https://keyhome.cm
```

### 5.3 Notes de version (première release)

```
Version 1.0.0

Première version de KeyHome Admin pour mobile !

• Tableau de bord avec indicateurs clés
• Gestion complète des annonces (validation, modification, suppression)
• Notifications push en temps réel
• Gestion des utilisateurs et des agences
• Suivi des paiements et abonnements
• Authentification sécurisée avec MFA
• Mode hors-ligne
```

---

## 6. Informations développeur

### 6.1 Google Play Store

| Champ | Valeur à remplir |
|-------|-----------------|
| **Nom du développeur** | `NeoCraft` (ou votre nom/entreprise) |
| **Email de contact** | `contact@keyhome.cm` (email public, visible par les utilisateurs) |
| **Numéro de téléphone** | Numéro professionnel (non visible publiquement) |
| **Site web** | `https://keyhome.cm` |
| **Adresse physique** | Obligatoire pour les comptes Organization (adresse de l'entreprise) |
| **Politique de confidentialité URL** | `https://keyhome.cm/privacy` |

### 6.2 Apple App Store

| Champ | Valeur à remplir |
|-------|-----------------|
| **Nom de l'éditeur** | `NeoCraft` (ou nom légal de l'entreprise) |
| **Email App Store** | `appstore@keyhome.cm` |
| **Téléphone** | Numéro pour l'équipe de review Apple |
| **URL de support** | `https://keyhome.cm/support` |
| **URL marketing** | `https://keyhome.cm` |
| **URL politique de confidentialité** | `https://keyhome.cm/privacy` (**OBLIGATOIRE**) |

### 6.3 Apple — Privacy Nutrition Labels

Apple exige de déclarer les données collectées. Pour KeyHome :

| Type de donnée | Collectée | Usage |
|---------------|-----------|-------|
| Nom | ✅ | Fonctionnalité de l'app |
| Email | ✅ | Fonctionnalité + Authentification |
| Numéro de téléphone | ✅ | Fonctionnalité (contacts annonces) |
| Adresse | ✅ | Fonctionnalité (localisation propriétés) |
| Photos | ✅ | Fonctionnalité (images annonces) |
| Données de paiement | ✅ | Fonctionnalité |
| Identifiants | ✅ | Authentification |
| Données d'utilisation | ✅ | Analytique (Sentry, logs) |
| Diagnostics | ✅ | Analytique (Sentry) |

---

## 7. Post-publication & Mises à jour

### 7.1 Avantage majeur du TWA / PWA wrapper

**Les mises à jour du contenu sont instantanées.** Quand vous déployez une nouvelle version du backend Laravel / Filament, tous les utilisateurs voient les changements immédiatement sans passer par une mise à jour du store.

Vous devez republier sur le store **uniquement** si vous changez :
- L'icône de l'app
- Les métadonnées du manifest (nom, couleurs, scope)
- La signing key
- Le comportement natif (splash screen, orientation)

### 7.2 Mettre à jour le package Android

```bash
# Si nécessaire :
1. Aller sur PWABuilder → re-générer avec le même keystore
2. Incrémenter le version code (2, 3, 4...)
3. Re-uploader le .aab sur Play Console
4. Soumettre pour review (plus rapide après la 1ère fois)
```

### 7.3 Monitoring

- **Google Play Console** → Android Vitals (crashes, ANR)
- **App Store Connect** → Metrics (crashes, performance)  
- **Sentry** (déjà intégré dans votre app) → erreurs JS/réseau

---

## 8. Checklist finale

### Assets à préparer AVANT la soumission

| Asset | Play Store | App Store | Statut |
|-------|-----------|-----------|--------|
| Icône 512×512 | ✅ | ✅ | ✅ Existe |
| Icône 1024×1024 | ✅ (haute res) | ✅ (obligatoire) | ❌ **À créer** |
| Icône maskable 512×512 | ✅ | N/A | ✅ Existe |
| Screenshots mobile (≥2) | ✅ | ✅ | ⚠️ 1 seul actuellement |
| Screenshots tablette | Recommandé | Requis si iPad | ❌ **À créer** |
| Feature graphic 1024×500 | ✅ | N/A | ❌ **À créer** |
| Description courte | ✅ | ✅ (sous-titre) | ❌ **À rédiger** |
| Description complète | ✅ | ✅ | ❌ **À rédiger** |
| Politique de confidentialité | ✅ | ✅ (obligatoire) | ❌ **À créer/publier** |
| Page support | Optionnel | Recommandé | ❌ **À créer** |
| `assetlinks.json` en prod | ✅ | N/A | ⚠️ Placeholder |
| Signing keystore (.jks) | ✅ | N/A | ❌ **Sera généré** |
| Certificat Apple | N/A | ✅ | ❌ **À configurer** |

### Ordre des opérations

```
GOOGLE PLAY :
1. □ Créer l'icône 1024×1024
2. □ Créer le feature graphic 1024×500
3. □ Capturer 4-6 screenshots mobile (1080×1920)
4. □ Rédiger les descriptions (proposées dans la section 5)
5. □ Publier la politique de confidentialité sur keyhome.cm/privacy
6. □ S'inscrire sur Google Play Console (25$)
7. □ Générer le .aab avec PWABuilder
8. □ Mettre à jour assetlinks.json avec le vrai SHA-256
9. □ Déployer assetlinks.json en production
10. □ Créer l'app sur Play Console
11. □ Uploader le .aab
12. □ Remplir le formulaire de contenu
13. □ Ajouter les testeurs (test interne)
14. □ Soumettre pour review

APPLE APP STORE :
1. □ S'inscrire au Apple Developer Program (99$/an)
2. □ Créer l'icône 1024×1024 sans transparence
3. □ Capturer les screenshots aux dimensions iOS requises
4. □ Générer le projet Xcode via PWABuilder
5. □ Configurer le Bundle ID et la Team dans Xcode
6. □ Archiver et uploader via Xcode
7. □ Configurer la fiche sur App Store Connect
8. □ Remplir les Privacy Nutrition Labels
9. □ Soumettre pour review
```

---

## Annexe : Commandes utiles

```bash
# Vérifier les Digital Asset Links
curl -s https://keyhome.cm/.well-known/assetlinks.json | python3 -m json.tool

# Vérifier le manifest
curl -s https://keyhome.cm/manifest.json | python3 -m json.tool

# Obtenir le SHA-256 d'un keystore existant
keytool -list -v -keystore your-keystore.jks -alias your-alias

# Vérifier le service worker
# Chrome → F12 → Application → Service Workers

# Tester l'app TWA sans publier (debug Android)
# Installer l'APK de debug généré par PWABuilder sur un appareil Android
adb install app-debug.apk
```
