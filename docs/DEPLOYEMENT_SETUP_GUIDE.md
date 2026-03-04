# Guide de configuration — Keyhome CI/CD & Infrastructure

---

## PHASE 1 — VPS : Préparer les dossiers et fichiers .env

### 1.1 Créer la structure prod
```bash
mkdir -p /opt/keyhome/.docker/nginx/conf.d
```

### 1.2 Créer le `.env` prod
```bash
nano /opt/keyhome/.env
```
Variables minimales à renseigner :
```env
# App
APP_ENV=production
APP_KEY=                        # généré automatiquement par le pipeline
APP_DOMAIN=api.keyhome.app

# Base de données
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=keyhome
DB_USERNAME=postgres
DB_PASSWORD=              # mot de passe fort

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Meilisearch
MEILISEARCH_KEY=          # clé forte

# Nightwatch
NIGHTWATCH_TOKEN=         # depuis nightwatch.laravel.com

# Docker
COMPOSE_PREFIX=keyhome-prod
WEB_PORT=9090

# Slack (voir Phase 3)
SLACK_WEBHOOK_URL=
```

### 1.3 Créer la structure preprod
```bash
mkdir -p /opt/keyhome-preprod/.docker/nginx/conf.d
```

### 1.4 Créer le `.env` preprod
```bash
nano /opt/keyhome-preprod/.env
```
```env
# App
APP_ENV=staging
APP_KEY=
APP_DOMAIN=api.keyhome.neocraft.dev

# Base de données (instance séparée de la prod)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=keyhome_preprod
DB_USERNAME=postgres
DB_PASSWORD=              # peut être différent de la prod

# Redis / Meilisearch (idem, instances séparées)
REDIS_HOST=redis
MEILISEARCH_KEY=

# Nightwatch
NIGHTWATCH_TOKEN=

# Docker
COMPOSE_PREFIX=keyhome-preprod
WEB_PORT=9091

# Slack
SLACK_WEBHOOK_URL=
```

---

## PHASE 2 — Traefik : Vérifier la configuration réseau

### 2.1 S'assurer que le réseau `traefik-public` existe
```bash
docker network ls | grep traefik-public
# Si absent :
docker network create traefik-public
```

### 2.2 Vérifier que Traefik écoute bien sur les entrypoints `web` et `websecure`
Dans ta config Traefik (`traefik.yml` ou labels), tu dois avoir :
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
      email: ton@email.com
      storage: /acme.json
      httpChallenge:
        entryPoint: web
```

### 2.3 S'assurer que Traefik est bien connecté au réseau `traefik-public`
```bash
docker inspect traefik | grep -A5 Networks
```

---

## PHASE 3 — Slack : Créer les webhooks

### 3.1 Créer deux canaux dans ton workspace Slack
- `#deploy-prod` — alertes production
- `#deploy-preprod` — alertes preprod / staging

### 3.2 Créer les webhooks entrants
1. Aller sur https://api.slack.com/apps
2. Créer une app (ou utiliser une existante)
3. Activer **Incoming Webhooks**
4. Cliquer **Add New Webhook to Workspace**
5. Sélectionner `#deploy-prod` → copier l'URL → c'est `SLACK_WEBHOOK_PROD`
6. Répéter pour `#deploy-preprod` → c'est `SLACK_WEBHOOK_PREPROD`

---

## PHASE 4 — GitLab : Configurer les variables CI/CD

Aller dans **GitLab → Ton projet → Settings → CI/CD → Variables**

### 4.1 Variables de registry Docker
| Variable | Valeur | Protected | Masked |
|---|---|---|---|
| `CI_REGISTRY_USER` | ton username GitLab registry | ✅ | ❌ |
| `CI_REGISTRY_PASSWORD` | token d'accès registry | ✅ | ✅ |

> Ces variables sont souvent auto-injectées par GitLab — vérifie si elles existent déjà.

### 4.2 Variables Slack
| Variable | Valeur | Protected | Masked |
|---|---|---|---|
| `SLACK_WEBHOOK_PROD` | URL webhook canal prod | ✅ | ✅ |
| `SLACK_WEBHOOK_PREPROD` | URL webhook canal preprod | ✅ | ✅ |

