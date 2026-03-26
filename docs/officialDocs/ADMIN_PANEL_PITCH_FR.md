# KeyHome — Le panneau d'administration

> *Derrière chaque grande plateforme, il y a un centre de contrôle qui la fait vivre. Voici le vôtre.*

---

## Pourquoi un panneau d'administration aussi complet ?

Une plateforme immobilière, ce n'est pas juste un catalogue d'annonces. C'est un écosystème vivant : des milliers d'utilisateurs qui s'inscrivent, des bailleurs qui publient, des locataires qui cherchent, des paiements qui transitent, des avis qui s'accumulent, des signalements qui remontent, des emails qui partent, des jobs qui tournent en arrière-plan. Tout ça, ça demande un pilote.

Le panneau d'administration de KeyHome a été conçu pour que **l'équipe qui gère la plateforme ait une visibilité totale, un contrôle absolu et des outils dignes d'une startup tech moderne** — sans avoir besoin de toucher une seule ligne de code pour les opérations du quotidien. C'est construit sur **Filament 4**, l'un des frameworks d'administration PHP les plus puissants du moment, avec une interface claire, réactive, et accessible depuis n'importe quel appareil.

Voici, section par section, tout ce que le panneau d'administration de KeyHome permet de faire.

---

## 1. Le tableau de bord — Le poste de commande central

Le tableau de bord est la première page que voit un administrateur quand il se connecte. En un seul coup d'œil, il a une photographie complète de l'état de santé de la plateforme. Pas de données brutes incompréhensibles — des métriques organisées, contextualisées, et visuellement claires, regroupées par thème.

---

### 1.1 Les indicateurs globaux (KPIs)

La première rangée affiche les chiffres les plus importants de la plateforme, chacun accompagné d'une tendance graphique sur les 7 derniers mois :

- **Nouveaux utilisateurs ce mois** — le nombre de personnes qui ont créé un compte. Un graphique en miniature montre si l'acquisition est en hausse ou en baisse.
- **Note moyenne globale** — la satisfaction sur 5 étoiles, calculée sur tous les avis reçus. Le pouls de la réputation de KeyHome.
- **Nombre total d'avis** — le volume de feedbacks laissés par la communauté.
- **Revenus totaux (FCFA)** — tout l'argent généré par la plateforme depuis son lancement.
- **Agences partenaires** — le nombre d'agences immobilières actives sur KeyHome.
- **Annonces actives** — le catalogue vivant en ce moment.
- **Prix moyen des annonces** — la tendance du marché, mois après mois.
- **Annonces en attente de modération** — un chiffre rouge si des annonces attendent une validation. Un clic direct sur ce chiffre amène l'administrateur à la file de modération.

Toutes ces données sont **mises en cache pour 5 minutes** afin d'être servies instantanément, même si la base de données grossit.

---

### 1.2 L'alerte de modération urgente

Juste sous les KPIs globaux, un widget dédié apparaît **uniquement quand des annonces attendent d'être validées**. Il affiche le nombre exact d'annonces en attente, en rouge, avec une icône d'alerte, et un lien direct vers la file de modération. Si tout est traité, ce widget disparaît — pas de bruit inutile quand tout va bien.

---

### 1.3 Acquisition — Comment les gens découvrent KeyHome

Cette section répond à une question fondamentale pour toute équipe marketing : d'où viennent nos utilisateurs ?

- **Visiteurs uniques** — le nombre de personnes différentes qui ont visité le site ces 30 derniers jours.
- **Principale source de trafic** — Google organique ? Réseaux sociaux ? Publicité payante ? Accès direct ? Le canal qui ramène le plus de monde est mis en avant avec son volume.
- **Taux de conversion visiteur → inscription** — quel pourcentage des visiteurs finit par créer un compte. Un indicateur clé pour évaluer l'efficacité de la landing page.
- **Nouvelles inscriptions (30 jours)** — le nombre brut de comptes créés.
- **Canal d'inscription le plus fréquent** — en croisant les données UTM et les sessions, on identifie quel canal d'acquisition génère réellement les comptes (direct, Google, campagne email, partenaire, etc.).

