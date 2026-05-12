# 🦊 Guide GitLab CI/CD pour KeyHome

> **Documentation complète du workflow CI/CD**  
> **Basée sur** : [GitLab CI/CD Documentation](https://docs.gitlab.com/ee/ci/)  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 📋 Table des matières

1. [Architecture CI/CD](#architecture)
2. [Configuration GitLab Runner](#gitlab-runner)
3. [GitLab Container Registry](#container-registry)
4. [Pipeline Stages](#pipeline-stages)
5. [Variables & Secrets](#variables-secrets)
6. [Workflow quotidien](#workflow-quotidien)
7. [Troubleshooting](#troubleshooting)

---

## 🏗️ Architecture CI/CD

### Vue d'ensemble

```
┌──────────────────────────────────────────────────────────────┐
│  DÉVELOPPEUR                                                  │
│  ┌────────────────┐                                          │
│  │ git push origin│                                          │
│  │     main       │                                          │
│  └────────┬───────┘                                          │
└───────────┼──────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  GITLAB.COM                                                   │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ .gitlab-ci.yml déclenche les stages                     ││
│  │                                                          ││
│  │  Stage 1: Quality (PHPStan, Pint)                       ││
│  │  Stage 2: Security (Composer Audit)                     ││
│  │  Stage 3: Build (Docker Image)                          ││
│  │  Stage 4: Test (PHPUnit)                                ││
│  │  Stage 5: Deploy ────────────────────────┐              ││
│  │  Stage 6: Notify (Slack)                 │              ││
│  │  Stage 7: Cleanup                        │              ││
│  └──────────────────────────────────────────┼──────────────┘│
│                                             │               │
│  ┌───────────────────────────────┐          │               │
│  │ GitLab Container Registry     │          │               │
│  │ registry.gitlab.com/...       │          │               │
│  │ ┌─────────────────────────┐   │          │               │
│  │ │ app:main (latest build) │ ◀─┘          │               │
│  │ │ app:develop             │              │               │
│  │ │ app:v1.2.3              │              │               │
│  │ └─────────────────────────┘              │               │
│  └───────────────────────────────┘          │               │
└─────────────────────────────────────────────┼───────────────┘
                                              │
                                              ▼
┌──────────────────────────────────────────────────────────────┐
│  VPS PRODUCTION (keyhome.neocraft.dev)                       │
│  ┌───────────────────────────────┐                          │
│  │ GitLab Runner (self-hosted)   │                          │
│  │                               │                          │
│  │ 1. Pull image depuis Registry │                          │
│  │ 2. docker compose pull        │                          │
│  │ 3. docker compose up -d       │                          │
│  │ 4. php artisan migrate        │                          │
│  │ 5. php artisan optimize       │                          │
│  └───────────────────────────────┘                          │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ Docker Containers                                     │  │
│  │  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐                 │  │
│  │  │App │ │Web │ │DB  │ │Redis│Worker│                 │  │
│  │  └────┘ └────┘ └────┘ └────┘ └────┘                 │  │
│  └───────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

---

## 🖥️ Configuration GitLab Runner

### Installation du Runner (sur le VPS)

```bash
# Ajouter le repository officiel GitLab
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | bash

# Installer
apt-get install gitlab-runner

# Vérifier
gitlab-runner --version
```

**📚 Référence** : [GitLab Runner Installation](https://docs.gitlab.com/runner/install/linux-repository.html)

### Enregistrement du Runner

```bash
# Lancer l'enregistrement interactif
gitlab-runner register

# Répondre aux questions :
GitLab instance URL: https://gitlab.com/
Registration token: [Depuis Settings > CI/CD > Runners]
Description: keyhome-vps-production
Tags: self-hosted-shell,production
Executor: shell
```

### Configuration avancée

Éditer `/etc/gitlab-runner/config.toml` :

```toml
concurrent = 1  # Nombre de jobs en parallèle
check_interval = 0

[session_server]
  session_timeout = 1800

[[runners]]
  name = "keyhome-vps-production"
  url = "https://gitlab.com/"
  token = "RUNNER_TOKEN_GÉNÉRÉ"
  executor = "shell"
  
  # Limites de ressources (optionnel)
  limit = 1
  request_concurrency = 1
  
  # Variables d'environnement par défaut
  environment = [
    "DOCKER_DRIVER=overlay2",
    "DOCKER_TLS_CERTDIR="
  ]
  
  [runners.custom_build_dir]
    enabled = false
  
  [runners.cache]
    MaxUploadedArchiveSize = 0
```

### Permissions Docker

```bash
# Ajouter gitlab-runner au groupe docker
usermod -aG docker gitlab-runner

# Vérifier
su - gitlab-runner -s /bin/bash
docker ps
exit

# Redémarrer
systemctl restart gitlab-runner
systemctl status gitlab-runner
```

---

## 🐳 GitLab Container Registry

### Login au Registry

#### Sur le VPS (gitlab-runner user)

```bash
su - gitlab-runner -s /bin/bash

# Login avec un Personal Access Token
docker login registry.gitlab.com
# Username: votre_username_gitlab
# Password: glpat-XxXxXxXxXxXxXxXxXxXx (PAT)
```

#### Créer un Personal Access Token (PAT)

1. GitLab > **Profile** > **Access Tokens**
2. Token name : `keyhome-registry-production`
3. Expiration : 1 an
4. Scopes :
   - ✅ `read_registry`
   - ✅ `write_registry`
5. Cliquer sur **Create personal access token**
6. **Copier le token** (ne sera plus jamais affiché)

### Structure du Registry

```
registry.gitlab.com/neocraftteam/immoapp-backend/
├── app:main              # Branche main (production)
├── app:develop           # Branche develop
├── app:feature-xyz       # Feature branches
└── app:v1.2.3            # Tags de version
```

### Commandes utiles

```bash
# Lister les images locales
docker images | grep immoapp-backend

# Pull une image spécifique
docker pull registry.gitlab.com/neocraftteam/immoapp-backend/app:main

# Tag une image
docker tag registry.gitlab.com/neocraftteam/immoapp-backend/app:main keyhome-backend:latest

# Nettoyer les anciennes images
docker image prune -a --filter "until=72h"
```

**📚 Référence** : [GitLab Container Registry](https://docs.gitlab.com/ee/user/packages/container_registry/)

---

## 🔄 Pipeline Stages

### Vue d'ensemble du .gitlab-ci.yml

```yaml
stages:
  - quality     # Lint & analyse statique
  - security    # Audit de sécurité
  - build       # Construction image Docker
  - test        # Tests unitaires
  - deploy      # Déploiement sur VPS
  - notify      # Notifications Slack
  - cleanup     # Nettoyage

variables:
  APP_IMAGE: $CI_REGISTRY_IMAGE/app
  IMAGE_TAG: $CI_COMMIT_REF_SLUG

default:
  tags:
    - self-hosted-shell  # 👈 Exécuté sur votre VPS
```

### Stage 1 : Quality

#### PHPStan (Analyse statique)

```yaml
phpstan:
  stage: quality
  script:
    - composer install --no-interaction
    - ./vendor/bin/phpstan analyse --memory-limit=2G
  only:
    - branches
```

**Ce que ça vérifie** :
- Types de variables
- Appels de méthodes inexistantes
- Propriétés non définies
- Erreurs logiques

#### Pint (Style de code)

```yaml
style_check:
  stage: quality
  script:
    - composer install --no-interaction
    - ./vendor/bin/pint --test  # --test = ne modifie pas, juste vérifie
  only:
    - branches
```

**Ce que ça vérifie** :
- PSR-12 compliance
- Formatage du code
- Conventions Laravel

### Stage 2 : Security

```yaml
composer_security:
  stage: security
  script:
    - composer audit  # Vérifie les CVE dans les dépendances
  only:
    - branches
```

### Stage 3 : Build

```yaml
build_image:
  stage: build
  script:
    # Login au registry
    - echo "$CI_REGISTRY_PASSWORD" | docker login -u "$CI_REGISTRY_USER" --password-stdin "$CI_REGISTRY"
    
    # Build l'image (avec cache layer)
    - docker build --pull -t $APP_IMAGE:$IMAGE_TAG .
    
    # Push au registry
    - docker push $APP_IMAGE:$IMAGE_TAG
    
    # Logout
    - docker logout $CI_REGISTRY
  only:
    - branches
```

**Optimisations** :
- `--pull` : Récupère les dernières images de base
- Cache Docker : Réutilise les layers pour build plus rapide

### Stage 4 : Test

```yaml
test_suite:
  stage: test
  variables:
    DB_CONNECTION: pgsql
    DB_HOST: 127.0.0.1
    DB_DATABASE: testing
  script:
    - composer install
    - cp .env.example .env.testing
    - php artisan key:generate --env=testing
    - php artisan migrate --env=testing --force
    - php artisan test --env=testing
  only:
    - branches
```

### Stage 5 : Deploy (Production)

```yaml
production_deploy:
  stage: deploy
  script:
    # 1. Copier les fichiers config
    - mkdir -p /var/www/ImmoApp-Backend/.docker/nginx/conf.d
    - cp docker-compose.yml /var/www/ImmoApp-Backend/
    - cp -rf .docker/nginx/conf.d/. /var/www/ImmoApp-Backend/.docker/nginx/conf.d/
    - cd /var/www/ImmoApp-Backend
    
    # 2. Login au registry
    - echo "$CI_REGISTRY_PASSWORD" | docker login -u "$CI_REGISTRY_USER" --password-stdin "$CI_REGISTRY"
    
    # 3. Pull l'image et redémarrer
    - export FULL_IMAGE="${CI_REGISTRY_IMAGE}/app:main"
    - APP_IMAGE=$FULL_IMAGE docker compose pull
    - APP_IMAGE=$FULL_IMAGE docker compose up -d --no-build
    
    # 4. Post-deploy Laravel
    - APP_IMAGE=$FULL_IMAGE docker compose exec -T app php artisan migrate --force
    - APP_IMAGE=$FULL_IMAGE docker compose exec -T app php artisan optimize:clear
    - APP_IMAGE=$FULL_IMAGE docker compose exec -T app php artisan optimize
    - APP_IMAGE=$FULL_IMAGE docker compose exec -T app php artisan storage:link
    - APP_IMAGE=$FULL_IMAGE docker compose exec -T app php artisan l5-swagger:generate
    
    # 5. Logout
    - docker logout $CI_REGISTRY
  only:
    - main  # Uniquement sur la branche main
```

### Stage 6 : Notify

```yaml
notify_slack_success:
  stage: notify
  script:
    - |
      curl -X POST -H 'Content-type: application/json' \
      --data "{
        \"text\": \"✅ *Déploiement Réussi !*\n*Auteur:* $GITLAB_USER_NAME\n*Message:* $CI_COMMIT_TITLE\"
      }" $SLACK_WEBHOOK_PREPROD
  when: on_success
  only:
    - main
```

### Stage 7 : Cleanup

```yaml
cleanup:
  stage: cleanup
  script:
    - docker system prune -f  # Nettoie images/volumes inutilisés
  when: always
  only:
    - main
```

---

## 🔐 Variables & Secrets

### Variables CI/CD à configurer

**GitLab : Settings > CI/CD > Variables**

| Variable | Valeur | Protégée | Masquée | Utilisée par |
|----------|--------|----------|---------|--------------|
| `CI_REGISTRY` | `registry.gitlab.com` | ✅ | ❌ | build, deploy |
| `CI_REGISTRY_IMAGE` | `registry.gitlab.com/neocraftteam/immoapp-backend` | ✅ | ❌ | build, deploy |
| `CI_REGISTRY_USER` | Votre username GitLab | ✅ | ❌ | build, deploy |
| `CI_REGISTRY_PASSWORD` | Personal Access Token | ✅ | ✅ | build, deploy |
| `SLACK_WEBHOOK_PREPROD` | `https://hooks.slack.com/...` | ✅ | ✅ | notify |

### Variables prédéfinies par GitLab

GitLab fournit automatiquement :

```bash
$CI_COMMIT_SHA         # Hash du commit
$CI_COMMIT_REF_NAME    # Nom de la branche (main, develop...)
$CI_COMMIT_REF_SLUG    # Nom sanitisé (main, develop, feature-xyz)
$CI_PIPELINE_ID        # ID unique de la pipeline
$CI_PROJECT_URL        # URL du projet GitLab
$GITLAB_USER_NAME      # Auteur du commit
```

**📚 Référence** : [GitLab CI/CD Variables](https://docs.gitlab.com/ee/ci/variables/predefined_variables.html)

---

## 💼 Workflow quotidien

### Développement de feature

```bash
# Sur votre machine locale

# 1. Créer une branche
git checkout -b feature/nouvelle-fonctionnalite

# 2. Développer
# ...

# 3. Commit
git add .
git commit -m "feat: ajout nouvelle fonctionnalité"

# 4. Push
git push origin feature/nouvelle-fonctionnalite
```

**Résultat** : Pipeline lancée avec stages `quality`, `security`, `build`, `test` (PAS de deploy)

### Déploiement en production

```bash
# 1. Merge request approuvée
# 2. Merge dans main

git checkout main
git pull origin main
git merge feature/nouvelle-fonctionnalite
git push origin main
```

**Résultat** : Pipeline complète incluant le **deploy** sur le VPS

### Rollback rapide

```bash
# Sur GitLab.com : Pipelines > Sélectionner une pipeline ancienne > Retry

# Ou en manuel sur le VPS :
cd /var/www/ImmoApp-Backend
docker pull registry.gitlab.com/neocraftteam/immoapp-backend/app:main@sha256:ancien_hash
docker compose up -d --force-recreate
```

---

## 🐛 Troubleshooting

### Erreur : "Runner offline"

```bash
# Sur le VPS
systemctl status gitlab-runner

# Si inactif
systemctl restart gitlab-runner

# Vérifier les logs
journalctl -u gitlab-runner -f
```

### Erreur : "Cannot connect to Docker daemon"

```bash
# Vérifier que gitlab-runner est dans le groupe docker
groups gitlab-runner

# Si absent
usermod -aG docker gitlab-runner
systemctl restart gitlab-runner
```

### Erreur : "unauthorized: authentication required" (Registry)

```bash
# Re-login au registry
su - gitlab-runner -s /bin/bash
docker login registry.gitlab.com
# Utiliser un PAT valide
exit

# Ou configurer dans le pipeline
echo "$CI_REGISTRY_PASSWORD" | docker login -u "$CI_REGISTRY_USER" --password-stdin "$CI_REGISTRY"
```

### Pipeline bloquée au stage "Deploy"

```bash
# Vérifier les logs sur le VPS
cd /var/www/ImmoApp-Backend
docker compose logs -f

# Vérifier l'espace disque
df -h

# Nettoyer
docker system prune -a -f
```

### Tests échouent

```bash
# Lancer les tests en local
composer install
cp .env.example .env.testing
php artisan key:generate --env=testing
php artisan migrate:fresh --env=testing
php artisan test --env=testing

# Voir les détails
php artisan test --env=testing --testdox
```

---

## 📊 Monitoring des Pipelines

### Dashboard GitLab

- **URL** : `https://gitlab.com/NeoCraftTeam/ImmoApp-Backend/-/pipelines`
- **Notifications email** : Configurées par défaut sur echec
- **Slack** : Configuré via `SLACK_WEBHOOK_PREPROD`

### Métriques à surveiller

- ✅ **Success Rate** : Doit être > 90%
- ⏱️ **Build Time** : ~3-5 minutes idéalement
- 📦 **Image Size** : Surveiller pour éviter bloat
- 🔄 **Deploy Frequency** : Indicateur de vélocité

---

## 📚 Ressources

- **GitLab CI/CD** : https://docs.gitlab.com/ee/ci/
- **GitLab Runner** : https://docs.gitlab.com/runner/
- **Container Registry** : https://docs.gitlab.com/ee/user/packages/container_registry/
- **Variables** : https://docs.gitlab.com/ee/ci/variables/

---

**Prochaine étape** : Lire `00-migration-serveur.md` pour le déploiement complet sur un nouveau serveur.
