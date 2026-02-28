# Guide de Configuration Preprod

## Architecture

Le preprod partage les services d'infrastructure (PostgreSQL, Redis, Meilisearch) avec la production pour économiser les ressources. Seuls les services applicatifs (app, worker, web, nightwatch) sont dupliqués.

```
PROD (/opt/keyhome/)                    PREPROD (/opt/keyhome-preprod/)
┌──────────────────────┐                ┌──────────────────────┐
│  keyhome-backend     │                │  keyhome-preprod-    │
│  keyhome-worker      │                │       backend        │
│  keyhome-web (:9090) │                │  keyhome-preprod-    │
│  keyhome-nightwatch  │                │       worker         │
│                      │                │  keyhome-preprod-    │
│  keyhome-db ─────────┼── réseau ──────│       web (:9091)    │
│  keyhome-redis ──────┼── partagé ─────│  keyhome-preprod-    │
│  keyhome-meilisearch─┼───────────────▶│       nightwatch     │
└──────────────────────┘                └──────────────────────┘
```

### Isolation des données

| Service | Production | Preprod |
|---|---|---|
| PostgreSQL | `keyhome` | `keyhome_preprod` |
| Redis | prefix par défaut | `keyhome_preprod_database_` |
| Meilisearch | index normaux | `SCOUT_PREFIX=preprod_` |
| Storage | `keyhome-preprod_preprod-storage-data` | volume séparé |

### Fichiers clés

| Fichier | Rôle |
|---|---|
| `docker-compose.yml` | Stack complète production (DB, Redis, etc.) |
| `docker-compose.preprod.yml` | Services applicatifs preprod uniquement |
| `.env.preprod.example` | Template `.env` pour le preprod |
| `.gitlab-ci.yml` | Pipeline CI/CD (deploy auto prod + preprod) |

---

## Configuration initiale sur le VPS

### 1. Créer le répertoire

```bash
mkdir -p /opt/keyhome-preprod/.docker/nginx/conf.d
```

### 2. Configurer le `.env`

```bash
cp /opt/keyhome/codebase/.env.preprod.example /opt/keyhome-preprod/.env
nano /opt/keyhome-preprod/.env
```

**Variables à adapter obligatoirement :**

| Variable | Valeur |
|---|---|
| `APP_KEY` | Généré au premier deploy (`php artisan key:generate`) |
| `DB_PASSWORD` | Même que prod (même PostgreSQL) |
| `MEILISEARCH_KEY` | Même que prod (même Meilisearch) |
| `APP_IMAGE` | `registry.gitlab.com/<namespace>/<projet>/app:preprod` |

### 3. Configurer le DNS

Ajouter un enregistrement A :

```
preprod-api.keyhome.neocraft.dev  →  <IP du VPS>
```

### 4. Configurer Traefik

#### Option A — Via fichier de configuration dynamique

```yaml
# /etc/traefik/dynamic/keyhome-preprod.yml
http:
  routers:
    keyhome-preprod:
      rule: "Host(`preprod-api.keyhome.neocraft.dev`)"
      entryPoints:
        - websecure
      service: keyhome-preprod
      tls:
        certResolver: letsencrypt

  services:
    keyhome-preprod:
      loadBalancer:
        servers:
          - url: "http://localhost:9091"
```

#### Option B — Via labels Docker

Ajouter `labels` au service `web` dans `docker-compose.preprod.yml` :

```yaml
web:
  labels:
    - "traefik.enable=true"
    - "traefik.http.routers.keyhome-preprod.rule=Host(`preprod-api.keyhome.neocraft.dev`)"
    - "traefik.http.routers.keyhome-preprod.entrypoints=websecure"
    - "traefik.http.routers.keyhome-preprod.tls.certresolver=letsencrypt"
    - "traefik.http.services.keyhome-preprod.loadbalancer.server.port=80"
```

### 5. Vérifier le réseau prod

```bash
docker network inspect keyhome_keyhome-network
```

La prod doit tourner avant le preprod (le réseau est créé par la stack prod).

---

## Pipeline CI/CD

### Flux de déploiement

```
preprod branch                          main branch
     │                                       │
     ▼                                       ▼
 quality ✓                               quality ✓
     │                                       │
 build → Registry (app:preprod)          build → Registry (app:main)
 tests ✓                                 tests ✓
     │                                       │
 deploy preprod                          deploy production
 /opt/keyhome-preprod/                   /opt/keyhome/
 port 9091                               port 9090
     │                                       │
 smoke test preprod                      smoke test prod
     │                                       │
 Slack "🧪 Preprod"                      Slack "🚀 Production"
```

### Workflow recommandé

1. Développer sur une branche feature
2. Merge dans `preprod` → déploiement auto sur le preprod
3. Tester sur `preprod-api.keyhome.neocraft.dev`
4. Merge `preprod` → `main` → déploiement auto en production

### Variables CI/CD GitLab

Dans **GitLab > Settings > CI/CD > Variables** :

| Variable | Valeur | Scope |
|---|---|---|
| `API_BASE_URL` | `https://api.keyhome.neocraft.dev` | `main` |
| `PREPROD_API_BASE_URL` | `https://api.keyhome.neocraft.dev` | `preprod` |
| `SLACK_WEBHOOK_URL` | URL Slack webhook | Tous |

---

## Commandes utiles

### Voir les containers preprod

```bash
cd /opt/keyhome-preprod
docker compose ps
```

### Logs preprod

```bash
cd /opt/keyhome-preprod
docker compose logs -f app
docker compose logs -f worker
```

### Artisan sur le preprod

```bash
cd /opt/keyhome-preprod
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker
```

### Redémarrer le preprod

```bash
cd /opt/keyhome-preprod
docker compose restart
```

### Arrêter le preprod (sans toucher à la prod)

```bash
cd /opt/keyhome-preprod
docker compose down
```

### Vérifier la base preprod depuis le container prod

```bash
docker exec keyhome-db psql -U postgres -l | grep preprod
```

---

## Dépannage

### Le preprod ne démarre pas

1. **Vérifier que la prod tourne** (réseau partagé nécessaire) :
   ```bash
   docker network inspect keyhome_keyhome-network
   ```

2. **Vérifier le `.env`** :
   ```bash
   cat /opt/keyhome-preprod/.env | grep -E 'DB_HOST|REDIS_HOST|MEILISEARCH_HOST'
   # Doit afficher : keyhome-db, keyhome-redis, keyhome-meilisearch
   ```

3. **Vérifier les logs** :
   ```bash
   cd /opt/keyhome-preprod && docker compose logs app --tail=50
   ```

### La base `keyhome_preprod` n'existe pas

Le CI la crée automatiquement au premier deploy. Pour la créer manuellement :

```bash
docker exec keyhome-db psql -U postgres -c "CREATE DATABASE keyhome_preprod"
docker exec keyhome-db psql -U postgres -d keyhome_preprod -c "CREATE EXTENSION IF NOT EXISTS postgis"
```

### Conflit de ports

Vérifier qu'aucun autre service n'utilise le port 9091 :

```bash
ss -tlnp | grep 9091
```

### Redis : les données preprod polluent-elles la prod ?

Non. Le preprod utilise `REDIS_PREFIX=keyhome_preprod_database_` dans son `.env`, ce qui préfixe toutes les clés. Pour vérifier :

```bash
docker exec keyhome-redis redis-cli KEYS "keyhome_preprod_*" | head -5
```