Un graphique en barres illustre ensuite la **répartition des inscriptions par source d'acquisition** semaine après semaine, pour suivre l'impact des campagnes marketing en temps réel.

---

### 1.4 Activation — Les premiers pas après l'inscription

Avoir des inscrits, c'est bien. Avoir des inscrits qui *utilisent vraiment* la plateforme, c'est mieux. Cette section mesure la qualité des nouvelles inscriptions :

- **Taux de profils complétés** — quel pourcentage des utilisateurs a terminé son onboarding et rempli son profil. Un profil incomplet est un utilisateur potentiellement perdu.
- **Délai avant la première action** — combien d'heures en moyenne s'écoulent entre la création du compte et la première interaction significative (recherche, publication, etc.).
- **Bailleurs ayant publié au moins une annonce** — quel pourcentage des bailleurs inscrits est passé à l'action et a mis une annonce en ligne.
- **Clients ayant effectué une première recherche** — quel pourcentage des locataires a lancé au moins une recherche de logement.

---

### 1.5 Croissance et répartition des utilisateurs

Deux graphiques donnent une vision longitudinale de la croissance :

- **Courbe d'inscription** — l'évolution du nombre de nouveaux comptes créés mois par mois. Idéal pour identifier les pics (après une campagne) et les creux (période creuse).
- **Répartition par statut** — un graphique circulaire qui montre la proportion d'utilisateurs actifs, inactifs, et suspendus. Un signal d'alerte si la proportion d'inactifs grimpe.

---

### 1.6 Interactions — Ce que les gens font sur la plateforme

Quatre métriques clés sur les 30 derniers jours mesurent l'engagement réel des utilisateurs :

- **Vues d'annonces** — le nombre total de fois qu'une annonce a été ouverte et consultée.
- **Favoris ajoutés** — le nombre de likes laissés sur des annonces.
- **Partages** — le nombre de fois qu'une annonce a été partagée (lien copié, envoyé sur WhatsApp, etc.).
- **Contacts** — le nombre de clics sur le numéro de téléphone ou le bouton de message d'un bailleur, après déblocage.

Un graphique de tendance montre ensuite l'**évolution de ces interactions sur les 30 derniers jours**, jour après jour. C'est la meilleure façon de voir si un pic de trafic s'est traduit par un engagement réel ou juste du rebond.

Un graphique complémentaire affiche la **répartition des annonces par ville** (Douala, Yaoundé, Bafoussam…) pour voir quels marchés sont les plus dynamiques.

---

### 1.7 Rétention — Les utilisateurs reviennent-ils ?

C'est la section qui mesure la fidélité. Elle répond à la question : une fois que quelqu'un a utilisé KeyHome, est-ce qu'il revient ?

- **DAU** (Daily Active Users) — combien de personnes sont connectées aujourd'hui.
- **WAU** (Weekly Active Users) — combien de personnes se sont connectées au moins une fois cette semaine.
- **MAU** (Monthly Active Users) — combien de personnes se sont connectées ce mois.
- **Taux de stickiness (DAU/MAU)** — le ratio entre les actifs du jour et les actifs du mois. Plus ce pourcentage est élevé, plus les gens reviennent *chaque jour*. C'est la métrique la plus représentative de la santé d'une app mobile.
- **Taux de retour à 7 jours** — quel pourcentage des utilisateurs qui ont visité la semaine dernière est revenu cette semaine.
- **Bailleurs actifs vs inactifs** — combien de bailleurs ont interagi avec leurs annonces ou le dashboard ces 30 derniers jours, et combien sont devenus silencieux.

Un graphique de cohorte visualise la **rétention par cohorte mensuelle** — on voit si les utilisateurs inscrits en janvier sont encore là en mars, en juin, en décembre.

---

### 1.8 Revenus — La performance financière de KeyHome

Cette section donne une vision claire et professionnelle des flux financiers de la plateforme :

