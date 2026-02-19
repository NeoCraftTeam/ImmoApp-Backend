# 🐳 Docker Compose Complet avec Traefik

> **Configuration production-ready** pour KeyHome sur nouveau serveur  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 📋 Structure des fichiers

```
/var/www/keyhome/
├── docker-compose.yml              # ← Configuration principale
├── docker-compose.traefik.yml      # ← Traefik séparé
├── .env                            # ← Variables d'environnement
├── traefik/
│   └── traefik.yml                 # ← Config statique Traefik
└── .docker/
    ├── nginx/
    │   └── conf.d/
    │       └── default.conf        # ← Config Nginx (optionnel avec Traefik)
    ├── php/
    │   ├── php.ini
    │   └── opcache.ini
    └── monitoring/
        ├── prometheus/
        │   └── prometheus.yml
        └── grafana/
            └── provisioning/
```

---

## 🚀 docker-compose.traefik.yml

**Fichier dédié pour Traefik** (à lancer en premier).

```yaml
# docker-compose.traefik.yml
version: '3.8'

services:
  traefik:
    image: traefik:v3.0
    container_name: traefik
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    networks:
      - traefik-public
    ports:
      - "80:80"
      - "443:443"
      # Port API/Dashboard (optionnel, déjà exposé via HTTPS)
      # - "8080:8080"
    environment:
      - TZ=Europe/Paris
    volumes:
      # Socket Docker (lecture seule pour sécurité)
      - /var/run/docker.sock:/var/run/docker.sock:ro
      
      # Configuration Traefik
      - ./traefik/traefik.yml:/etc/traefik/traefik.yml:ro
      
      # Certificats SSL (persistent)
      - traefik-certificates:/letsencrypt
      
      # Logs
      - traefik-logs:/var/log/traefik
    labels:
      # Activer Traefik pour lui-même
      - "traefik.enable=true"
      
      # === DASHBOARD TRAEFIK ===
      - "traefik.http.routers.dashboard.rule=Host(`dashboard.keyhome.neocraft.dev`)"
      - "traefik.http.routers.dashboard.entrypoints=websecure"
      - "traefik.http.routers.dashboard.tls.certresolver=letsencrypt"
      - "traefik.http.routers.dashboard.service=api@internal"
      
      # Authentification basique (à personnaliser)
      - "traefik.http.routers.dashboard.middlewares=dashboard-auth"
      - "traefik.http.middlewares.dashboard-auth.basicauth.users=admin:$$apr1$$8EVjn/nj$$GiLUZqcbueTFeD23SuB6x0"
      
      # Rate limiting (protection DDoS)
      - "traefik.http.middlewares.rate-limit.ratelimit.average=100"
      - "traefik.http.middlewares.rate-limit.ratelimit.burst=50"

networks:
  traefik-public:
    external: true

volumes:
  traefik-certificates:
    driver: local
  traefik-logs:
    driver: local
```

---

## 🏗️ docker-compose.yml (Principal)

**Configuration complète de l'application KeyHome**.

