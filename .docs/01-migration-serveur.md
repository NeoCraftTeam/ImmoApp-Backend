# 📦 Guide de Migration KeyHome - Nouveau Serveur VPS

> **Documentation officielle** basée sur les best practices Docker, PostgreSQL, et Laravel.  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 📋 Table des matières

1. [Prérequis](#prérequis)
2. [Préparation de l'ancien serveur](#préparation-ancien-serveur)
3. [Configuration du nouveau serveur](#configuration-nouveau-serveur)
4. [Migration des données](#migration-données)
5. [Vérification et bascule DNS](#vérification-bascule)
6. [Rollback en cas de problème](#rollback)

---

## 🎯 Prérequis

### A. Ancien Serveur (Source)
- [ ] Accès SSH root/sudo
- [ ] Docker et Docker Compose installés
- [ ] Application KeyHome fonctionnelle
- [ ] Sauvegardes récentes disponibles

### B. Nouveau Serveur (Destination)
- [ ] **OS** : Ubuntu 22.04 LTS (recommandé) ou Debian 12
- [ ] **RAM** : Minimum 4 GB (8 GB recommandé)
- [ ] **CPU** : Minimum 2 cores (4 cores recommandé)
- [ ] **Stockage** : Minimum 50 GB SSD
- [ ] **IP Publique** : Fixe et connue
- [ ] **Accès SSH** : Root ou utilisateur sudo

### C. Outils requis
```bash
# Sur votre machine locale
brew install rsync     # macOS
apt install rsync      # Ubuntu/Debian

# Vérifier les versions
rsync --version
ssh -V
```

---

## 🔧 Préparation de l'ancien serveur

### Étape 1 : Connexion et inventaire

```bash
# Connexion à l'ancien VPS
ssh root@<IP_ANCIEN_SERVEUR>

# Lister tous les conteneurs
docker ps -a

# Lister les volumes Docker
docker volume ls

# Vérifier l'espace disque
df -h

# Localiser les données KeyHome
docker volume inspect keyhome-db-data
docker volume inspect keyhome-storage-data
```

### Étape 2 : Arrêt propre de l'application

```bash
# Se positionner dans le projet
cd /var/www/ImmoApp-Backend  # Adapter selon votre chemin

# Passer en mode maintenance
docker compose exec app php artisan down --message="Migration en cours"

# Arrêter les workers pour éviter les jobs en cours
docker compose stop worker

# Attendre 30 secondes que les jobs se terminent
sleep 30
```

### Étape 3 : Sauvegarde de la base de données

```bash
# Créer un dossier de sauvegarde
mkdir -p ~/keyhome-backup/database

# Dump PostgreSQL
docker compose exec -T db pg_dumpall -U postgres > ~/keyhome-backup/database/keyhome-$(date +%Y%m%d-%H%M%S).sql

# Vérifier la taille du dump
ls -lh ~/keyhome-backup/database/

# Compresser (optionnel mais recommandé)
gzip ~/keyhome-backup/database/*.sql
```

**⚠️ Important** : Le dump doit contenir :
- Schéma complet
- Données
- Utilisateurs et permissions
- Extensions PostGIS

### Étape 4 : Sauvegarde des fichiers (storage)

```bash
# Créer dossier pour les fichiers
mkdir -p ~/keyhome-backup/storage

# Copier le volume storage
docker cp keyhome-backend:/var/www/storage ~/keyhome-backup/storage/

# Vérifier la taille
du -sh ~/keyhome-backup/storage/

# Alternative : Export du volume Docker directement
docker run --rm \
  -v keyhome-storage-data:/source \
  -v ~/keyhome-backup/storage:/backup \
  alpine tar czf /backup/storage-backup.tar.gz -C /source .
```

### Étape 5 : Sauvegarde du code et configurations

```bash
# Variables d'environnement
cp .env ~/keyhome-backup/.env.backup

# Docker Compose
cp docker-compose.yml ~/keyhome-backup/

# Configurations Nginx
cp -r .docker ~/keyhome-backup/

# Créer une archive complète
cd ~/keyhome-backup
tar czf keyhome-full-backup-$(date +%Y%m%d).tar.gz *

# Vérifier l'archive
tar -tzf keyhome-full-backup-*.tar.gz | head -20
```

### Étape 6 : Télécharger les sauvegardes en local

```bash
# Sur VOTRE MACHINE LOCALE (pas le serveur)
mkdir -p ~/Desktop/keyhome-migration

# Télécharger via SCP
scp -r root@<IP_ANCIEN_SERVEUR>:~/keyhome-backup/* ~/Desktop/keyhome-migration/

# Vérifier
ls -lh ~/Desktop/keyhome-migration/
```

---

## 🖥️ Configuration du nouveau serveur

### Étape 1 : Connexion et mise à jour système

```bash
# Connexion au nouveau VPS
ssh root@<IP_NOUVEAU_SERVEUR>

# Mise à jour système
apt update && apt upgrade -y

# Installation des dépendances de base
apt install -y \
  apt-transport-https \
  ca-certificates \
  curl \
  gnupg \
  lsb-release \
  git \
  vim \
  htop \
  ufw
```

### Étape 2 : Installation Docker & Docker Compose

```bash
# Ajouter le repo officiel Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Vérifier l'installation
docker --version
docker compose version

# Démarrer Docker au boot
systemctl enable docker
systemctl start docker

# Tester
docker run hello-world
```

**📚 Référence** : [Docker Installation Documentation](https://docs.docker.com/engine/install/ubuntu/)

### Étape 3 : Configuration du firewall

```bash
# Activer UFW
ufw --force enable

# Règles de base
ufw default deny incoming
ufw default allow outgoing

# Autoriser SSH (IMPORTANT !)
ufw allow 22/tcp

# Autoriser HTTP/HTTPS (pour Traefik)
ufw allow 80/tcp
ufw allow 443/tcp

# Autoriser PostgreSQL (optionnel, si accès distant)
# ufw allow 5432/tcp

# Vérifier
ufw status verbose
```

### Étape 4 : Créer l'arborescence du projet

```bash
# Créer un utilisateur dédié (recommandé)
useradd -m -s /bin/bash keyhome
usermod -aG docker keyhome

# Créer les dossiers
mkdir -p /var/www/keyhome
chown -R keyhome:keyhome /var/www/keyhome

# Se connecter en tant que keyhome
su - keyhome
cd /var/www/keyhome
```

### Étape 5 : Uploader les sauvegardes

```bash
# Sur VOTRE MACHINE LOCALE
scp -r ~/Desktop/keyhome-migration/* keyhome@<IP_NOUVEAU_SERVEUR>:/var/www/keyhome/backup/
```

### Étape 6 : Extraire et préparer

```bash
# Sur le NOUVEAU SERVEUR (en tant que keyhome)
cd /var/www/keyhome/backup

# Extraire l'archive complète
tar xzf keyhome-full-backup-*.tar.gz

# Décompresser le dump SQL
gunzip database/*.sql.gz

# Vérifier
ls -lh
```

---

## 🦊 Installation GitLab Runner

**⚠️ ÉTAPE CRITIQUE** : GitLab Runner permet le déploiement automatique via CI/CD.

### Étape 1 : Installation GitLab Runner

```bash
# Sur le NOUVEAU serveur (en tant que root)

# 1. Ajouter le repository officiel GitLab
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | bash

# 2. Installer GitLab Runner
apt-get install gitlab-runner

# 3. Vérifier l'installation
gitlab-runner --version
```

**📚 Référence** : [GitLab Runner Installation](https://docs.gitlab.com/runner/install/linux-repository.html)

### Étape 2 : Enregistrer le Runner

```bash
# Obtenir le Registration Token :
# 1. Aller sur GitLab : https://gitlab.com/NeoCraftTeam/ImmoApp-Backend
# 2. Settings > CI/CD > Runners > Expand
# 3. Copier le "registration token"

# Enregistrer le runner
gitlab-runner register

# Répondre aux questions :
# Enter GitLab instance URL: https://gitlab.com/
# Enter registration token: [VOTRE_TOKEN]
# Enter description: keyhome-vps-production
# Enter tags (comma separated): self-hosted-shell,production
# Enter executor: shell
```

### Étape 3 : Configuration du Runner

```bash
# Éditer la config du runner
nano /etc/gitlab-runner/config.toml
```

**Fichier `/etc/gitlab-runner/config.toml`** :
```toml
concurrent = 1
check_interval = 0

[session_server]
  session_timeout = 1800

[[runners]]
  name = "keyhome-vps-production"
  url = "https://gitlab.com/"
  token = "VOTRE_RUNNER_TOKEN"
  executor = "shell"
  
  # Tags pour cibler ce runner dans .gitlab-ci.yml
  [runners.custom_build_dir]
  [runners.cache]
    [runners.cache.s3]
    [runners.cache.gcs]
    [runners.cache.azure]
```

### Étape 4 : Donner les permissions Docker au runner

```bash
# Ajouter gitlab-runner au groupe docker
usermod -aG docker gitlab-runner

# Vérifier
su - gitlab-runner -s /bin/bash
docker ps  # Doit fonctionner sans sudo
exit

# Redémarrer le service
systemctl restart gitlab-runner
systemctl status gitlab-runner
```

### Étape 5 : Configurer l'accès au GitLab Container Registry

```bash
# En tant que gitlab-runner
su - gitlab-runner -s /bin/bash

# Login au registry GitLab
docker login registry.gitlab.com

# Username: Votre username GitLab
# Password: Utiliser un Personal Access Token (PAT)
```

**Comment créer un Personal Access Token (PAT)** :
1. GitLab > Profile > Access Tokens
2. Token name : `keyhome-registry-access`
3. Scopes : `read_registry`, `write_registry`
4. Expiration : 1 an
5. Copier le token généré

```bash
# Tester l'accès au registry
docker pull registry.gitlab.com/neocraftteam/immoapp-backend/app:main

# Si ça fonctionne, vous êtes prêt !
exit  # Revenir en root
```

---

## 🐳 Configuration GitLab CI/CD Variables

### Variables à configurer dans GitLab

Aller sur GitLab : **Settings > CI/CD > Variables**

| Variable | Valeur | Protection | Masqué |
|----------|--------|------------|--------|
| `CI_REGISTRY` | `registry.gitlab.com` | ✅ | ❌ |
| `CI_REGISTRY_IMAGE` | `registry.gitlab.com/neocraftteam/immoapp-backend` | ✅ | ❌ |
| `CI_REGISTRY_USER` | Votre username GitLab | ✅ | ❌ |
| `CI_REGISTRY_PASSWORD` | Votre Personal Access Token | ✅ | ✅ |
| `SLACK_WEBHOOK_URL` | URL webhook Slack (optionnel) | ✅ | ✅ |

**📚 Référence** : [GitLab CI/CD Variables](https://docs.gitlab.com/ee/ci/variables/)

---

## 🔄 Migration des données

### Étape 1 : ⚠️ PAS de clone Git manuel (le pipeline le fait)

```bash
# ❌ NE PAS FAIRE :
# git clone https://github.com/NeoCraftTeam/ImmoApp-Backend.git .

# ✅ À LA PLACE : Créer la structure minimale
cd /var/www/keyhome
mkdir -p .docker/nginx/conf.d

# Le code sera déployé par GitLab CI/CD via le Registry
```

### Étape 2 : Restaurer le .env

```bash
# Copier le .env de la sauvegarde
cp ~/keyhome/backup/.env.backup /var/www/keyhome/.env

# ⚠️ IMPORTANT : Adapter les valeurs pour le nouveau serveur
nano /var/www/keyhome/.env
```

**Variables à vérifier/modifier dans `.env`** :
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://keyhome.neocraft.dev

# Base de données
DB_HOST=db  # Nom du conteneur Docker
DB_DATABASE=keyhome
DB_USERNAME=postgres
DB_PASSWORD=VotreNouveauMotDePasseSecurise

# Queue (avec Redis)
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=VotreRedisPassword

# Mail (vérifier que les credentials sont corrects)
MAIL_HOST=mail.infomaniak.com
MAIL_USERNAME=support@neocraft.dev
MAIL_PASSWORD=VotreMotDePasseMail
# ... etc
```

### Étape 3 : Restaurer la base de données

```bash
# Démarrer PostgreSQL uniquement
cd /var/www/keyhome

# ⚠️ Créer un docker-compose minimal temporaire pour la DB
cat > docker-compose.temp.yml <<EOF
version: '3.8'
services:
  db:
    image: postgis/postgis:15-3.3-alpine
    container_name: keyhome-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: keyhome
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - keyhome-db-data:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    networks:
      - keyhome-network

networks:
  keyhome-network:
    driver: bridge

volumes:
  keyhome-db-data:
    driver: local
EOF

# Démarrer PostgreSQL
docker compose -f docker-compose.temp.yml up -d

# Attendre que PostgreSQL soit prêt
sleep 10
docker compose -f docker-compose.temp.yml logs db | tail -20
```

### Étape 4 : Restaurer le dump SQL

```bash
# Restaurer le dump SQL
cat ~/keyhome/backup/database/keyhome-*.sql | docker compose -f docker-compose.temp.yml exec -T db psql -U postgres

# Vérifier la restauration
docker compose -f docker-compose.temp.yml exec db psql -U postgres -d keyhome -c "\dt"
docker compose -f docker-compose.temp.yml exec db psql -U postgres -d keyhome -c "SELECT COUNT(*) FROM users;"

# Arrêter PostgreSQL temporaire
docker compose -f docker-compose.temp.yml down

# Supprimer le fichier temporaire
rm docker-compose.temp.yml
```

### Étape 5 : Restaurer les fichiers storage

```bash
# Créer le volume storage et y copier les fichiers
docker volume create keyhome-storage-data

# Restaurer depuis la sauvegarde
docker run --rm \
  -v keyhome-storage-data:/target \
  -v ~/keyhome/backup/storage:/backup \
  alpine sh -c "cp -rp /backup/storage/. /target/ && chmod -R 755 /target && chown -R 1000:1000 /target"

# Vérifier
docker run --rm -v keyhome-storage-data:/data alpine ls -lah /data
```

### Étape 6 : Copier les fichiers de configuration

```bash
cd /var/www/keyhome

# Copier les configs essentielles depuis la sauvegarde
cp ~/keyhome/backup/docker-compose.yml ./docker-compose.yml
cp -r ~/keyhome/backup/.docker ./.docker

# Vérifier que tout est en place
ls -la
ls -la .docker/nginx/conf.d/
```

### Étape 7 : Déclencher le premier déploiement via GitLab CI/CD

**🎯 Workflow complet** :

```bash
# Sur VOTRE MACHINE LOCALE (pas le serveur)

# 1. Vérifier que le .gitlab-ci.yml est à jour
cat .gitlab-ci.yml | grep "production_deploy"

# 2. Faire un commit/push pour déclencher le pipeline
git add .
git commit -m "chore: initial deployment on new VPS"
git push origin main

# 3. Surveiller la pipeline sur GitLab
# https://gitlab.com/NeoCraftTeam/ImmoApp-Backend/-/pipelines
```

**Ce que fait automatiquement la pipeline** :

1. ✅ **Quality** : PHPStan + Pint (style check)
2. ✅ **Security** : Composer audit
3. ✅ **Build** : Construit l'image Docker
4. ✅ **Push** : Pousse l'image vers GitLab Container Registry
5. ✅ **Test** : Lance les tests PHPUnit
6. ✅ **Deploy** : 
   - Pull l'image depuis le Registry
   - Lance les conteneurs (app, worker, web, redis...)
   - Exécute les migrations
   - Optimise Laravel
   - Génère la doc Swagger
7. ✅ **Notify** : Envoie une notif Slack (si configuré)
8. ✅ **Cleanup** : Nettoie les anciennes images

### Étape 8 : Surveiller le déploiement sur le serveur

```bash
# Sur le NOUVEAU SERVEUR

# Suivre les logs du runner en temps réel
tail -f /var/log/gitlab-runner/gitlab-runner.log

# Ou suivre les logs Docker
cd /var/www/keyhome
docker compose logs -f

# Vérifier que tous les conteneurs sont UP
docker compose ps
```

### Étape 9 : Vérifications post-déploiement

```bash
# Sur le serveur

cd /var/www/keyhome

# 1. Vérifier l'image utilisée
docker compose images

# 2. Tester la base de données
docker compose exec app php artisan tinker --execute="echo \App\Models\User::count() . ' users';"

# 3. Tester les fichiers storage
docker compose exec app ls -lah /var/www/storage/app/public/

# 4. Générer une clé d'app si nécessaire
docker compose exec app php artisan key:generate --show

# 5. Tester l'API
curl -I http://localhost:9090
```

---

## 🔄 Workflow de déploiement futur

### Pour chaque mise à jour de code

```bash
# Sur VOTRE MACHINE LOCALE

# 1. Développer vos features
git add .
git commit -m "feat: nouvelle fonctionnalité"

# 2. Push vers GitLab
git push origin main

# 3. Le reste est AUTOMATIQUE :
# - Build de l'image
# - Push au Registry
# - Pull sur le VPS
# - Redémarrage des services
# - Migrations
# - Cache refresh
```

**Le serveur n'a JAMAIS besoin de `git pull` manuel !**

### Déploiement manuel d'urgence (si pipeline en panne)

```bash
# Sur le SERVEUR (uniquement en cas d'urgence)

cd /var/www/keyhome

# Login au registry
echo $CI_REGISTRY_PASSWORD | docker login -u $CI_REGISTRY_USER --password-stdin registry.gitlab.com

# Pull l'image latest
export APP_IMAGE="registry.gitlab.com/neocraftteam/immoapp-backend/app:main"
docker compose pull

# Redémarrer
docker compose up -d --force-recreate

# Migrations
docker compose exec app php artisan migrate --force

# Logout
docker logout registry.gitlab.com
```

---

## ✅ Vérification et bascule DNS

### Étape 1 : Tests locaux avant bascule DNS

```bash
# Sur VOTRE MACHINE LOCALE (macOS)
# Éditer /etc/hosts temporairement
sudo nano /etc/hosts

# Ajouter cette ligne (remplacer par l'IP du nouveau serveur)
<IP_NOUVEAU_SERVEUR>  keyhome.neocraft.dev api.keyhome.neocraft.dev
```

Tester dans le navigateur :
- `http://<IP_NOUVEAU_SERVEUR>:9090` → Doit afficher l'app
- Tester login/logout
- Tester upload d'image
- Tester création d'annonce

### Étape 2 : Bascule DNS progressive

**Option A : DNS Failover (recommandé)**
1. Réduire le TTL DNS à 300s (5 minutes)
2. Attendre 24h que le changement se propage
3. Pointer le domaine vers la nouvelle IP
4. Surveiller pendant 1h
5. Si OK, augmenter le TTL à 3600s

**Option B : Bascule immédiate**
1. Dans votre registrar/DNS (OVH, Cloudflare, etc.)
2. Modifier l'enregistrement A :
   ```
   keyhome.neocraft.dev  →  <IP_NOUVEAU_SERVEUR>
   ```
3. Attendre 5-30 minutes (propagation DNS)

### Étape 3 : Monitoring post-migration

```bash
# Sur le NOUVEAU serveur
# Surveiller les logs en temps réel
docker compose logs -f

# Surveiller les ressources
htop

# Surveiller les erreurs Laravel
docker compose exec app tail -f storage/logs/laravel.log
```

---

## 🔙 Rollback en cas de problème

### Si problème détecté < 1h après migration

```bash
# 1. Re-pointer le DNS vers l'ancien serveur
# 2. Sur l'ANCIEN serveur :
docker compose start
docker compose exec app php artisan up

# L'ancien serveur reprend le trafic
```

### Si problème détecté > 1h après migration

```bash
# Sur le NOUVEAU serveur
# 1. Dump de la base actuelle (conservation des nouvelles données)
docker compose exec -T db pg_dumpall -U postgres > /tmp/new-server-dump.sql

# 2. Fusionner avec l'ancienne base (complexe, nécessite expertise SQL)
# Ou accepter la perte des données créées pendant la migration
```

---

## 📊 Checklist finale

- [ ] Tous les conteneurs Docker sont UP
- [ ] Base de données accessible et cohérente
- [ ] Fichiers storage accessibles (images, uploads)
- [ ] Login/Logout fonctionnel
- [ ] Création d'annonce fonctionnelle
- [ ] Emails envoyés correctement
- [ ] Worker de queue actif
- [ ] Monitoring (Grafana) accessible
- [ ] SSL/HTTPS configuré (voir doc Traefik)
- [ ] Sauvegardes automatiques configurées
- [ ] DNS pointant vers nouveau serveur
- [ ] Ancien serveur conservé 7 jours (backup de sécurité)

---

## 🆘 Support & Références

- **Docker Docs** : https://docs.docker.com/
- **PostgreSQL Backup** : https://www.postgresql.org/docs/current/backup.html
- **Laravel Deployment** : https://laravel.com/docs/deployment
- **Traefik** : Voir `01-traefik-setup.md`

---

**Prochaine étape** : Lire `01-traefik-setup.md` pour configurer le proxy inverse avec SSL automatique.
