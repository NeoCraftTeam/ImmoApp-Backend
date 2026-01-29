# 📚 Documentation KeyHome - Guide de Migration & Déploiement

> **Documentation complète** pour la migration et la gestion du serveur KeyHome  
> **Version** : 1.0 | **Dernière mise à jour** : 2026-01-29

---

## 🎯 À qui s'adresse cette documentation ?

Cette documentation est conçue pour :
- ✅ **Migrer KeyHome** vers un nouveau serveur VPS
- ✅ **Comprendre l'architecture** Docker + Traefik + GitLab CI/CD
- ✅ **Déployer automatiquement** via GitLab Container Registry
- ✅ **Gérer la production** au quotidien

---

## 📖 Ordre de lecture (Numérotation logique)

### 📍 ÉTAPE 0 : Préparation

#### [`00-conventions-linux.md`](./00-conventions-linux.md)
**Durée de lecture** : 10 minutes  
**Obligatoire** : ⭐⭐⭐⭐⭐

**À lire si** :
- Vous vous demandez pourquoi utiliser `/opt` au lieu de `/var/www`
- Vous voulez comprendre les conventions Linux (FHS)
- C'est votre première migration de serveur

**Contenu** :
- Hiérarchie standard Linux (`/opt`, `/var`, `/srv`, etc.)
- Comparaison `/var/www` vs `/opt` vs `/srv`
- Structure recommandée pour KeyHome
- Permissions et propriétaires
- Organisation multi-projets

**Résultat** : Vous saurez exactement **où** placer vos fichiers.

---

### 🚀 ÉTAPE 1 : Migration du serveur

#### [`01-migration-serveur.md`](./01-migration-serveur.md)
**Durée** : 2-4 heures (selon taille de la DB)  
**Obligatoire** : ⭐⭐⭐⭐⭐

**À lire si** :
- Vous migrez vers un nouveau VPS
- Vous configurez un serveur pour la première fois
- Vous voulez restaurer une sauvegarde

**Contenu** :
- ✅ Préparation de l'ancien serveur (sauvegardes)
- ✅ Configuration du nouveau serveur (Ubuntu, Docker, Firewall)
- ✅ **Installation GitLab Runner**
- ✅ **Configuration GitLab Container Registry**
- ✅ Migration des données (DB + storage)
- ✅ Déploiement via GitLab CI/CD
- ✅ Vérification et bascule DNS
- ✅ Procédure de rollback

**Résultat** : Serveur opérationnel avec déploiement automatique.

---

### 🦊 ÉTAPE 2 : GitLab CI/CD (Workflow automatique)

#### [`02-gitlab-cicd.md`](./02-gitlab-cicd.md)
**Durée de lecture** : 20 minutes (+ 30 min de config)  
**Obligatoire** : ⭐⭐⭐⭐⭐

**À lire si** :
- Vous voulez comprendre comment fonctionne le déploiement automatique
- Vous voulez modifier le pipeline (`.gitlab-ci.yml`)
- Vous avez des erreurs dans la CI/CD

**Contenu** :
- 🏗️ Architecture CI/CD complète (diagramme)
- 🖥️ Configuration GitLab Runner
- 🐳 GitLab Container Registry (PAT, login, structure)
- 🔄 Pipeline Stages expliquées (quality, build, test, deploy...)
- 🔐 Variables & Secrets
- 💼 Workflow quotidien (dev → push → deploy)
- 🐛 Troubleshooting CI/CD

**Résultat** : Vous comprenez le workflow `git push` → déploiement automatique.

---

### 🌐 ÉTAPE 3 : Traefik (Reverse Proxy)

#### [`03-traefik-setup.md`](./03-traefik-setup.md)
**Durée** : 1-2 heures (configuration + tests)  
**Obligatoire** : ⭐⭐⭐⭐ (si vous utilisez plusieurs sous-domaines)

