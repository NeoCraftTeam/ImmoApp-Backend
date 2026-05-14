# Guide de Déploiement — KeyHome Backend
> Dernière mise à jour : mai 2026 — source de vérité pour tout nouveau VPS ou reprise de déploiement.

---

## Table des matières

1. [Architecture globale](#1-architecture-globale)
2. [Prérequis VPS](#2-prérequis-vps)
3. [Phase 1 — Traefik & réseaux Docker](#phase-1--traefik--réseaux-docker)
4. [Phase 2 — Setup Production (premier déploiement)](#phase-2--setup-production)
5. [Phase 3 — Setup Preprod](#phase-3--setup-preprod)
6. [Phase 4 — GitLab CI/CD Variables](#phase-4--gitlab-cicd-variables)
7. [Phase 5 — Monitoring (Grafana / Prometheus / pgAdmin)](#phase-5--monitoring)
8. [Gestion de la base de données](#8-gestion-de-la-base-de-données)
9. [Pipeline CI/CD — fonctionnement détaillé](#9-pipeline-cicd--fonctionnement-détaillé)
10. [Commandes quotidiennes](#10-commandes-quotidiennes)
11. [Rollback manuel](#11-rollback-manuel)
12. [Dépannage](#12-dépannage)
13. [Checklist nouveau VPS](#13-checklist-nouveau-vps)

---

## 1. Architecture globale

```
VPS (/opt/)
├── keyhome/                      ← PRODUCTION
│   ├── docker-compose.yml        ← copié depuis le repo par le CI
│   ├── .env                      ← JAMAIS dans le repo, créé manuellement
│   └── .docker/nginx/conf.d/     ← copié depuis le repo par le CI
│
└── keyhome-preprod/              ← PREPROD (staging)
    ├── docker-compose.yml        ← copié depuis docker-compose.preprod.yml par le CI
    ├── .env                      ← JAMAIS dans le repo, créé manuellement
    └── .docker/nginx/conf.d/     ← copié depuis le repo par le CI
```

### Conteneurs par environnement

```
PRODUCTION (docker-compose.yml)         PREPROD (docker-compose.preprod.yml)
────────────────────────────────        ────────────────────────────────────
keyhome-prod-app       (php-fpm)        keyhome-preprod-backend  (php-fpm)
keyhome-prod-worker    (queue)          keyhome-preprod-worker
keyhome-prod-worker-tours              keyhome-preprod-web      (:9091)
keyhome-prod-reverb    (websocket)      keyhome-preprod-reverb
keyhome-prod-web       (:9090, nginx)   keyhome-preprod-nightwatch-agent
keyhome-prod-nightwatch-agent
keyhome-prod-pgbouncer (pooler)         ← partagé via keyhome-prod-network
keyhome-prod-db        (PostgreSQL)     ← partagé via keyhome-prod-network
keyhome-prod-redis                      ← partagé via keyhome-prod-network
keyhome-prod-meilisearch                ← partagé via keyhome-prod-network
keyhome-prod-db-backup-local
keyhome-prod-db-backup-s3

── Profil --profile monitoring (prod uniquement, off par défaut) ──
keyhome-prod-prometheus
keyhome-prod-grafana
keyhome-prod-node-exporter
keyhome-prod-cadvisor
keyhome-prod-postgres-exporter
keyhome-prod-redis-exporter

── Profil --profile debug (prod uniquement, off par défaut) ──
keyhome-prod-pgadmin

── Profil --profile replica (prod uniquement, off par défaut) ──
keyhome-prod-db-replica
```

### Isolation des données

| Ressource    | Production           | Preprod                        |
|---|---|---|
| PostgreSQL   | DB `keyhome_prod`    | DB `keyhome_preprod` (même serveur) |
| Redis        | prefix par défaut    | `REDIS_PREFIX=keyhome_preprod_database_` |
| Meilisearch  | index normaux        | `SCOUT_PREFIX=preprod_`        |
| Reverb       | conteneur séparé     | conteneur séparé               |
| Backups      | local + S3 (nuit)    | aucun (données de test)        |

### Réseaux Docker

| Réseau                   | Rôle |
|---|---|
| `keyhome_keyhome-network` | Réseau interne prod (DB, Redis, Meilisearch, app, worker…) |
| `traefik-public`          | Réseau Traefik pour TLS/HTTPS (externe) |
| `preprod-network`         | Réseau interne preprod |

> Le preprod attache ses conteneurs au réseau prod (`keyhome_keyhome-network`) pour accéder à DB/Redis/Meilisearch.

---

## 2. Prérequis VPS

```bash
# Docker + Docker Compose v2
docker --version          # >= 24
docker compose version    # >= 2.20

# GitLab Runner self-hosted
gitlab-runner --version

# Ports ouverts
# 80, 443 → Traefik
# 9090    → prod nginx (interne, via Traefik)
# 9091    → preprod nginx (interne, via Traefik)
```

### GitLab Runner

Le runner doit avoir le tag `self-hosted-shell` et l'executor `shell` :

```bash
# Vérifier
gitlab-runner list

# Si absent, éditer /etc/gitlab-runner/config.toml :
[[runners]]
  name = "keyhome-vps"
  tags = ["self-hosted-shell"]
  executor = "shell"

gitlab-runner restart

# L'utilisateur gitlab-runner doit être dans le groupe docker
sudo usermod -aG docker gitlab-runner
sudo -u gitlab-runner docker ps   # doit fonctionner
```

---

## Phase 1 — Traefik & réseaux Docker

### 1.1 Créer le réseau `traefik-public`

```bash
docker network ls | grep traefik-public
# Si absent :
docker network create traefik-public
```

### 1.2 Configuration Traefik minimale

Le fichier `traefik.yml` (ou labels) doit avoir :

```yaml
entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@keyhome.app
      storage: /acme.json
      httpChallenge:
        entryPoint: web
```

Traefik doit être connecté au réseau `traefik-public` :

```bash
docker inspect traefik | grep -A5 Networks
```

### 1.3 DNS — enregistrements A à créer

| Domaine | Pointe vers |
|---|---|
| `api.keyhome.app` | IP du VPS |
| `reverb.keyhome.app` | IP du VPS |
| `grafana.keyhome.app` | IP du VPS |
| `pgadmin.keyhome.app` | IP du VPS |
| `api.keyhome.neocraft.dev` | IP du VPS (preprod) |
| `reverb.keyhome.neocraft.dev` | IP du VPS (preprod) |

---

## Phase 2 — Setup Production

### 2.1 Créer les répertoires

```bash
mkdir -p /opt/keyhome/.docker/nginx/conf.d
```

### 2.2 Créer le fichier `.env` prod

```bash
nano /opt/keyhome/.env
```

**Contenu complet :**

```env
# ── Application ──────────────────────────────────────────────────────────────
APP_NAME="KeyHome"
APP_ENV=production
APP_KEY=                          # généré automatiquement par le pipeline au 1er deploy
APP_DEBUG=false
APP_URL=https://api.keyhome.app
APP_DOMAIN=api.keyhome.app

# ── Docker ───────────────────────────────────────────────────────────────────
COMPOSE_PREFIX=keyhome-prod
WEB_PORT=9090
APP_IMAGE=registry.gitlab.com/neocraft/immoapp-backend/app:main

# ── Base de données ──────────────────────────────────────────────────────────
# IMPORTANT : l'app se connecte via pgbouncer (DB_HOST=pgbouncer est forcé
# dans docker-compose.yml environment:). Ne pas mettre 'db' ici pour les writes.
DB_CONNECTION=pgsql
DB_HOST=pgbouncer          # ignoré : surchargé par docker-compose.yml
DB_PORT=5432
DB_DATABASE=keyhome_prod   # ancien nom : immoapp — voir section 8 pour le renommage
DB_USERNAME=cedrick
DB_PASSWORD=               # mot de passe fort
DB_SSLMODE=disable         # ignoré : surchargé par docker-compose.yml

# Read replica — pointer sur 'db' tant qu'aucune replica n'est bootstrappée
DB_HOST_READ=db

# ── Redis ─────────────────────────────────────────────────────────────────────
REDIS_HOST=redis            # ignoré : surchargé par docker-compose.yml
REDIS_PORT=6379
REDIS_PASSWORD=

# ── Meilisearch ──────────────────────────────────────────────────────────────
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=            # clé forte

# ── Reverb (WebSocket) ───────────────────────────────────────────────────────
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=reverb.keyhome.app
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_DOMAIN=reverb.keyhome.app

# ── Monitoring ────────────────────────────────────────────────────────────────
# Nécessaires uniquement si --profile monitoring est activé
GRAFANA_DOMAIN=grafana.keyhome.app
GRAFANA_PASSWORD=           # mot de passe fort
PGADMIN_DOMAIN=pgadmin.keyhome.app
PGADMIN_EMAIL=admin@keyhome.app
PGADMIN_PASSWORD=           # mot de passe fort

# ── Nightwatch ────────────────────────────────────────────────────────────────
NIGHTWATCH_TOKEN=           # depuis nightwatch.laravel.com

# ── Notifications / Mail ──────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@keyhome.app
MAIL_FROM_NAME="KeyHome"

# ── Paiements ─────────────────────────────────────────────────────────────────
FLUTTERWAVE_SECRET_KEY=
FLUTTERWAVE_PUBLIC_KEY=
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

# ── IA / LLM ──────────────────────────────────────────────────────────────────
OPENAI_API_KEY=
GROQ_API_KEY=
GEMINI_API_KEY=

# ── Storage (Cloudflare R2) ───────────────────────────────────────────────────
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=
AWS_URL=

# ── Backups S3 ────────────────────────────────────────────────────────────────
R2_BACKUP_ENDPOINT=
R2_BACKUP_BUCKET=
R2_BACKUP_ACCESS_KEY=
R2_BACKUP_SECRET_KEY=
R2_BACKUP_REGION=auto

# ── Auth / Clerk ──────────────────────────────────────────────────────────────
CLERK_SECRET_KEY=
CLERK_PUBLISHABLE_KEY=
CLERK_JWKS_URL=

# ── Slack ─────────────────────────────────────────────────────────────────────
SLACK_WEBHOOK_URL=
```

### 2.3 Premier déploiement prod

Merger `preprod → main` sur GitLab (ou push direct sur `main`) → le CI se charge du reste.

```bash
# Vérifier après deploy
ssh cedrick 'cd /opt/keyhome && docker compose ps'
curl -I https://api.keyhome.app/up
```

---

## Phase 3 — Setup Preprod

### 3.1 Créer les répertoires

```bash
mkdir -p /opt/keyhome-preprod/.docker/nginx/conf.d
```

### 3.2 Créer le fichier `.env` preprod

```bash
nano /opt/keyhome-preprod/.env
```

**Différences clés vs prod :**

```env
# ── Application ──────────────────────────────────────────────────────────────
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://api.keyhome.neocraft.dev
APP_DOMAIN=api.keyhome.neocraft.dev

# ── Docker ───────────────────────────────────────────────────────────────────
COMPOSE_PREFIX=keyhome-preprod
WEB_PORT=9091
APP_IMAGE=registry.gitlab.com/neocraft/immoapp-backend/app:preprod

# ── Base de données ───────────────────────────────────────────────────────────
# Pointe sur le conteneur PostgreSQL de PRODUCTION via keyhome-prod-network
DB_HOST=keyhome-prod-db     # conteneur prod accessible via réseau partagé
DB_PORT=5432
DB_DATABASE=keyhome_preprod  # base SÉPARÉE sur le même serveur PostgreSQL
DB_USERNAME=cedrick
DB_PASSWORD=                 # MÊME mot de passe que prod (même serveur)

# Pas de pgbouncer en preprod — connexion directe
DB_HOST_READ=keyhome-prod-db

# ── Redis (partagé avec prod, préfixe isolé) ──────────────────────────────────
REDIS_HOST=keyhome-prod-redis
REDIS_PORT=6379
REDIS_PREFIX=keyhome_preprod_database_  # CRITIQUE : isole les clés de la prod

# ── Meilisearch (partagé avec prod, préfixe isolé) ────────────────────────────
MEILISEARCH_HOST=http://keyhome-prod-meilisearch:7700
MEILISEARCH_KEY=             # MÊME clé que prod (même instance)
SCOUT_PREFIX=preprod_        # CRITIQUE : isole les index de la prod

# ── Reverb ────────────────────────────────────────────────────────────────────
REVERB_DOMAIN=reverb.keyhome.neocraft.dev
# Clés DIFFÉRENTES de la prod (même si même Reverb un jour)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=

# ── Nightwatch (token différent de la prod) ────────────────────────────────────
NIGHTWATCH_TOKEN=

# ── Slack ─────────────────────────────────────────────────────────────────────
SLACK_WEBHOOK_URL=
```

### 3.3 S'assurer que la prod tourne en premier

Le preprod utilise le réseau `keyhome_keyhome-network` créé par la stack prod. La prod DOIT être démarrée avant le preprod.

```bash
# Vérifier que le réseau existe
docker network inspect keyhome_keyhome-network

# Créer la DB preprod si premier déploiement
docker exec keyhome-prod-db psql -U cedrick -c "CREATE DATABASE IF NOT EXISTS keyhome_preprod"
docker exec keyhome-prod-db psql -U cedrick -d keyhome_preprod -c "CREATE EXTENSION IF NOT EXISTS postgis"
```

### 3.4 Premier déploiement preprod

Push sur la branche `preprod` → le CI déploie automatiquement.

```bash
git push gitlab preprod
curl -I http://localhost:9091/up   # smoke test local
```

---

## Phase 4 — GitLab CI/CD Variables

**Aller dans : GitLab → Projet → Settings → CI/CD → Variables**

### 4.1 Variables obligatoires

| Variable | Valeur | Protected | Masked | Notes |
|---|---|---|---|---|
| `CI_REGISTRY_USER` | username GitLab | ✅ | ❌ | Souvent auto-injecté |
| `CI_REGISTRY_PASSWORD` | token d'accès registry | ✅ | ✅ | Souvent auto-injecté |
| `SLACK_WEBHOOK_PROD` | URL webhook Slack prod | ✅ | ✅ | Canal `#deploy-prod` |
| `SLACK_WEBHOOK_PREPROD` | URL webhook Slack preprod | ❌ | ✅ | Canal `#deploy-preprod` |
| `PROD_API_URL` | `https://api.keyhome.app` | ✅ | ❌ | Pour smoke test prod |

### 4.2 Variables optionnelles utiles

| Variable | Valeur | Notes |
|---|---|---|
| `PREPROD_API_URL` | `https://api.keyhome.neocraft.dev` | Utilisée dans les notifications |

### 4.3 Protéger les branches

**Settings → Repository → Protected Branches :**
- `main` → Allowed to push: Maintainers — Allowed to merge: Developers+Maintainers
- `preprod` → idem

---

## Phase 5 — Monitoring

Les services de monitoring sont **uniquement dans `docker-compose.yml` (prod)** et sont **off par défaut** (profiles Docker). Ils ne démarrent jamais sur le preprod.

### 5.1 Activer le stack monitoring

```bash
ssh cedrick 'cd /opt/keyhome && docker compose --profile monitoring up -d'
```

Conteneurs démarrés :
- `keyhome-prod-prometheus` — collecte métriques (interne uniquement)
- `keyhome-prod-grafana` — dashboards → `https://grafana.keyhome.app`
- `keyhome-prod-node-exporter` — métriques VPS (CPU/RAM/Disque)
- `keyhome-prod-cadvisor` — métriques Docker containers
- `keyhome-prod-postgres-exporter` — métriques PostgreSQL
- `keyhome-prod-redis-exporter` — métriques Redis

### 5.2 Activer pgAdmin (debug)

```bash
ssh cedrick 'cd /opt/keyhome && docker compose --profile debug up -d pgadmin'
```

→ Accessible via `https://pgadmin.keyhome.app`

Login : `PGADMIN_EMAIL` / `PGADMIN_PASSWORD` (depuis le `.env` prod)

> **Sécurité** : pgAdmin et Grafana passent par Traefik (HTTPS). Ne pas exposer directement. Activer uniquement le temps du diagnostic, puis `docker compose stop pgadmin`.

### 5.3 Variables `.env` nécessaires pour le monitoring

```env
GRAFANA_DOMAIN=grafana.keyhome.app
GRAFANA_PASSWORD=<mot_de_passe_fort>
PGADMIN_DOMAIN=pgadmin.keyhome.app
PGADMIN_EMAIL=admin@keyhome.app
PGADMIN_PASSWORD=<mot_de_passe_fort>
```

### 5.4 Arrêter le monitoring

```bash
ssh cedrick 'cd /opt/keyhome && docker compose --profile monitoring stop'
ssh cedrick 'cd /opt/keyhome && docker compose --profile debug stop pgadmin'
```

---

## 8. Gestion de la base de données

### 8.1 Renommer la DB prod (immoapp → keyhome_prod)

> ⚠️ **Interruption de service ~30 secondes.** Faire un backup avant.

```bash
# 0. Backup manuel avant de toucher quoi que ce soit
ssh cedrick 'docker exec keyhome-prod-db-backup-local /bin/sh -c "backup.sh" || true'

# 1. Stopper les services applicatifs (PAS la DB)
ssh cedrick 'cd /opt/keyhome && docker compose stop app worker reverb worker-tours web nightwatch-agent pgbouncer'

# 2. Renommer la DB (plus aucune connexion active)
ssh cedrick 'docker exec keyhome-prod-db psql -U cedrick -c "ALTER DATABASE immoapp RENAME TO keyhome_prod;"'

# 3. Mettre à jour le .env
ssh cedrick 'sed -i "s/^DB_DATABASE=immoapp/DB_DATABASE=keyhome_prod/" /opt/keyhome/.env'

# 4. Vérifier
ssh cedrick 'grep "^DB_DATABASE" /opt/keyhome/.env'
# → DB_DATABASE=keyhome_prod

# 5. Redémarrer
ssh cedrick 'cd /opt/keyhome && docker compose up -d'
```

### 8.2 Convention de nommage des bases

| Environnement | Nom de la DB |
|---|---|
| Production | `keyhome_prod` |
| Preprod / Staging | `keyhome_preprod` |
| Tests CI | `testing` |

### 8.3 Connexion à PostgreSQL (depuis le VPS)

```bash
# Via psql direct (dans le conteneur)
docker exec -it keyhome-prod-db psql -U cedrick -d keyhome_prod

# Lister toutes les bases
docker exec keyhome-prod-db psql -U cedrick -l

# Vérifier l'état via pgbouncer
docker exec keyhome-prod-pgbouncer psql -h 127.0.0.1 -p 5432 -U cedrick -d keyhome_prod -c "SELECT 1"
```

### 8.4 Read Replica (optionnel — futur)

La replica est derrière le profil `--profile replica`. Tant qu'elle n'est pas bootstrappée, garder `DB_HOST_READ=db` dans le `.env` pour renvoyer toutes les lectures sur le primaire.

**Setup one-time (quand la replica sera activée) :**

```bash
# Sur le primaire : activer WAL + créer l'utilisateur de réplication
docker exec keyhome-prod-db psql -U cedrick -c "ALTER SYSTEM SET wal_level = replica;"
docker exec keyhome-prod-db psql -U cedrick -c "SELECT pg_reload_conf();"
docker exec keyhome-prod-db psql -U cedrick -c "CREATE ROLE replicator WITH REPLICATION LOGIN PASSWORD 'STRONG_PASS';"

# Démarrer la replica
ssh cedrick 'cd /opt/keyhome && docker compose --profile replica up -d db-replica'

# Bootstrapper (depuis le conteneur replica)
docker exec keyhome-prod-db-replica sh -c \
  "PGPASSWORD=STRONG_PASS pg_basebackup -h db -U replicator -D /var/lib/postgresql/data -P -Xs -R"

# Après bootstrap : mettre à jour le .env
# DB_HOST_READ=db-replica
```

### 8.5 Règle migration — contraintes CHECK

> **Ne jamais oublier** : PostgreSQL applique les contraintes CHECK immédiatement (pas à la fin de la transaction). Si une migration doit normaliser des données ET modifier une contrainte, toujours **`DROP CONSTRAINT` AVANT le `UPDATE`**.

```php
// ✅ Ordre correct
DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_xxx_check');
DB::statement('UPDATE payments SET ...'); // maintenant sans contrainte
DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_xxx_check CHECK (...)');

// ❌ Ordre bugué (le UPDATE échoue si les nouvelles valeurs violent l'ancienne contrainte)
DB::statement('UPDATE payments SET ...');
DB::statement('ALTER TABLE payments DROP CONSTRAINT ...');
```

### 8.6 Backups automatiques

| Service | Fréquence | Rétention | Destination |
|---|---|---|---|
| `keyhome-prod-db-backup-local` | Tous les jours à 03h00 UTC | 7j quotidiens, 4 semaines, 6 mois | Volume Docker local |
| `keyhome-prod-db-backup-s3` | Tous les jours | idem | Cloudflare R2 |

```bash
# Backup manuel immédiat
ssh cedrick 'docker exec keyhome-prod-db-backup-local /bin/sh -c "backup.sh"'

# Lister les backups locaux
ssh cedrick 'ls -lh /var/lib/docker/volumes/keyhome_keyhome-db-backups/_data/'
```

---

## 9. Pipeline CI/CD — fonctionnement détaillé

### 9.1 Stages

```
prepare → quality → build_and_test → deploy → smoke_test → notify → cleanup
```

| Stage | Jobs | Branches |
|---|---|---|
| `prepare` | `prepare_ci_image` | Toutes |
| `quality` | `phpstan`, `style_check`, `composer_security`, `rector` | Toutes (sur changements PHP) |
| `build_and_test` | `build_image` + `test_suite` (parallèles) | `main`/`preprod` pour build ; toutes pour tests |
| `deploy` | `production_deploy` / `preprod_deploy` | `main` / `preprod` |
| `smoke_test` | `production_smoke_test` / `preprod_smoke_test` | `main` / `preprod` |
| `notify` | `slack_success` / `slack_failure` | `main`/`preprod` |
| `cleanup` | `cleanup` | Toutes |

### 9.2 Image CI vs Image App

| Image | Tag | Contenu | Pushée quand |
|---|---|---|---|
| `…/ci:<branch>` | `ci:main`, `ci:preprod` | PHP extensions + Composer + vendor | Seulement `main`/`preprod` |
| `…/app:<branch>` | `app:main`, `app:preprod` | Image complète de l'app | Seulement `main`/`preprod` |

> Les branches feature **buildent** l'image CI localement sur le runner (pour les jobs quality/tests) mais **ne poussent PAS** au registry → évite ~1.4 GB de bruit par branche.

### 9.3 Étapes du déploiement (template partagé)

```
1. Copier docker-compose.yml + nginx conf
2. git clone / git pull du codebase sur le VPS
3. php artisan down (maintenance mode)
4. docker compose pull (nouvelle image)
5. docker compose up -d --remove-orphans
6. Attente healthcheck app (60 tentatives × 2s)
7. Recréer le conteneur web (pour reset DNS FastCGI nginx)
8. php artisan migrate --force
   → En cas d'échec : rollback automatique vers l'image précédente
9. php artisan optimize:clear + optimize
10. php artisan storage:link
11. scout:sync-index-settings
12. php artisan up (désactive maintenance mode)
13. l5-swagger:generate (non-bloquant)
```

### 9.4 Rollback automatique

Le CI sauvegarde l'image courante avant le deploy. Si les migrations échouent, il redémarre avec l'ancienne image automatiquement.

```bash
# Rollback manuel si nécessaire
PREVIOUS_IMAGE="registry.gitlab.com/neocraft/immoapp-backend/app:<sha>"
cd /opt/keyhome
APP_IMAGE=$PREVIOUS_IMAGE docker compose up -d --no-build --remove-orphans
APP_IMAGE=$PREVIOUS_IMAGE docker compose exec -T app php artisan up
```

### 9.5 Smoke test

Le smoke test prod fait un GET sur `https://api.keyhome.app/api/ping` — attend un HTTP 200 (10 tentatives × 5s).

Le smoke test preprod fait un GET sur `http://127.0.0.1:9091/api/ping`.

---

## 10. Commandes quotidiennes

### Prod — statut

```bash
ssh cedrick 'cd /opt/keyhome && docker compose ps'
ssh cedrick 'docker compose -f /opt/keyhome/docker-compose.yml logs --tail=50 app'
ssh cedrick 'docker compose -f /opt/keyhome/docker-compose.yml logs --tail=50 worker'
```

### Artisan prod

```bash
ssh cedrick 'docker exec keyhome-prod-app php artisan migrate:status'
ssh cedrick 'docker exec keyhome-prod-app php artisan tinker'
ssh cedrick 'docker exec keyhome-prod-app php artisan queue:failed'
ssh cedrick 'docker exec keyhome-prod-app php artisan queue:flush'
```

### Preprod — statut

```bash
ssh cedrick 'cd /opt/keyhome-preprod && docker compose ps'
ssh cedrick 'docker exec keyhome-preprod-backend php artisan migrate:status'
```

### Libérer de l'espace disque

```bash
# Images dangling
ssh cedrick 'docker image prune -f'

# Build cache (garde 10 GB)
ssh cedrick 'docker builder prune -f --keep-storage=10GB'

# Containers arrêtés depuis plus d'1h
ssh cedrick 'docker container prune -f --filter "until=1h"'

# Vue d'ensemble
ssh cedrick 'docker system df'
```

---

## 11. Rollback manuel

```bash
# 1. Voir les images disponibles dans le registry
# GitLab → Packages → Container Registry → app

# 2. Déployer une image spécifique
ssh cedrick '
  cd /opt/keyhome
  APP_IMAGE="registry.gitlab.com/neocraft/immoapp-backend/app:main" \
  docker compose pull
  APP_IMAGE="registry.gitlab.com/neocraft/immoapp-backend/app:main" \
  docker compose up -d --no-build --remove-orphans
'

# 3. Si la migration a déjà été appliquée et doit être revertée
ssh cedrick 'docker exec keyhome-prod-app php artisan migrate:rollback --step=1'
```

---

## 12. Dépannage

### Le conteneur `app` ne démarre pas

```bash
ssh cedrick 'docker compose -f /opt/keyhome/docker-compose.yml logs app --tail=100'
ssh cedrick 'docker compose -f /opt/keyhome/docker-compose.yml ps -a'
```

Causes fréquentes :
- **`APP_KEY` vide** → `docker exec keyhome-prod-app php artisan key:generate`
- **pgbouncer pas prêt** → attendre 30s après `docker compose up`, le healthcheck fait 10 retries
- **permissions storage** → `docker exec keyhome-prod-app chmod -R 775 storage bootstrap/cache`

### pgbouncer unhealthy

```bash
ssh cedrick 'docker logs keyhome-prod-pgbouncer --tail=30'
# Le healthcheck utilise bash /dev/tcp (pas pg_isready qui n'est pas dispo dans l'image)
# Si le problème persiste :
ssh cedrick 'docker restart keyhome-prod-pgbouncer'
```

### Migration échoue — violation de contrainte CHECK

> **Root cause connue** : si une migration fait un `UPDATE` avant de `DROP CONSTRAINT`, PostgreSQL rejette les nouvelles valeurs même dans la même transaction.
> **Fix** : toujours `DROP CONSTRAINT` en premier, puis `UPDATE`, puis `ADD CONSTRAINT`. Voir section 8.5.

### Le preprod ne se connecte pas à la DB

```bash
# Vérifier que le réseau prod existe
ssh cedrick 'docker network inspect keyhome_keyhome-network'

# Vérifier la connectivité depuis le conteneur preprod
ssh cedrick 'docker exec keyhome-preprod-backend ping keyhome-prod-db -c 3'

# Créer la base si absente
ssh cedrick 'docker exec keyhome-prod-db psql -U cedrick -c "CREATE DATABASE keyhome_preprod"'
```

### Redis : données preprod polluent la prod ?

Non. La clé `REDIS_PREFIX=keyhome_preprod_database_` dans le `.env` preprod préfixe tout.

```bash
# Vérifier les clés preprod
ssh cedrick 'docker exec keyhome-prod-redis redis-cli KEYS "keyhome_preprod_*" | head -10'
```

### Conflit de ports

```bash
ssh cedrick 'ss -tlnp | grep -E "9090|9091"'
```

### Espace disque critique sur le VPS

```bash
ssh cedrick 'df -h'
ssh cedrick 'docker system df'
# Nettoyage agressif (attention aux volumes !)
ssh cedrick 'docker system prune -f --volumes=false'
ssh cedrick 'docker builder prune -f'
```

---

## 13. Checklist nouveau VPS

```
□ Docker + Docker Compose v2 installés
□ GitLab Runner installé, tag = self-hosted-shell, executor = shell
□ gitlab-runner dans le groupe docker
□ Traefik installé et connecté à traefik-public
□ DNS configurés (api.keyhome.app, reverb.keyhome.app, etc.)
□ Réseau traefik-public créé
□ /opt/keyhome/ créé avec .env complet (voir Phase 2)
□ /opt/keyhome-preprod/ créé avec .env complet (voir Phase 3)
□ .docker/nginx/conf.d/ présents dans les deux répertoires
  (copié automatiquement par le CI à chaque deploy)
□ Variables CI/CD configurées dans GitLab (voir Phase 4)
□ Branches main et preprod protégées dans GitLab
□ Premier deploy prod : push sur main ou merge preprod→main
□ Premier deploy preprod : push sur preprod
□ DB keyhome_preprod créée sur le serveur PostgreSQL prod
□ Smoke tests prod et preprod passent
□ Notifications Slack reçues dans les bons canaux
□ DB renommée immoapp → keyhome_prod (voir section 8.1)
□ Monitoring optionnel activé si besoin (section 5.1)
```

---

## Annexe — Flux Git / déploiement

```
feature/xyz
     │
     ▼ merge request
  preprod  ──────────────► preprod_deploy ──► /opt/keyhome-preprod/
     │                      smoke test
     ▼ merge request
   main    ──────────────► production_deploy ──► /opt/keyhome/
                            smoke test
                            Slack 🚀
```

**Règle d'or :** on ne push jamais directement sur `main`. Toujours passer par `preprod` → tester → merger.
