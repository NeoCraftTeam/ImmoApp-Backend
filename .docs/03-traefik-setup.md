# 🚀 Guide Complet Traefik pour KeyHome

> **Documentation basée sur** : [Traefik Official Documentation v3.0](https://doc.traefik.io/traefik/)  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 📋 Table des matières

1. [Qu'est-ce que Traefik ?](#introduction)
2. [Pourquoi Traefik pour KeyHome ?](#pourquoi-traefik)
3. [Architecture & Concepts](#architecture)
4. [Installation & Configuration](#installation)
5. [Configuration SSL automatique](#ssl-automatique)
6. [Multi-domaines & sous-domaines](#multi-domaines)
7. [Monitoring & Dashboard](#monitoring)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 Qu'est-ce que Traefik ?

**Traefik** est un **reverse proxy** et **load balancer** moderne conçu spécifiquement pour les environnements cloud-native et Docker.

### Caractéristiques principales

- ✅ **Auto-découverte** : Détecte automatiquement vos services Docker
- ✅ **SSL automatique** : Génère et renouvelle les certificats Let's Encrypt
- ✅ **Dashboard intégré** : Interface web de monitoring
- ✅ **Load balancing** : Répartition de charge native
- ✅ **Middleware** : Authentification, rate limiting, compression...
- ✅ **Configuration dynamique** : Pas besoin de redémarrer pour ajouter un service

---

## 💡 Pourquoi Traefik pour KeyHome ?

### Comparaison : Nginx vs Traefik

| Besoin | Nginx (Actuel) | Traefik (Futur) |
|--------|---------------|-----------------|
| **Ajouter un sous-domaine** | Éditer .conf + reload | 1 label Docker |
| **SSL Let's Encrypt** | Certbot + cron | Automatique |
| **Nouveau service** | Config manuelle | Auto-détecté |
| **Load balancing** | Config manuelle | Automatique |
| **Monitoring** | Logs uniquement | Dashboard + Prometheus |

### Exemple concret

**Avec Nginx** (actuel) :
```bash
# 1. Créer /etc/nginx/sites-available/api.conf
# 2. Éditer la config
# 3. Tester la config
nginx -t
# 4. Recharger Nginx
systemctl reload nginx
# 5. Configurer Certbot
certbot --nginx -d api.keyhome.neocraft.dev
```

**Avec Traefik** (futur) :
```yaml
# Juste ajouter 2 labels au service :
labels:
  - "traefik.http.routers.api.rule=Host(`api.keyhome.neocraft.dev`)"
  - "traefik.http.routers.api.tls.certresolver=letsencrypt"
```

---

## 🏗️ Architecture & Concepts

### Architecture simplifiée

```
┌─────────────────────────────────────────────────────────────┐
│                        INTERNET                             │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
        ┌────────────────┐
        │   Traefik      │  Port 80 & 443
        │  (Reverse      │  (Gère SSL automatiquement)
        │   Proxy)       │
        └────────┬───────┘
                 │
      ┌──────────┼──────────┬──────────────┬──────────────┐
      │          │          │              │              │
      ▼          ▼          ▼              ▼              ▼
  ┌──────┐  ┌──────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐
  │ App  │  │ Web  │  │ Grafana  │  │ PgAdmin  │  │   API    │
  │ :9000│  │ :80  │  │  :3000   │  │  :80     │  │  :8000   │
  └──────┘  └──────┘  └──────────┘  └──────────┘  └──────────┘
```

### Concepts clés

#### 1. **EntryPoints** (Points d'entrée)
Les ports sur lesquels Traefik écoute.

```yaml
entryPoints:
  web:
    address: ":80"     # HTTP
  websecure:
    address: ":443"    # HTTPS
```

#### 2. **Routers** (Routeurs)
Décident quel service doit traiter une requête selon des règles (domaine, chemin...).

```yaml
# Exemple : Toutes les requêtes vers api.keyhome.neocraft.dev
routers:
  api-router:
    rule: "Host(`api.keyhome.neocraft.dev`)"
    service: api-service
```

#### 3. **Services** (Services)
Les destinations finales (vos conteneurs Docker).

```yaml
services:
  api-service:
    loadBalancer:
      servers:
        - url: "http://app:9000"
```

#### 4. **Middlewares** (Middleware)
Modifications de requêtes/réponses (auth, redirect, compression...).

```yaml
middlewares:
  redirect-to-https:
    redirectScheme:
      scheme: https
      permanent: true
```

---

## ⚙️ Installation & Configuration

### Étape 1 : Créer le réseau Docker externe

```bash
# Sur votre serveur
docker network create traefik-public
```

**Pourquoi ?** Permet à Traefik de communiquer avec tous vos services.

### Étape 2 : Créer les fichiers de configuration

#### `traefik.yml` (Configuration statique)

```yaml
# /var/www/keyhome/traefik/traefik.yml

# Points d'entrée
entryPoints:
  web:
    address: ":80"
    # Redirection automatique HTTP → HTTPS
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
          permanent: true

  websecure:
    address: ":443"
    http:
      tls:
        certResolver: letsencrypt

# Providers
providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false  # ⚠️ Important : sécurise vos services
    network: traefik-public

# Certificats Let's Encrypt
certificatesResolvers:
  letsencrypt:
    acme:
      email: support@neocraft.dev  # 📧 Votre email
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web

# API & Dashboard
api:
  dashboard: true
  insecure: false  # Dashboard protégé par HTTPS

# Logs
log:
  level: INFO
  filePath: /var/log/traefik/traefik.log

accessLog:
  filePath: /var/log/traefik/access.log

# Métriques Prometheus (pour Grafana)
metrics:
  prometheus:
    addEntryPointsLabels: true
    addServicesLabels: true
```

**📚 Référence** : [Traefik Configuration](https://doc.traefik.io/traefik/getting-started/configuration-overview/)

### Étape 3 : Créer le fichier docker-compose pour Traefik

#### `docker-compose.traefik.yml`

```yaml
# /var/www/keyhome/docker-compose.traefik.yml

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
      - "80:80"      # HTTP
      - "443:443"    # HTTPS
    volumes:
      # Socket Docker pour auto-découverte
      - /var/run/docker.sock:/var/run/docker.sock:ro
      # Configuration Traefik
      - ./traefik/traefik.yml:/etc/traefik/traefik.yml:ro
      # Certificats Let's Encrypt (persistants)
      - traefik-certificates:/letsencrypt
      # Logs
      - traefik-logs:/var/log/traefik
    labels:
      # Activer Traefik pour ce conteneur
      - "traefik.enable=true"

      # Dashboard Traefik accessible via dashboard.keyhome.neocraft.dev
      - "traefik.http.routers.traefik-dashboard.rule=Host(`dashboard.keyhome.neocraft.dev`)"
      - "traefik.http.routers.traefik-dashboard.entrypoints=websecure"
      - "traefik.http.routers.traefik-dashboard.tls.certresolver=letsencrypt"
      - "traefik.http.routers.traefik-dashboard.service=api@internal"
      
      # Authentification basique pour le dashboard (optionnel mais recommandé)
      - "traefik.http.routers.traefik-dashboard.middlewares=dashboard-auth"
      - "traefik.http.middlewares.dashboard-auth.basicauth.users=admin:$$apr1$$8EVjn/nj$$GiLUZqcbueTFeD23SuB6x0"
      # (Généré avec : echo $(htpasswd -nB admin) | sed -e s/\\$/\\$\\$/g)

networks:
  traefik-public:
    external: true

volumes:
  traefik-certificates:
  traefik-logs:
```

**⚠️ Important** : Remplacez `admin:$$apr1...` par votre propre mot de passe.

#### Générer le mot de passe pour le dashboard

```bash
# Installer htpasswd
apt install apache2-utils

# Générer le hash
echo $(htpasswd -nB admin) | sed -e s/\\$/\\$\\$/g

# Copier le résultat dans le label basicauth.users
```

### Étape 4 : Adapter votre docker-compose.yml principal

Modifiez votre `docker-compose.yml` existant pour ajouter les labels Traefik :

```yaml
# docker-compose.yml (extrait)

services:
  app:
    # ... config existante ...
    networks:
      - keyhome-network
      - traefik-public  # 👈 Ajouter
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route pour l'application principale
      - "traefik.http.routers.keyhome-app.rule=Host(`keyhome.neocraft.dev`)"
      - "traefik.http.routers.keyhome-app.entrypoints=websecure"
      - "traefik.http.routers.keyhome-app.tls.certresolver=letsencrypt"
      - "traefik.http.routers.keyhome-app.service=keyhome-app"
      - "traefik.http.services.keyhome-app.loadbalancer.server.port=9000"

  web:
    # ... config existante ...
    networks:
      - keyhome-network
      - traefik-public  # 👈 Ajouter
    # ⚠️ RETIRER le mapping de ports (80:80) - Traefik gère maintenant
    # ports:
    #   - "9090:80"  # 👈 Commenter ou supprimer
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route pour l'API
      - "traefik.http.routers.keyhome-api.rule=Host(`api.keyhome.neocraft.dev`)"
      - "traefik.http.routers.keyhome-api.entrypoints=websecure"
      - "traefik.http.routers.keyhome-api.tls.certresolver=letsencrypt"
      - "traefik.http.routers.keyhome-api.service=keyhome-api"
      - "traefik.http.services.keyhome-api.loadbalancer.server.port=80"

  grafana:
    # ... config existante ...
    networks:
      - keyhome-network
      - traefik-public  # 👈 Ajouter
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"
      
      # Route pour Grafana
      - "traefik.http.routers.grafana.rule=Host(`grafana.keyhome.neocraft.dev`)"
      - "traefik.http.routers.grafana.entrypoints=websecure"
      - "traefik.http.routers.grafana.tls.certresolver=letsencrypt"
      - "traefik.http.routers.grafana.service=grafana"
      - "traefik.http.services.grafana.loadbalancer.server.port=3000"

networks:
  keyhome-network:
    driver: bridge
  traefik-public:
    external: true
```

### Étape 5 : Lancer Traefik

```bash
# 1. Créer le dossier de config
mkdir -p traefik

# 2. Copier traefik.yml dedans (voir Étape 2)

# 3. Lancer Traefik
docker compose -f docker-compose.traefik.yml up -d

# 4. Vérifier les logs
docker logs traefik -f

# 5. Vérifier que Traefik écoute
ss -tlnp | grep -E ":(80|443)"
```

### Étape 6 : Relancer vos services avec les labels

```bash
# Arrêter les services actuels
docker compose down

# Relancer avec la nouvelle config
docker compose up -d

# Vérifier que Traefik détecte vos services
docker logs traefik | grep "Adding route"
```

---

## 🔒 Configuration SSL automatique

### Comment ça marche ?

1. **Traefik détecte** un nouveau service avec `tls.certresolver=letsencrypt`
2. **Challenge HTTP** : Let's Encrypt envoie une requête vers `http://votre-domaine/.well-known/`
3. **Traefik répond** automatiquement
4. **Certificat généré** et sauvegardé dans `/letsencrypt/acme.json`
5. **Renouvellement automatique** 30 jours avant expiration

### Vérifier les certificats

```bash
# Voir le contenu de acme.json
docker exec traefik cat /letsencrypt/acme.json | jq '.letsencrypt.Certificates[] | {Domain: .domain.main, NotAfter: .certificate}'

# Tester SSL avec OpenSSL
openssl s_client -connect keyhome.neocraft.dev:443 -servername keyhome.neocraft.dev < /dev/null | grep -A 2 "Verify return code"
```

### Forcer le renouvellement (debug)

```bash
# Supprimer acme.json (⚠️ tous les certificats seront regénérés)
docker compose -f docker-compose.traefik.yml down
docker volume rm traefik-certificates
docker compose -f docker-compose.traefik.yml up -d
```

---

## 🌐 Multi-domaines & sous-domaines

### Pattern pour ajouter un nouveau service

Exemple : ajouter `pgadmin.keyhome.neocraft.dev`

```yaml
pgadmin:
  # ... config existante ...
  networks:
    - traefik-public
  labels:
    - "traefik.enable=true"
    - "traefik.http.routers.pgadmin.rule=Host(`pgadmin.keyhome.neocraft.dev`)"
    - "traefik.http.routers.pgadmin.entrypoints=websecure"
    - "traefik.http.routers.pgadmin.tls.certresolver=letsencrypt"
    - "traefik.http.services.pgadmin.loadbalancer.server.port=80"
```

**C'est tout !** Traefik :
1. Détecte le service
2. Crée la route
3. Génère le certificat SSL
4. Redirige le trafic

---

## 📊 Monitoring & Dashboard

### Accéder au Dashboard Traefik

1. Ouvrir `https://dashboard.keyhome.neocraft.dev`
2. Credentials : `admin` / `votre-mot-de-passe`

### Ce que vous voyez :

- **Routers** : Toutes les routes actives
- **Services** : État de santé des conteneurs
- **Middlewares** : Règles appliquées
- **Certificats** : État SSL et expiration

### Intégration Prometheus

Traefik expose automatiquement des métriques sur `:8082/metrics`.

Ajouter dans votre `prometheus.yml` :

```yaml
scrape_configs:
  - job_name: 'traefik'
    static_configs:
      - targets: ['traefik:8080']
```

---

## 🔧 Troubleshooting

### Problème : Service non accessible

```bash
# 1. Vérifier que Traefik tourne
docker ps | grep traefik

# 2. Vérifier les logs Traefik
docker logs traefik --tail=100

# 3. Vérifier que le service est sur traefik-public
docker inspect <container-name> | grep -A 10 Networks

# 4. Vérifier les labels du conteneur
docker inspect <container-name> | jq '.[].Config.Labels'
```

### Problème : Certificat SSL non généré

```bash
# 1. Vérifier les logs ACME
docker logs traefik | grep -i "acme\|certificate"

# 2. Vérifier que le port 80 est ouvert
curl -I http://keyhome.neocraft.dev/.well-known/acme-challenge/test

# 3. Vérifier le DNS
dig keyhome.neocraft.dev +short
# Doit retourner l'IP de votre serveur
```

### Problème : "404 Not Found" de Traefik

**Cause** : Le service n'est pas enregistré dans Traefik.

**Solution** :
```bash
# Vérifier que traefik.enable=true
docker inspect <container> | grep "traefik.enable"

# Vérifier le réseau
docker network inspect traefik-public
```

---

## 📚 Références officielles

- **Documentation Traefik** : https://doc.traefik.io/traefik/
- **Routing** : https://doc.traefik.io/traefik/routing/routers/
- **Docker Provider** : https://doc.traefik.io/traefik/providers/docker/
- **Let's Encrypt** : https://doc.traefik.io/traefik/https/acme/
- **Middlewares** : https://doc.traefik.io/traefik/middlewares/overview/

---

**Prochaine étape** : Lire `02-docker-compose-complet.md` pour voir le fichier Docker Compose final avec tous les services.