**À lire si** :
- Vous voulez HTTPS automatique (Let's Encrypt)
- Vous voulez gérer plusieurs sous-domaines (api.*, grafana.*, etc.)
- Vous voulez remplacer Nginx par Traefik

**Contenu** :
- 🎯 Qu'est-ce que Traefik ? (vs Nginx)
- 🏗️ Architecture & concepts (EntryPoints, Routers, Services)
- ⚙️ Installation & configuration complète
- 🔒 SSL automatique (Let's Encrypt)
- 🌐 Multi-domaines & sous-domaines
- 📊 Dashboard Traefik
- 🐛 Troubleshooting

**Résultat** : Traefik gère tous vos domaines avec SSL automatique.

---

### 🐳 ÉTAPE 4 : Docker Compose complet

#### [`04-docker-compose-complet.md`](./04-docker-compose-complet.md)
**Durée de lecture** : 15 minutes  
**Obligatoire** : ⭐⭐⭐⭐

**À lire si** :
- Vous voulez voir la configuration Docker complète
- Vous voulez ajouter de nouveaux services
- Vous voulez comprendre les labels Traefik

**Contenu** :
- 🐳 `docker-compose.traefik.yml` (Traefik séparé)
- 🏗️ `docker-compose.yml` (Application complète)
  - App (PHP-FPM)
  - Worker (Queue)
  - Web (Nginx)
  - DB (PostgreSQL + PostGIS)
  - Redis
  - Meilisearch
  - Monitoring (Prometheus, Grafana, exporters)
  - PgAdmin
- 📝 Fichier `.env` production
- 🚀 Commandes de déploiement
- 🌐 URLs finales

**Résultat** : Configuration production-ready complète.

---

## 🗂️ Documentation par cas d'usage

### 🆕 Je veux installer KeyHome pour la première fois

**Ordre de lecture** :
1. [`00-conventions-linux.md`](./00-conventions-linux.md) → Comprendre où placer les fichiers
2. [`01-migration-serveur.md`](./01-migration-serveur.md) → Installer le serveur (ignorer la partie "ancien serveur")
3. [`02-gitlab-cicd.md`](./02-gitlab-cicd.md) → Configurer le déploiement automatique
4. [`03-traefik-setup.md`](./03-traefik-setup.md) → Configurer le reverse proxy
5. [`04-docker-compose-complet.md`](./04-docker-compose-complet.md) → Référence complète

---

### 🔄 Je veux migrer vers un nouveau serveur

**Ordre de lecture** :
1. [`00-conventions-linux.md`](./00-conventions-linux.md) → Conventions (rapide)
2. [`01-migration-serveur.md`](./01-migration-serveur.md) → **SUIVRE ÉTAPE PAR ÉTAPE** ⭐
3. [`02-gitlab-cicd.md`](./02-gitlab-cicd.md) → Vérifier la CI/CD après migration
4. [`03-traefik-setup.md`](./03-traefik-setup.md) → Si vous passez à Traefik
5. [`04-docker-compose-complet.md`](./04-docker-compose-complet.md) → Référence

---

### 🐛 J'ai un problème de déploiement

**Aller directement à** :
- [`02-gitlab-cicd.md`](./02-gitlab-cicd.md) → Section "Troubleshooting"
- [`03-traefik-setup.md`](./03-traefik-setup.md) → Section "Troubleshooting"

---

### 📊 Je veux ajouter un nouveau service (ex: pgadmin)

**Aller directement à** :
- [`04-docker-compose-complet.md`](./04-docker-compose-complet.md) → Voir les exemples
- [`03-traefik-setup.md`](./03-traefik-setup.md) → Labels Traefik

---

### 🔐 Je veux configurer HTTPS / SSL

**Aller directement à** :
- [`03-traefik-setup.md`](./03-traefik-setup.md) → Section "SSL automatique"

---

## 📁 Structure des fichiers

```
.docs/
├── README.md                      # ← VOUS ÊTES ICI
├── 00-conventions-linux.md        # Conventions Linux (FHS)
├── 01-migration-serveur.md        # Migration complète
├── 02-gitlab-cicd.md              # CI/CD automatique
├── 03-traefik-setup.md            # Reverse proxy
└── 04-docker-compose-complet.md   # Configs Docker finales
```

---

## 🎓 Prérequis

### Connaissances requises

- ✅ **Linux de base** : SSH, `cd`, `ls`, `cp`, `chmod`
- ✅ **Git** : `git clone`, `git push`, `git pull`
- ✅ **Docker basics** : Comprendre conteneurs vs images
- ⚠️ **Docker Compose** : Pas obligatoire, tout est expliqué
- ⚠️ **Nginx/Apache** : Pas obligatoire (Traefik remplace)

### Outils nécessaires

**Sur votre machine locale** :
- Git client
- SSH client
- Navigateur web (pour GitLab)

**Sur le serveur VPS** :
- Ubuntu 22.04 LTS (ou Debian 12)
- Minimum 4 GB RAM
- Minimum 50 GB stockage SSD
- IP publique fixe

---

## ⏱️ Temps estimé total

| Étape | Première fois | Déjà expérimenté |
|-------|---------------|------------------|
| **Lecture docs** | 1h | 20 min |
| **Config serveur** | 2h | 30 min |
| **Migration données** | 2h | 1h |
| **GitLab Runner** | 1h | 20 min |
| **Traefik** | 1h | 30 min |
| **Tests & vérif** | 1h | 30 min |
| **TOTAL** | **~8h** | **~3h** |

💡 **Conseil** : Prévoyez une journée complète la première fois, avec beaucoup de café ☕

---

## 🆘 Support & Ressources

### Documentation officielle

- **Docker** : https://docs.docker.com/
- **GitLab CI/CD** : https://docs.gitlab.com/ee/ci/
- **Traefik** : https://doc.traefik.io/traefik/
- **Laravel Deployment** : https://laravel.com/docs/deployment
- **PostgreSQL** : https://www.postgresql.org/docs/

### En cas de problème

1. **Lire la section Troubleshooting** du document concerné
2. **Vérifier les logs** :
   ```bash
   docker compose logs -f --tail=100
   tail -f /var/log/gitlab-runner/gitlab-runner.log
   ```
3. **Checkpoint de sécurité** : Gardez l'ancien serveur en ligne 7 jours minimum

---

## 📊 Checklist migration complète

- [ ] Lire `00-conventions-linux.md`
- [ ] Sauvegarder ancien serveur (DB + storage + configs)
- [ ] Configurer nouveau VPS (Docker, firewall, utilisateur)
- [ ] Installer GitLab Runner
- [ ] Configurer accès GitLab Container Registry
- [ ] Restaurer base de données
- [ ] Restaurer fichiers storage
- [ ] Tester déploiement via GitLab CI/CD
- [ ] Configurer Traefik (optionnel)
- [ ] Tester HTTPS / SSL
- [ ] Vérifier tous les services (API, Filament, Grafana...)
- [ ] Basculer DNS
- [ ] Surveiller logs pendant 24h
- [ ] Garder ancien serveur 7 jours (backup de sécurité)
- [ ] Configurer sauvegardes automatiques
- [ ] Documenter les modifications custom

---

## 🚀 Prêt à commencer ?

**ÉTAPE 1** : Allez lire [`00-conventions-linux.md`](./00-conventions-linux.md)

Bon courage ! 💪