- **Revenu mensuel (MRR)** — le total des paiements encaissés ce mois-ci, toutes sources confondues.
- **Revenu moyen par utilisateur actif (ARPU)** — combien dépense en moyenne chaque utilisateur actif. Un indicateur de monétisation.
- **Taux de churn** — le pourcentage d'utilisateurs qui étaient actifs le mois dernier et qui ne sont pas revenus ce mois. Un churn élevé est un signal d'alerte immédiat.
- **Source de revenu principale** — est-ce que l'argent vient surtout des déblocages de contacts ? Des abonnements bailleurs ? Des boosts d'annonces ? Des achats de crédits ? La source dominante est mise en avant avec son montant.

Un graphique linéaire double montre ensuite :
1. **Les revenus réels mois par mois** (ligne verte) sur les 12 derniers mois.
2. **Les projections à +3 mois, +6 mois et +12 mois** (ligne orange en pointillés), calculées par régression linéaire sur l'historique. Un outil précieux pour anticiper la croissance et préparer les décisions stratégiques.

---

### 1.9 Le tunnel de conversion

Un widget visuel unique qui représente les **5 étapes du parcours utilisateur** sous forme d'entonnoir :

1. **Visiteurs** — les gens qui arrivent sur le site
2. **Inscrits** — ceux qui créent un compte
3. **Actifs** — ceux qui font une vraie action
4. **Payants** — ceux qui achètent des crédits
5. **Fidèles** — ceux qui reviennent plusieurs fois

À chaque étape, le tunnel affiche le **nombre d'utilisateurs, le taux de passage à l'étape suivante, et le taux de déperdition**. Un outil fondamental pour identifier où les utilisateurs abandonnent et sur quoi concentrer les efforts d'amélioration.

---

### 1.10 Qualité de service

Quatre indicateurs mesurent la fiabilité et la satisfaction globale de la plateforme :

- **Score NPS** (Net Promoter Score) — calculé à partir des avis et notes des utilisateurs. Un NPS positif signifie que plus de gens recommandent KeyHome qu'ils ne la déconseillent.
- **Taux de signalement** — le pourcentage d'annonces qui ont été signalées par des utilisateurs comme suspectes ou frauduleuses. Un taux qui grimpe est un signal que la qualité du catalogue se dégrade.
- **Délai moyen de location** — combien de jours en moyenne entre la publication d'une annonce et sa première réservation. Un indicateur de la liquidité du marché.
- **Réactivité des bailleurs** — quel pourcentage des demandes de visite reçoit une réponse (confirmée ou refusée) de la part du bailleur. Les bailleurs fantômes qui ne répondent pas nuisent à toute la plateforme.

---

### 1.11 La carte de chaleur géographique

Un widget cartographique qui visualise la **densité des annonces et des transactions sur une carte interactive**. On peut voir immédiatement quels quartiers de Douala ou de Yaoundé concentrent le plus d'activité, identifier les zones sous-représentées, et guider les efforts de développement commercial vers les zones à fort potentiel non exploité.

---

### 1.12 Export du rapport complet

En bas du tableau de bord, un widget d'action permet d'**exporter l'intégralité du rapport de métriques** en un clic :

- **Export CSV** — un fichier tableur structuré avec toutes les sections (acquisition, activation, rétention, revenus, tunnel, qualité) prêt à être ouvert dans Excel ou Google Sheets pour une analyse approfondie.
- **Export PDF** — un rapport A4 mis en page, horodaté, aux couleurs de KeyHome, prêt à être imprimé ou partagé avec des investisseurs ou des partenaires.

---

## 2. Gestion des utilisateurs

Le module utilisateurs donne un accès complet à tous les comptes inscrits sur KeyHome, avec des outils de recherche, de filtrage et d'action granulaires.

L'administrateur peut **parcourir, filtrer et rechercher** tous les comptes par nom, email, rôle, statut, ou date d'inscription. Pour chaque utilisateur, il peut consulter son profil complet, son historique d'activité, ses annonces, ses paiements, ses avis, et ses transactions de crédits.