```yaml
# docker-compose.yml
version: '3.8'

services:
  # === CODE SYNC (Bootstrap) ===
  code-sync:
    image: ${APP_IMAGE:-keyhome-backend}
    container_name: keyhome-code-sync
    command: sh -c "cp -rp /var/www/. /shared-code/ && chmod -R 755 /shared-code && chown -R 1000:1000 /shared-code"
    volumes:
      - keyhome-app-code:/shared-code
    networks:
      - keyhome-network

  # === APPLICATION PHP-FPM ===
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: ${APP_IMAGE:-keyhome-backend}
    container_name: keyhome-backend
    restart: unless-stopped
    working_dir: /var/www
    depends_on:
      code-sync:
        condition: service_completed_successfully
      db:
        condition: service_started
      redis:
        condition: service_started
    env_file:
      - .env
    volumes:
      - keyhome-app-code:/var/www
      - keyhome-storage-data:/var/www/storage
    networks:
      - keyhome-network
      - traefik-public
    environment:
      - DB_HOST=db
      - REDIS_HOST=redis
      - APP_ENV=production
    # ⚠️ PAS de ports exposés - Traefik gère tout
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route principale : keyhome.neocraft.dev
      - "traefik.http.routers.keyhome-app.rule=Host(`keyhome.neocraft.dev`)"
      - "traefik.http.routers.keyhome-app.entrypoints=websecure"
      - "traefik.http.routers.keyhome-app.tls.certresolver=letsencrypt"
      - "traefik.http.routers.keyhome-app.service=keyhome-app"
      - "traefik.http.services.keyhome-app.loadbalancer.server.port=9000"

  # === WORKER QUEUE ===
  worker:
    image: ${APP_IMAGE:-keyhome-backend}
    container_name: keyhome-worker
    restart: unless-stopped
    working_dir: /var/www
    command: php artisan queue:work --queue=emails,default --tries=3 --timeout=90 --sleep=3 --max-jobs=1000
    depends_on:
      - db
      - redis
      - app
    env_file:
      - .env
    volumes:
      - keyhome-app-code:/var/www
      - keyhome-storage-data:/var/www/storage
    networks:
      - keyhome-network
    environment:
      - DB_HOST=db
      - REDIS_HOST=redis

  # === SERVEUR WEB NGINX ===
  web:
    image: nginx:alpine
    container_name: keyhome-web
    restart: unless-stopped
    depends_on:
      app:
        condition: service_started
    # ⚠️ PAS de ports exposés - Traefik se charge du routage
    volumes:
      - keyhome-app-code:/var/www
      - keyhome-storage-data:/var/www/storage
      - .docker/nginx/conf.d:/etc/nginx/conf.d
    networks:
      - keyhome-network
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route API : api.keyhome.neocraft.dev
      - "traefik.http.routers.keyhome-api.rule=Host(`api.keyhome.neocraft.dev`)"
      - "traefik.http.routers.keyhome-api.entrypoints=websecure"
      - "traefik.http.routers.keyhome-api.tls.certresolver=letsencrypt"
      - "traefik.http.routers.keyhome-api.service=keyhome-api"
      - "traefik.http.services.keyhome-api.loadbalancer.server.port=80"
      
      # Middleware CORS (optionnel)
      - "traefik.http.routers.keyhome-api.middlewares=cors-headers"
      - "traefik.http.middlewares.cors-headers.headers.accesscontrolallowmethods=GET,OPTIONS,PUT,POST,DELETE,PATCH"
      - "traefik.http.middlewares.cors-headers.headers.accesscontrolalloworiginlist=https://keyhome.neocraft.dev,https://preview.keyhome.neocraft.dev"
      - "traefik.http.middlewares.cors-headers.headers.accesscontrolmaxage=100"
      - "traefik.http.middlewares.cors-headers.headers.addvaryheader=true"

  # === BASE DE DONNÉES POSTGRESQL + POSTGIS ===
  db:
    image: postgis/postgis:15-3.3-alpine
    container_name: keyhome-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-keyhome}
      POSTGRES_USER: ${DB_USERNAME:-postgres}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-ChangeMe123!}
      # Timezone
      TZ: Europe/Paris
      PGTZ: Europe/Paris
    volumes:
      - keyhome-db-data:/var/lib/postgresql/data
    networks:
      - keyhome-network
    # Performance tuning (optionnel)
    command: >
      postgres
      -c shared_buffers=256MB
      -c effective_cache_size=1GB
      -c maintenance_work_mem=64MB
      -c checkpoint_completion_target=0.9
      -c wal_buffers=16MB
      -c default_statistics_target=100
      -c random_page_cost=1.1
      -c effective_io_concurrency=200
      -c work_mem=2621kB
      -c min_wal_size=1GB
      -c max_wal_size=4GB

  # === CACHE REDIS ===
  redis:
    image: redis:7-alpine
    container_name: keyhome-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD:-redis123}
    volumes:
      - keyhome-redis-data:/data
    networks:
      - keyhome-network

  # === MOTEUR DE RECHERCHE MEILISEARCH ===
  meilisearch:
    image: getmeili/meilisearch:v1.10
    container_name: keyhome-meilisearch
    restart: unless-stopped
    environment:
      - MEILI_MASTER_KEY=${MEILISEARCH_KEY:-masterKeyChangeMe}
      - MEILI_ENV=production
      - MEILI_HTTP_ADDR=0.0.0.0:7700
    volumes:
      - keyhome-meilisearch-data:/meili_data
    networks:
      - keyhome-network
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route Meilisearch : search.keyhome.neocraft.dev
      - "traefik.http.routers.meilisearch.rule=Host(`search.keyhome.neocraft.dev`)"
      - "traefik.http.routers.meilisearch.entrypoints=websecure"
      - "traefik.http.routers.meilisearch.tls.certresolver=letsencrypt"
      - "traefik.http.services.meilisearch.loadbalancer.server.port=7700"

  # === PGADMIN (GESTION DB) ===
  pgadmin:
    image: dpage/pgadmin4
    container_name: keyhome-pgadmin
    restart: unless-stopped
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_EMAIL:-admin@keyhome.dev}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_PASSWORD:-admin}
      PGADMIN_CONFIG_SERVER_MODE: 'True'
      PGADMIN_CONFIG_MASTER_PASSWORD_REQUIRED: 'True'
    networks:
      - keyhome-network
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route PgAdmin : pgadmin.keyhome.neocraft.dev
      - "traefik.http.routers.pgadmin.rule=Host(`pgadmin.keyhome.neocraft.dev`)"
      - "traefik.http.routers.pgadmin.entrypoints=websecure"
      - "traefik.http.routers.pgadmin.tls.certresolver=letsencrypt"
      - "traefik.http.services.pgadmin.loadbalancer.server.port=80"

  # === MONITORING : PROMETHEUS ===
  prometheus:
    image: prom/prometheus:latest
    container_name: keyhome-prometheus
    restart: unless-stopped
    volumes:
      - .docker/monitoring/prometheus:/etc/prometheus
      - keyhome-prometheus-data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/usr/share/prometheus/console_libraries'
      - '--web.console.templates=/usr/share/prometheus/consoles'
      - '--storage.tsdb.retention.time=30d'
    networks:
      - keyhome-network
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route Prometheus : prometheus.keyhome.neocraft.dev
      - "traefik.http.routers.prometheus.rule=Host(`prometheus.keyhome.neocraft.dev`)"
      - "traefik.http.routers.prometheus.entrypoints=websecure"
      - "traefik.http.routers.prometheus.tls.certresolver=letsencrypt"
      - "traefik.http.services.prometheus.loadbalancer.server.port=9090"
      
      # Protection basique (optionnel)
      - "traefik.http.routers.prometheus.middlewares=prometheus-auth"
      - "traefik.http.middlewares.prometheus-auth.basicauth.users=admin:$$apr1$$8EVjn/nj$$GiLUZqcbueTFeD23SuB6x0"

  # === MONITORING : GRAFANA ===
  grafana:
    image: grafana/grafana:latest
    container_name: keyhome-grafana
    restart: unless-stopped
    volumes:
      - keyhome-grafana-data:/var/lib/grafana
      - .docker/monitoring/grafana/provisioning:/etc/grafana/provisioning
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD:-admin}
      - GF_SERVER_ROOT_URL=https://grafana.keyhome.neocraft.dev
      - GF_INSTALL_PLUGINS=redis-datasource
    networks:
      - keyhome-network
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route Grafana : grafana.keyhome.neocraft.dev
      - "traefik.http.routers.grafana.rule=Host(`grafana.keyhome.neocraft.dev`)"
      - "traefik.http.routers.grafana.entrypoints=websecure"
      - "traefik.http.routers.grafana.tls.certresolver=letsencrypt"
      - "traefik.http.services.grafana.loadbalancer.server.port=3000"

  # === EXPORTERS POUR MONITORING ===
  
  node-exporter:
    image: prom/node-exporter:latest
    container_name: keyhome-node-exporter
    restart: unless-stopped
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.sysfs=/host/sys'
      - '--collector.filesystem.mount-points-exclude=^/(sys|proc|dev|host|etc)($$|/)'
    networks:
      - keyhome-network

  cadvisor:
    image: gcr.io/cadvisor/cadvisor:latest
    container_name: keyhome-cadvisor
    restart: unless-stopped
    volumes:
      - /:/rootfs:ro
      - /var/run:/var/run:rw
      - /sys:/sys:ro
      - /var/lib/docker/:/var/lib/docker:ro
    networks:
      - keyhome-network

  postgres-exporter:
    image: prometheuscommunity/postgres-exporter
    container_name: keyhome-postgres-exporter
    restart: unless-stopped
    environment:
      DATA_SOURCE_NAME: "postgresql://${DB_USERNAME:-postgres}:${DB_PASSWORD:-password}@db:5432/${DB_DATABASE:-keyhome}?sslmode=disable"
    networks:
      - keyhome-network

  redis-exporter:
    image: oliver006/redis_exporter
    container_name: keyhome-redis-exporter
    restart: unless-stopped
    environment:
      - REDIS_ADDR=redis:6379
      - REDIS_PASSWORD=${REDIS_PASSWORD:-redis123}
    networks:
      - keyhome-network

# === RÉSEAUX ===
networks:
  keyhome-network:
    driver: bridge
  traefik-public:
    external: true

# === VOLUMES PERSISTANTS ===
volumes:
  keyhome-db-data:
    driver: local
  keyhome-app-code:
    driver: local
  keyhome-storage-data:
    driver: local
  keyhome-redis-data:
    driver: local
  keyhome-prometheus-data:
    driver: local
  keyhome-grafana-data:
    driver: local
  keyhome-meilisearch-data:
    driver: local
```

