# 📁 Conventions Linux & Structure Serveur

> **Guide des bonnes pratiques** pour l'organisation des fichiers sur un serveur Linux  
> **Basé sur** : [Filesystem Hierarchy Standard (FHS)](https://refspecs.linuxfoundation.org/FHS_3.0/fhs-3.0.html)  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 📋 Table des matières

1. [Hiérarchie standard Linux](#hiérarchie-standard)
2. [Où placer vos applications ?](#où-placer-applications)
3. [Structure recommandée pour KeyHome](#structure-keyhome)
4. [Permissions et propriétaires](#permissions)
5. [Organisation multi-projets](#multi-projets)

---

## 🗂️ Hiérarchie standard Linux (FHS)

### Répertoires système principaux

| Répertoire | Usage | Exemple |
|------------|-------|---------|
| `/` | Racine du système | Point de montage |
| `/bin` | Binaires essentiels | `bash`, `ls`, `cat` |
| `/etc` | Fichiers de configuration système | `nginx.conf`, `hosts` |
| `/home` | Répertoires utilisateurs | `/home/keyhome` |
| `/opt` | **Applications tierces** | `/opt/keyhome` ✅ |
| `/srv` | Données servies par le système | `/srv/www` |
| `/var` | Données variables (logs, cache...) | `/var/log`, `/var/www` |
| `/usr` | Utilitaires multi-utilisateurs | `/usr/bin`, `/usr/local` |
| `/tmp` | Fichiers temporaires | Nettoyé au reboot |

---

## 💡 Où placer vos applications ?

### Débat `/var/www` vs `/opt` vs `/srv`

#### Option 1 : `/var/www` (Traditionnel - Apache/Nginx)

**✅ Avantages** :
- Convention historique pour le web
- Intuitif pour les développeurs web
- Fonctionne bien avec Nginx/Apache par défaut

**❌ Inconvénients** :
- `/var` est techniquement pour les *données variables* (logs, cache)
- Peut devenir confus avec plusieurs projets
- Mixing code + logs dans `/var`

**Quand l'utiliser** :
- Application web PHP classique (WordPress, Laravel simple)
- Un seul site web sur le serveur
- Serveur LAMP/LEMP traditionnel

```bash
/var/www/
├── html/              # Site par défaut
├── keyhome/          # Votre application
│   ├── public/
│   ├── storage/
│   └── ...
└── autre-site/
```

#### Option 2 : `/opt` (Recommandé - Applications autonomes) ⭐

**✅ Avantages** :
- **Conformité FHS** : `/opt` est fait pour ça
- Isolation claire : 1 app = 1 dossier
- Séparation code / données / logs
- Scalable (plusieurs apps facilement)
- Utilisé par les packages professionnels (Docker, GitLab...)

**❌ Inconvénients** :
- Moins connu des débutants
- Nginx doit pointer vers `/opt` (config custom)

**Quand l'utiliser** :
- Applications Dockerisées ✅
- Environnements multi-applications
- Projets professionnels/production
- **C'est le choix recommandé pour KeyHome**

```bash
/opt/
├── keyhome/              # ✅ APPLICATION PRINCIPALE
│   ├── docker-compose.yml
│   ├── .env
│   ├── .docker/
│   └── traefik/
├── monitoring/           # Autre service (optionnel)
│   └── docker-compose.yml
└── backup-scripts/       # Scripts utilitaires
```

#### Option 3 : `/srv` (Services de données)

**✅ Avantages** :
- Sémantiquement correct pour "services"
- Certaines distros le préfèrent (Debian)

**❌ Inconvénients** :
- Moins utilisé en pratique
- Confusion avec `/var`

**Quand l'utiliser** :
- FTP, NFS, ou services de partage de fichiers
- **Moins recommandé pour une app web**

```bash
/srv/
├── www/              # Sites web
├── ftp/              # Données FTP
└── git/              # Repositories Git
```

---

## 🏗️ Structure recommandée pour KeyHome

### Architecture complète (Production)

```bash
# === SYSTÈME ===
/
├── opt/
│   └── keyhome/                          # ← APPLICATION PRINCIPALE
│       ├── docker-compose.yml            # Config Docker
│       ├── docker-compose.traefik.yml    # Traefik séparé
│       ├── .env                          # Variables d'environnement
│       ├── .docker/                      # Configs Docker
│       │   ├── nginx/
│       │   │   └── conf.d/
│       │   │       └── default.conf
│       │   ├── php/
│       │   │   ├── php.ini
│       │   │   └── opcache.ini
│       │   └── monitoring/
│       │       ├── prometheus/
│       │       │   └── prometheus.yml
│       │       └── grafana/
│       │           └── provisioning/
│       ├── traefik/                      # Config Traefik
│       │   └── traefik.yml
│       └── scripts/                      # Scripts utilitaires
│           ├── backup.sh
│           ├── restore.sh
│           └── health-check.sh
│
├── var/
│   ├── log/
│   │   ├── gitlab-runner/                # Logs CI/CD
│   │   └── keyhome/                      # Logs application (symlink)
│   └── lib/
│       └── docker/
│           └── volumes/                  # ← DONNÉES DOCKER
│               ├── keyhome-db-data/
│               ├── keyhome-storage-data/
│               ├── keyhome-redis-data/
│               └── traefik-certificates/
│
├── home/
│   └── keyhome/                          # Utilisateur dédié
│       ├── .ssh/
│       │   └── authorized_keys
│       └── backups/                      # Sauvegardes locales
│           ├── database/
│           ├── storage/
│           └── configs/
│
└── etc/
    ├── gitlab-runner/
    │   └── config.toml                   # Config GitLab Runner
    └── systemd/
        └── system/
            └── keyhome-backup.timer      # Cron systemd pour backups
```

### Explications

| Emplacement | Contenu | Raison |
|-------------|---------|--------|
| `/opt/keyhome/` | Code, configs, docker-compose | **Application isolée et self-contained** |
| `/var/lib/docker/volumes/` | Données persistantes (DB, storage) | **Géré automatiquement par Docker** |
| `/var/log/` | Logs applicatifs | **Conformité FHS pour logs** |
| `/home/keyhome/` | Backups, SSH keys | **Isolation utilisateur** |
| `/etc/` | Configs système (runner, timers) | **Configs système standard** |

---

## 🔐 Permissions et propriétaires

### Créer un utilisateur dédié

```bash
# Créer l'utilisateur keyhome
useradd -m -s /bin/bash keyhome

# Ajouter au groupe docker
usermod -aG docker keyhome

# Créer la structure
mkdir -p /opt/keyhome
chown -R keyhome:keyhome /opt/keyhome

# Vérifier
ls -lah /opt/
# drwxr-xr-x  3 keyhome keyhome 4.0K Jan 29 15:00 keyhome
```

### Permissions recommandées

```bash
# Application
chmod 755 /opt/keyhome                    # Lecture publique, écriture owner
chmod 700 /opt/keyhome/.env               # Lecture/écriture owner UNIQUEMENT
chmod 644 /opt/keyhome/docker-compose.yml # Lecture publique

# Scripts exécutables
chmod 750 /opt/keyhome/scripts/*.sh       # Exécution owner+group

# Logs
chmod 755 /var/log/keyhome                # Lecture publique
chmod 644 /var/log/keyhome/*.log          # Logs lisibles

# Backups (sensibles)
chmod 700 /home/keyhome/backups           # Lecture/écriture owner UNIQUEMENT
```

### Volumes Docker (gérés automatiquement)

```bash
# Docker gère les permissions des volumes
# Par défaut : root:root avec 755

# Pour changer le owner dans un volume :
docker run --rm \
  -v keyhome-storage-data:/data \
  alpine chown -R 1000:1000 /data
```

---

## 🗄️ Organisation multi-projets

### Si vous hébergez plusieurs applications

```bash
/opt/
├── keyhome/                   # Application 1
│   ├── docker-compose.yml
│   └── .env
│
├── autre-projet/              # Application 2
│   ├── docker-compose.yml
│   └── .env
│
├── shared/                    # Ressources partagées (optionnel)
│   ├── traefik/               # 1 seul Traefik pour tout
│   │   └── docker-compose.yml
│   └── monitoring/            # 1 seul Grafana pour tout
│       └── docker-compose.yml
│
└── scripts/                   # Scripts globaux
    ├── global-backup.sh
    └── health-check-all.sh
```

### Réseau Docker partagé

```bash
# Créer un réseau global pour Traefik
docker network create traefik-public

# Chaque projet se connecte à ce réseau
# Dans docker-compose.yml :
networks:
  traefik-public:
    external: true
  keyhome-private:
    driver: bridge
```

---

## 📊 Comparaison finale

| Critère | `/var/www` | `/opt` | `/srv` |
|---------|------------|--------|--------|
| **Conformité FHS** | ⚠️ Discutable | ✅ Oui | ✅ Oui |
| **Dockerisé** | ⚠️ Possible | ✅ Recommandé | ⚠️ Possible |
| **Multi-apps** | ⚠️ Devient confus | ✅ Scalable | ⚠️ Moyen |
| **Nginx config** | ✅ Par défaut | ⚠️ Custom | ⚠️ Custom |
| **Sémantique** | "Web content" | "Applications" | "Services data" |
| **Industrie** | Petits sites | ✅ Entreprise | Rare |

---

## 🎯 Recommandation pour KeyHome

### ✅ Utilisez `/opt/keyhome`

**Raisons** :
1. ✅ **Conformité FHS** : C'est la raison d'être de `/opt`
2. ✅ **Isolation** : Tout est contenu dans `/opt/keyhome/`
3. ✅ **Docker-friendly** : Pas de confusion avec `/var`
4. ✅ **Scalable** : Facile d'ajouter d'autres apps
5. ✅ **Professionnel** : Utilisé par Docker, GitLab, etc.

### Migration de `/var/www` vers `/opt`

Si vous êtes actuellement sur `/var/www/ImmoApp-Backend` :

```bash
# 1. Arrêter les services
cd /var/www/ImmoApp-Backend
docker compose down

# 2. Créer la nouvelle structure
mkdir -p /opt/keyhome
chown -R keyhome:keyhome /opt/keyhome

# 3. Déplacer les fichiers
mv /var/www/ImmoApp-Backend/* /opt/keyhome/
mv /var/www/ImmoApp-Backend/.[!.]* /opt/keyhome/  # Fichiers cachés

# 4. Vérifier
ls -lah /opt/keyhome/

# 5. Créer un symlink (optionnel, pour compatibilité)
ln -s /opt/keyhome /var/www/ImmoApp-Backend

# 6. Relancer
cd /opt/keyhome
docker compose up -d

# 7. Mettre à jour GitLab Runner
nano /etc/gitlab-runner/config.toml
# Changer les paths vers /opt/keyhome

# 8. Nettoyer l'ancien emplacement (après vérification)
rm -rf /var/www/ImmoApp-Backend  # Attention : sauvegarder avant !
```

---

## 🔗 Symlinks utiles (optionnel)

Pour garder des raccourcis :

```bash
# Logs accessibles facilement
ln -s /var/lib/docker/volumes/keyhome-logs/_data /opt/keyhome/logs

# Accès rapide aux configs
ln -s /opt/keyhome /home/keyhome/app

# Vérifier
ls -lah /home/keyhome/
# lrwxrwxrwx  1 keyhome keyhome   12 Jan 29 15:00 app -> /opt/keyhome
```

---

## 📚 Références

- **FHS 3.0** : https://refspecs.linuxfoundation.org/FHS_3.0/fhs-3.0.html
- **/opt specification** : https://refspecs.linuxfoundation.org/FHS_3.0/fhs/ch03s13.html
- **Docker volumes** : https://docs.docker.com/storage/volumes/
- **Nginx best practices** : https://www.nginx.com/resources/wiki/start/topics/tutorials/config_pitfalls/

---

## ✅ Checklist migration vers `/opt`

- [ ] Créer utilisateur `keyhome`
- [ ] Créer `/opt/keyhome/`
- [ ] Déplacer fichiers depuis `/var/www`
- [ ] Vérifier permissions (755 pour dossiers, 644 pour fichiers)
- [ ] Protéger `.env` (chmod 600)
- [ ] Mettre à jour GitLab Runner config
- [ ] Mettre à jour tous les scripts de backup
- [ ] Tester `docker compose up -d`
- [ ] Vérifier logs et volumes Docker
- [ ] Créer symlinks si nécessaire
- [ ] Nettoyer ancien emplacement (après 7j de vérification)

---

**Prochaine étape** : Lire `01-migration-serveur.md` avec les chemins `/opt/keyhome`.