**Actions disponibles sur chaque utilisateur :**
- Visualiser le profil complet avec toutes les métadonnées
- Modifier les informations de base
- **Suspendre ou réactiver** un compte en un clic
- Consulter l'**historique de connexion** (adresse IP, navigateur, date/heure de chaque session)
- Accéder directement aux annonces, paiements et avis associés à cet utilisateur

---

## 3. Gestion des permissions et des rôles

Une page dédiée permet de gérer les **niveaux d'accès de chaque utilisateur** sur la plateforme. Trois rôles coexistent : **Client** (locataire standard), **Agent** (bailleur ou agent immobilier), et **Administrateur**.

L'administrateur peut **changer le rôle d'un utilisateur** depuis un menu déroulant — par exemple, promouvoir un agent de confiance en administrateur, ou rétrograder un compte dont le rôle ne correspond plus à son usage réel.

Il peut également **activer ou désactiver n'importe quel compte** individuellement, ou effectuer des **actions en lot** sur une sélection d'utilisateurs — par exemple, activer tous les comptes d'une même agence en une seule opération.

---

## 4. Gestion des agences

KeyHome permet à des agences immobilières de rejoindre la plateforme avec leur propre espace dédié. L'administrateur a une vue complète sur toutes les agences partenaires : informations de profil, nombre d'annonces actives, statut de vérification, plan d'abonnement en cours, et historique des paiements.

Il peut valider ou rejeter les demandes d'inscription d'agences, gérer leurs profils, et surveiller leur activité.

---

## 5. Modération des annonces

### 5.1 File de modération (annonces en attente)

C'est l'un des modules les plus critiques de l'administration. Chaque annonce publiée par un bailleur passe d'abord par cette file avant d'être visible par les locataires. L'administrateur voit la liste de toutes les annonces en attente, avec les photos, la description, le prix, la localisation, et le profil du bailleur qui a soumis l'annonce.

Pour chaque annonce, il peut :
- **Approuver** — l'annonce est immédiatement mise en ligne et visible par tous
- **Rejeter** — l'annonce est refusée avec possibilité d'indiquer le motif (photos insuffisantes, prix incohérent, description manquante, etc.)
- **Demander des modifications** — l'annonce est renvoyée au bailleur avec des instructions précises

### 5.2 Gestion globale des annonces

Une vue complète de toutes les annonces de la plateforme, avec des filtres avancés par statut (en ligne, en attente, expirée, suspendue), par ville, par type de bien, par prix, et par date. L'administrateur peut modifier, désactiver ou supprimer n'importe quelle annonce, et voir en temps réel ses métriques de performance (vues, favoris, déblocages).

---

## 6. Gestion des signalements

Quand un locataire signale une annonce suspecte, ce signalement remonte ici. L'administrateur voit la liste de tous les signalements avec : l'annonce concernée, le motif du signalement (fraude suspectée, photos trompeuses, prix anormal, coordonnées incorrectes, etc.), l'utilisateur qui a signalé, et la date.

Pour chaque signalement, il peut prendre une décision : **clore le signalement** si l'annonce est conforme, **suspendre l'annonce** pendant l'enquête, ou **supprimer l'annonce et sanctionner le bailleur** si la fraude est avérée. Chaque décision est tracée dans le journal d'activité.

---

## ⭐ 7. Gestion des avis

Tous les avis laissés par les locataires sont visibles ici. L'administrateur peut lire chaque avis en détail, filtrer par note (de 1 à 5 étoiles), par annonce, ou par date. Si un avis enfreint les règles de la communauté (contenu offensant, faux avis de complaisance, conflit d'intérêts), il peut le **supprimer** avec une notification automatique à l'auteur.

---

## 8. Gestion des paiements et transactions

### 8.1 Paiements