---

## 📝 Fichier .env (Production)

```env
# === APPLICATION ===
APP_NAME=KeyHome
APP_ENV=production
APP_KEY=base64:VotreCleDEchiffrementGenereeParLaravel
APP_DEBUG=false
APP_URL=https://keyhome.neocraft.dev

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

# === IMAGE DOCKER ===
APP_IMAGE=keyhome-backend:latest

# === BASE DE DONNÉES ===
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=keyhome
DB_USERNAME=postgres
DB_PASSWORD=VotreMotDePasseSecurise123!

# === CACHE & SESSION ===
SESSION_DRIVER=redis
SESSION_LIFETIME=10080
CACHE_STORE=redis

# === QUEUE ===
QUEUE_CONNECTION=redis

# === REDIS ===
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=VotreRedisPassword123!
REDIS_PORT=6379

# === MAIL ===
MAIL_MAILER=smtp
MAIL_HOST=mail.infomaniak.com
MAIL_PORT=465
MAIL_USERNAME=support@neocraft.dev
MAIL_PASSWORD=VotreMotDePasseMail
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@keyhome.neocraft.dev
MAIL_FROM_NAME="${APP_NAME}"

# === MEILISEARCH ===
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=VotreMasterKeySecure

# === MONITORING ===
GRAFANA_PASSWORD=VotreMotDePasseGrafana
PGADMIN_EMAIL=admin@keyhome.dev
PGADMIN_PASSWORD=VotreMotDePassePgAdmin

# === PAIEMENT FEDAPAY ===
FEDAPAY_PUBLIC_KEY=pk_live_VotreCle
FEDAPAY_SECRET_KEY=sk_live_VotreCleSecrete
FEDAPAY_ENVIRONMENT=live

# === SENTRY (OPTIONNEL) ===
SENTRY_LARAVEL_DSN=https://votre-dsn@sentry.io/project-id
SENTRY_TRACES_SAMPLE_RATE=0.1
```