### 4.3 Variables d'URLs (pour smoke tests et notifications)
| Variable | Valeur | Protected | Masked |
|---|---|---|---|
| `PROD_API_URL` | `https://api.keyhome.app` | ✅ | ❌ |
| `PREPROD_API_URL` | `https://api.keyhome.neocraft.dev` | ✅ | ❌ |

### 4.4 Protéger les branches `main` et `preprod`
Aller dans **Settings → Repository → Protected Branches** :
- `main` → Allowed to push: Maintainers — Allowed to merge: Developers+Maintainers
- `preprod` → idem

---

## PHASE 5 — GitLab : Configurer le Runner

### 5.1 Vérifier que le runner self-hosted tourne sur le VPS
```bash
gitlab-runner status
```

### 5.2 Vérifier que le runner a le tag `self-hosted-shell`
```bash
gitlab-runner list
```
Si le tag est absent, éditer `/etc/gitlab-runner/config.toml` :
```toml
[[runners]]
  name = "keyhome-vps"
  tags = ["self-hosted-shell"]
  executor = "shell"
```
Puis redémarrer :
```bash
gitlab-runner restart
```

### 5.3 S'assurer que Docker est accessible depuis le runner
```bash
# L'utilisateur gitlab-runner doit être dans le groupe docker
sudo usermod -aG docker gitlab-runner
# Vérifier
sudo -u gitlab-runner docker ps
```

---

## PHASE 6 — Déployer les fichiers de config

### 6.1 Placer les fichiers dans le repo GitLab (à la racine)
```
├── docker-compose.yml           ← prod
├── docker-compose.preprod.yml   ← preprod
├── .gitlab-ci.yml
├── .docker/
│   └── nginx/
│       └── conf.d/
│           └── default.conf     ← config nginx
└── .env.preprod.example         ← template .env preprod (sans secrets)
```

### 6.2 Créer le `.env.preprod.example`
```bash
cp .env.example .env.preprod.example
# Retirer toutes les valeurs sensibles, garder uniquement les clés
```

---

## PHASE 7 — Premier déploiement

### 7.1 Premier déploiement preprod
```bash
# Sur le VPS, s'assurer que le .env est bien en place
ls -la /opt/keyhome-preprod/.env

# Pousser un commit sur la branche preprod
git push origin preprod
```
Vérifier dans GitLab → CI/CD → Pipelines que tout passe ✅

### 7.2 Premier déploiement prod
```bash
# S'assurer que le .env prod est en place
ls -la /opt/keyhome/.env

# Merger preprod dans main (ou pousser directement sur main)
git push origin main
```

### 7.3 Vérifications post-déploiement
```bash
# Sur le VPS — vérifier les conteneurs prod
cd /opt/keyhome && docker compose ps

# Vérifier les conteneurs preprod
cd /opt/keyhome-preprod && docker compose ps

# Tester les URLs manuellement
curl -I https://api.keyhome.app/up
curl -I https://api.keyhome.neocraft.dev/up
```

---

## PHASE 8 — Vérifications finales

| Check | Commande / Action |
|---|---|
| Certificat SSL prod valide | `curl -vI https://api.keyhome.app/up` |
| Certificat SSL preprod valide | `curl -vI https://api.keyhome.neocraft.dev/up` |
| Notification Slack succès reçue | Vérifier `#deploy-prod` et `#deploy-preprod` |
| Logs nginx OK | `docker logs keyhome-prod-web` |
| Logs app OK | `docker logs keyhome-prod-backend` |
| Worker tourne | `docker logs keyhome-prod-worker` |
| Cache registry CI actif | Vérifier dans GitLab → Packages → Container Registry → `ci-cache` |

---

## Résumé des dépendances entre phases

```
Phase 1 (VPS .env)
    ↓
Phase 2 (Traefik réseau)
    ↓
Phase 3 (Slack webhooks)
    ↓
Phase 4 (GitLab variables)  ←→  Phase 5 (Runner)
    ↓
Phase 6 (Fichiers repo)
    ↓
Phase 7 (Premier déploiement)
    ↓
Phase 8 (Vérifications)
```