Un tableau de bord financier exhaustif qui liste toutes les transactions effectuées sur la plateforme : déblocages de contacts, achats de crédits, abonnements, boosts. Pour chaque paiement : montant en FCFA, statut (réussi, en attente, échoué), passerelle utilisée (MTN Mobile Money, Orange Money, carte bancaire), utilisateur, et horodatage.

L'administrateur peut filtrer, rechercher et exporter ces données pour la comptabilité.

### 8.2 Remboursements

Une section dédiée à la gestion des remboursements. Quand un utilisateur conteste un paiement ou rencontre un problème technique, la demande de remboursement apparaît ici et peut être traitée manuellement.

---

## 9. Système de crédits

### 9.1 Packs de crédits

L'administrateur gère les **packs de crédits** proposés à la vente aux locataires : le nombre de crédits inclus dans chaque pack, le prix en FCFA, et la description. Ces packs peuvent être créés, modifiés ou désactivés sans aucun déploiement technique.

### 9.2 Historique des transactions de crédits

Un journal complet de tous les mouvements de crédits : achat d'un pack, utilisation pour débloquer une annonce, crédit offert, bonus de bienvenue, etc. Idéal pour résoudre les litiges utilisateurs en moins d'une minute.

---

## 10. Codes promotionnels

L'administrateur peut créer des **codes promo** pour des opérations marketing ciblées : réduction sur les packs de crédits, crédits offerts à l'inscription, offres spéciales pour des événements. Pour chaque code, il définit le type de réduction (pourcentage ou montant fixe), la date d'expiration, le nombre d'utilisations maximum, et les conditions d'éligibilité. Les codes actifs et leur taux d'utilisation sont visibles en temps réel.

---

## 11. Plans d'abonnement

KeyHome propose des plans d'abonnement pour les bailleurs et les agences qui souhaitent bénéficier d'avantages premium. L'administrateur peut créer et modifier les plans : nom du plan, prix mensuel et annuel en FCFA, nombre d'annonces actives autorisées, crédits de boost mensuels inclus, et fonctionnalités exclusives. Les plans actifs sont immédiatement disponibles à la souscription.

---

## 12. Sondages

Un module complet de gestion des sondages utilisateurs. L'administrateur peut **créer des sondages** sur mesure avec des questions à choix multiples, des champs texte libre, ou des échelles de notation. Il configure le ciblage (tous les utilisateurs, seulement les locataires, seulement les nouveaux inscrits), la durée d'affichage, et la fréquence.

Une fois les réponses collectées, il accède à une **vue analytique complète** : taux de réponse, répartition des réponses par question, verbatims des réponses texte, et évolution dans le temps. Le tout, pour une prise de décision basée sur les vraies données des utilisateurs.

---

## 13. Newsletter

### 13.1 Abonnés

La liste complète des personnes abonnées à la newsletter de KeyHome : email, date d'inscription, statut d'abonnement (actif / désabonné). L'administrateur peut exporter cette liste pour des envois via des outils tiers.

### 13.2 Campagnes