---

## 🚀 Commandes de déploiement

### 1. Première installation

```bash
# Créer le réseau Traefik
docker network create traefik-public

# Lancer Traefik
docker compose -f docker-compose.traefik.yml up -d

# Attendre 10 secondes
sleep 10

# Lancer l'application
docker compose up -d

# Vérifier les logs
docker compose logs -f
```

### 2. Mise à jour (après git pull)

```bash
# Rebuild l'image
docker compose build app

# Redémarrer les services
docker compose up -d --force-recreate app worker

# Vider les caches
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```

### 3. Vérification santé

```bash
# Tous les conteneurs UP ?
docker compose ps

# Traefik détecte les services ?
docker logs traefik | grep "Adding route"

# Test SSL
curl -I https://keyhome.neocraft.dev
curl -I https://api.keyhome.neocraft.dev
curl -I https://grafana.keyhome.neocraft.dev
```

---

## 🌐 URLs finales

Après déploiement, les services seront accessibles via :

| Service | URL | Protection |
|---------|-----|------------|
| **App principale** | https://keyhome.neocraft.dev | Publique |
| **API** | https://api.keyhome.neocraft.dev | Auth Bearer |
| **Dashboard Traefik** | https://dashboard.keyhome.neocraft.dev | BasicAuth |
| **Grafana** | https://grafana.keyhome.neocraft.dev | Login |
| **Prometheus** | https://prometheus.keyhome.neocraft.dev | BasicAuth |
| **PgAdmin** | https://pgadmin.keyhome.neocraft.dev | Login |
| **Meilisearch** | https://search.keyhome.neocraft.dev | API Key |

---

## 📚 Ressources

- **Docker Compose Spec** : https://docs.docker.com/compose/compose-file/
- **Traefik Labels** : https://doc.traefik.io/traefik/routing/providers/docker/
- **Let's Encrypt** : https://letsencrypt.org/docs/

---

**Prochaine étape** : Lire `03-deploiement.md` pour le workflow de déploiement avec GitLab CI/CD.
