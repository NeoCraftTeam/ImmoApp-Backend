---
name: full-stack-audit-and-prod-prep
description: Audit complet de bases de code full-stack (Backend Laravel, Mobile React Native) et préparation à la mise en production. Utilisez cette compétence pour analyser la sécurité, la qualité du code, et préparer un déploiement final sécurisé.
---

# Full-Stack Audit and Production Readiness

Cette compétence guide l'agent dans l'audit approfondi d'un projet full-stack et sa préparation pour un environnement de production réel.

## Flux de Travail

### 1. Analyse et Audit Initial
- Scanner la base de code pour identifier les frameworks et les dépendances clés.
- Réaliser un audit de sécurité en se concentrant sur les injections SQL, l'authentification et la sécurité des WebViews mobiles.
- Évaluer la qualité du code à l'aide d'outils d'analyse statique (ex: PHP Insights, Larastan).
- Produire un rapport d'audit détaillé en utilisant le template `/home/ubuntu/skills/full-stack-audit-and-prod-prep/templates/audit_report_template.md`.

### 2. Application des Corrections
- Sécuriser les requêtes SQL "raw" en utilisant des requêtes préparées.
- Renforcer la sécurité des applications mobiles (WebView whitelist, blocage d'accès fichiers).
- Améliorer la validation des webhooks et la gestion des secrets.
- Mettre à jour les configurations CI/CD (GitHub Actions) pour correspondre aux exigences du projet.

### 3. Préparation à la Production
- Vérifier l'alignement de la documentation API (Swagger/OpenAPI) avec le code réel.
- Contrôler les assets (logos, icônes) et les configurations d'environnement (URLs de production).
- Vérifier la configuration des tâches de fond (Workers) et des services d'email.
- Évaluer les index de base de données pour la performance (notamment les index spatiaux pour la géolocalisation).

### 4. Livraison et Documentation
- Produire un guide de déploiement final incluant une checklist de production.
- Fournir un résumé des modifications effectuées pour la mise en conformité.

## Ressources de Référence
- Consultez `/home/ubuntu/skills/full-stack-audit-and-prod-prep/references/audit_checklists.md` pour les points de contrôle spécifiques par domaine.

## Conseils pour Manus
- **Précision** : Lors de l'audit, ne vous contentez pas de lister les problèmes, proposez des corrections concrètes.
- **Sécurité** : Soyez intransigeant sur la validation des entrées utilisateur et des signatures de webhooks.
- **Environnement** : Rappelez toujours à l'utilisateur de ne jamais coder de secrets en dur et d'utiliser des variables d'environnement.