La gestion des campagnes email : création d'une nouvelle campagne, rédaction du contenu, ciblage des abonnés, et suivi des statistiques d'envoi (taux d'ouverture, taux de clic, désabonnements après envoi).

---

## 14. Gestion géographique

### 14.1 Villes

L'administrateur gère les villes disponibles sur KeyHome : Douala, Yaoundé, Bafoussam, et toutes les nouvelles villes à ajouter lors de l'expansion. Pour chaque ville, il configure le nom, la région, les coordonnées GPS pour le centrage de la carte, et le statut actif/inactif.

### 14.2 Quartiers

Pour chaque ville, l'administrateur gère la liste des quartiers disponibles. Ces quartiers alimentent les filtres de recherche et les champs d'adresse des bailleurs. Un quartier bien configuré — avec ses coordonnées GPS précises — améliore directement la pertinence des résultats de recherche et la précision de la carte.

---

## 15. Attributs des biens immobiliers

### 15.1 Catégories d'attributs

Les attributs sont les caractéristiques techniques des logements : "Équipements", "Sécurité", "Services", "Transports à proximité", etc. L'administrateur crée et gère les catégories qui regroupent ces attributs.

### 15.2 Attributs

Les attributs individuels : "Climatisation", "Groupe électrogène", "Gardiennage 24h/24", "Parking couvert", "Accès handicapé", "Wifi inclus", "Eau chaude", etc. Ces attributs s'affichent comme cases à cocher dans le formulaire de publication d'annonce, et comme filtres dans la recherche. Plus les attributs sont riches et bien pensés, plus la recherche des locataires est précise.

---

## 16. Centre de notifications — Parler directement aux utilisateurs

Un outil de **broadcast ciblé** qui permet à l'équipe KeyHome d'envoyer une notification in-app à n'importe quel segment d'utilisateurs, directement depuis le panneau d'administration.

L'administrateur rédige un titre et un message (avec mise en forme riche), choisit la cible parmi : **tous les utilisateurs**, **les administrateurs uniquement**, **les agents/bailleurs uniquement**, ou **les clients uniquement** — puis envoie en un clic.

La notification est délivrée instantanément dans le centre de notifications de chaque utilisateur ciblé, avec une file d'attente par blocs de 100 pour gérer efficacement les envois massifs. Un compteur de livraison confirme combien d'utilisateurs ont été notifiés.

Un récapitulatif des statistiques affiche le nombre total de notifications envoyées sur la plateforme, celles non encore lues, et celles envoyées aujourd'hui.

---

## 17. Rapports et exports

Une page dédiée aux **rapports de synthèse** avec des données actualisées en temps réel et des boutons d'export en un clic :

- **Utilisateurs inscrits** (total et nouveaux ce mois)
- **Annonces totales et actives**
- **Revenus du mois en cours (XOF)**
- **Avis reçus et note moyenne**

Trois exports CSV sont disponibles immédiatement :
- **Export utilisateurs** — toute la liste des comptes avec prénom, nom, email, rôle, statut, date d'inscription
- **Export annonces** — toutes les annonces avec titre, statut, prix, ville, quartier, date de publication
- **Export paiements** — toutes les transactions avec montant, statut, passerelle, utilisateur, date

Chaque fichier est nommé automatiquement avec la date du jour et peut être ouvert directement dans Excel.

---

## 18. Paramètres de la plateforme

Le module le plus sensible de l'administration. Il permet de modifier le comportement global de KeyHome sans toucher au code. Deux paramètres sont configurables depuis cette page :

**Système de crédits :**
- Le **coût de déblocage** — combien de crédits un locataire doit dépenser pour accéder aux coordonnées d'un bailleur. Modifier ce paramètre impacte directement la barrière à l'entrée et le taux de déblocage.
- Le **bonus de bienvenue** — combien de crédits sont offerts automatiquement à chaque nouveau compte créé. Un levier direct sur l'activation des nouveaux inscrits.

**Annonces :**
- La **durée de vie d'une annonce** — après combien de jours une annonce expire automatiquement si le bailleur ne la renouvelle pas.

**Sécurité renforcée :** pour protéger ces paramètres critiques, toute modification déclenche un **processus de vérification en deux étapes**. L'administrateur clique sur "Modifier", un code à 6 chiffres est envoyé à son adresse email, il doit saisir ce code dans les 10 minutes pour confirmer le changement. Impossible de modifier ces paramètres accidentellement ou sans accès à l'email de l'administrateur. Chaque modification est tracée dans le journal d'activité avec l'ancienne et la nouvelle valeur.

---

## 19. Journal d'activité

Un registre immuable de **toutes les actions effectuées par les administrateurs** sur la plateforme : qui a approuvé quelle annonce, qui a modifié quel paramètre, qui a suspendu quel compte, qui a supprimé quel avis — avec la date, l'heure précise, et l'adresse IP de l'auteur de l'action. Indispensable pour l'audit, la conformité, et la traçabilité interne.

---

## 20. Feature Flags — Activer ou désactiver des fonctionnalités à chaud

Un tableau de bord d'interrupteurs qui permet d'**activer ou désactiver des fonctionnalités de la plateforme en temps réel**, sans déploiement. Par exemple : désactiver temporairement les déblocages pendant une maintenance du système de paiement, mettre en pause les inscriptions pendant un pic de charge, activer une fonctionnalité en bêta pour un groupe restreint d'utilisateurs.

Chaque flag peut être basculé en un clic, et réinitialisé à sa valeur par défaut de configuration si besoin. C'est l'outil idéal pour gérer des incidents en production ou pour faire des déploiements progressifs de nouvelles fonctionnalités sans risque.

---

## 21. Monitoring des jobs en échec

KeyHome tourne sur une architecture de files d'attente (queues) pour tous les traitements en arrière-plan : envoi d'emails, traitement de paiements, envoi de notifications push, génération de PDF, synchronisation avec MeiliSearch, etc. Quand un job échoue pour une raison technique (timeout, service tiers indisponible, erreur réseau), il atterrit ici.

L'administrateur voit la liste de tous les jobs en échec avec : le type de job, la file d'attente concernée (critique, paiements, emails, défaut), le message d'erreur complet, et l'horodatage de l'échec. Il peut **relancer un job spécifique** ou **relancer tous les jobs en échec en une seule action**. Il peut aussi **purger la liste** si les échecs sont obsolètes et sans impact. La liste se rafraîchit automatiquement toutes les 30 secondes.

---

## 22. Suivi des visites et de l'acquisition

### 22.1 Visites du site

Un journal des sessions de visite sur la plateforme : pages visitées, durée de session, source de trafic, géolocalisation approximative. Ces données alimentent les métriques d'acquisition du tableau de bord.

### 22.2 Utilisateurs par canal d'acquisition

Une table détaillée qui lie chaque inscription à son canal d'acquisition (UTM source, medium, campaign), permettant une analyse fine du ROI de chaque canal marketing.

---

## 23. Suivi des déblocages

Un registre complet de tous les déblocages d'annonces effectués sur la plateforme : quel locataire a débloqué quelle annonce, à quelle date, pour quel montant de crédits. Ces données permettent d'identifier les annonces les plus attractives, les locataires les plus actifs, et les bailleurs dont les annonces génèrent le plus d'intérêt réel.

---

## En résumé — Ce que le panneau d'administration de KeyHome change vraiment

La plupart des plateformes immobilières africaines sont gérées avec des feuilles Excel, des groupes WhatsApp, et des outils bricole. KeyHome a fait le choix inverse : **investir dans une infrastructure d'administration aussi sérieuse que l'application elle-même**.

| Besoin opérationnel | Outil disponible |
|---|---|
| Surveiller la croissance en temps réel | Dashboard avec 20+ métriques actualisées |
| Modérer les annonces rapidement | File de modération avec actions en un clic |
| Gérer les fraudes et signalements | Module de signalement avec historique complet |
| Piloter les revenus | MRR, ARPU, churn, projections graphiques |
| Comprendre d'où viennent les utilisateurs | Acquisition multi-canal avec UTM tracking |
| Mesurer la rétention et la fidélité | DAU/WAU/MAU, stickiness, cohortes |
| Communiquer avec les utilisateurs | Centre de notifications ciblé |
| Modifier les règles de la plateforme | Paramètres protégés par double vérification |
| Gérer les crises techniques | Feature flags + monitoring des jobs |
| Préparer un reporting investisseur | Export PDF/CSV en un clic |

Le panneau d'administration de KeyHome n'est pas juste un outil de gestion. C'est le **cerveau opérationnel de la plateforme** — celui qui permet à une petite équipe de faire tourner une marketplace immobilière au niveau d'une entreprise tech mature, avec la rigueur, la transparence, et la réactivité que ça implique.

---

> **KeyHome Admin. Le contrôle total. La responsabilité entière.**

---

*© 2026 KeyHome — Panneau d'administration interne*
*Accès restreint — Équipe KeyHome uniquement*
