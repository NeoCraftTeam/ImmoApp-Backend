# Guide de dépannage - Images 404

## 🔍 Diagnostic du problème

Le problème des images qui retournent 404 peut avoir plusieurs causes:

### Architecture du système

```
┌─────────────────────────────────────────────────────────┐
│ VPS Production                                          │
│                                                         │
│  ┌──────────┐         ┌──────────┐      ┌──────────┐  │
│  │  Nginx   │────────▶│ PHP-FPM  │──────│ Storage  │  │
│  │  (web)   │         │  (app)   │      │ Volume   │  │
│  └──────────┘         └──────────┘      └──────────┘  │
│       │                                        │        │
│       │                                        │        │
│       └────────────────────────────────────────┘        │
│           Nginx doit pouvoir accéder au storage         │
└─────────────────────────────────────────────────────────┘
```

### Causes possibles

1. **Symlink manquant** : `public/storage` → `storage/app/public`
2. **Permissions incorrectes** sur le dossier `storage/`
3. **Volume Docker** : Les fichiers ne sont pas dans le bon volume
4. **Configuration Nginx** : Le bloc `location /storage/` mal configuré
5. **Fichiers pas synchronisés** : Les uploads locaux ne sont pas sur le VPS

---

## 🚀 Solution rapide (VPS)

### Étape 1: Exécuter le script de diagnostic

```bash
# Sur le VPS
cd /chemin/vers/ImmoApp-Backend
./scripts/fix-images-vps.sh
```

Ce script va :
- ✅ Diagnostiquer tous les problèmes
- ✅ Corriger automatiquement les permissions
- ✅ Recréer le symlink si nécessaire
- ✅ Tester l'accès aux fichiers

### Étape 2: Vérifications manuelles

```bash
# 1. Vérifier que les images existent bien
docker-compose exec app ls -la /var/www/storage/app/public/

# 2. Vérifier le symlink
docker-compose exec app ls -la /var/www/public/storage

# 3. Tester l'accès depuis Nginx
docker-compose exec web ls -la /var/www/storage/app/public/

# 4. Vérifier les logs Nginx
docker-compose logs web | grep -i "404\|error" | tail -20
```

---

## 🔧 Solutions détaillées par cause

### Problème 1: Symlink manquant

**Symptôme:** `public/storage` n'existe pas ou pointe vers le mauvais endroit

**Solution:**
```bash
docker-compose exec app rm -f /var/www/public/storage
docker-compose exec app php artisan storage:link
```

### Problème 2: Permissions incorrectes

**Symptôme:** Nginx reçoit "Permission denied"

**Solution:**
```bash
# Corriger les permissions et propriétaires
docker-compose exec app chmod -R 755 /var/www/storage
docker-compose exec app chown -R www-data:www-data /var/www/storage

# Vérifier
docker-compose exec app ls -ld /var/www/storage
# Devrait afficher: drwxr-xr-x ... www-data www-data
```

### Problème 3: Configuration Nginx incorrecte

**Symptôme:** Nginx ne sait pas comment servir `/storage/`

**Vérifier la config actuelle:**
```bash
docker-compose exec web cat /etc/nginx/conf.d/default.conf | grep -A 5 "location /storage"
```

**Devrait contenir:**
```nginx
location /storage/ {
    alias /var/www/storage/app/public/;
    try_files $uri =404;
}
```

**Si absent, modification nécessaire dans `.docker/nginx/conf.d/default.conf`**

### Problème 4: Volume Docker

**Symptôme:** Les fichiers uploadés disparaissent après redémarrage

**Solution:**
```bash
# Vérifier que le volume existe et est monté
docker volume ls | grep keyhome-storage

# Inspecter le volume
docker volume inspect keyhome-storage-data

# Vérifier le montage dans docker-compose.yml
```

Dans `docker-compose.yml`, l'app doit avoir:
```yaml
volumes:
  - keyhome-storage-data:/var/www/storage
```

### Problème 5: Fichiers pas uploadés sur le VPS

**Symptôme:** Les images fonctionnent en local mais pas sur le VPS

**Cause:** Les uploads sont faits en local, pas sur le VPS

**Solution:**
- **Option A:** Uploadez les images **directement depuis le VPS** via Filament
- **Option B:** Copiez manuellement les fichiers locaux vers le VPS:

```bash
# Depuis votre machine locale
rsync -avz storage/app/public/ user@vps:/path/to/project/storage/app/public/

# Puis sur le VPS, corrigez les permissions
ssh user@vps
cd /path/to/project
docker-compose exec app chown -R www-data:www-data /var/www/storage
```

---

## 🧪 Tests après corrections

### Test 1: Vérifier qu'un fichier existe

```bash
# Lister les médias
docker-compose exec app find /var/www/storage/app/public -name "*.jpeg" | head -5

# Noter un chemin, par exemple: /var/www/storage/app/public/48/600.jpeg
```

### Test 2: Tester depuis Nginx

```bash
# Si le fichier est 48/600.jpeg
docker-compose exec web test -f /var/www/storage/app/public/48/600.jpeg && echo "OK" || echo "NOT FOUND"
```

### Test 3: Tester l'URL complète

```bash
# Depuis le VPS
curl -I http://localhost/storage/48/600.jpeg

# Devrait retourner: HTTP/1.1 200 OK
# Si 404, il y a encore un problème
```

### Test 4: Depuis le navigateur

Ouvrez: `https://keyhomeback.neocraft.dev/storage/48/600.jpeg`

---

## 📊 Commandes de diagnostic avancées

### Vérifier les volumes Docker

```bash
# Lister tous les volumes
docker volume ls | grep keyhome

# Inspecter le volume storage
docker volume inspect keyhome-storage-data

# Trouver où Docker stocke physiquement les données
docker volume inspect keyhome-storage-data --format '{{ .Mountpoint }}'
```

### Vérifier les montages dans les conteneurs

```bash
# Dans le conteneur app
docker-compose exec app df -h | grep storage

# Dans le conteneur web (Nginx)
docker-compose exec web df -h
docker-compose exec web mount | grep www
```

### Comparer les fichiers entre conteneurs

```bash
# Nombre de fichiers dans app
docker-compose exec app find /var/www/storage/app/public -type f | wc -l

# Nginx peut-il voir les mêmes fichiers?
docker-compose exec web find /var/www/storage/app/public -type f | wc -l

# Les deux devraient retourner le même nombre
```

---

## ⚠️ Cas particulier: Images en base de données vs fichiers

Spatie Media Library stocke:
- Les **métadonnées** dans la table `media`
- Les **fichiers physiques** dans `storage/app/public/`

Vérifier la cohérence:

```bash
# Compter les médias en DB
docker-compose exec app php artisan tinker
>>> \App\Models\Media::count();

# Compter les fichiers physiques
docker-compose exec app find /var/www/storage/app/public -name "*.jpeg" -o -name "*.jpg" -o -name "*.png" | wc -l
```

Si les nombres sont très différents, il y a un problème de synchronisation.

---

## 🎯 Checklist complète

Avant de déclarer le problème résolu:

- [ ] Les fichiers existent dans `storage/app/public/` (vérifier avec `ls`)
- [ ] Le symlink `public/storage` existe et pointe vers `../storage/app/public`
- [ ] Les permissions sont `755` et le propriétaire est `www-data:www-data`
- [ ] Nginx peut lire les fichiers (test avec `docker-compose exec web test -f ...`)
- [ ] La configuration Nginx contient le bloc `location /storage/`
- [ ] Le volume `keyhome-storage-data` est bien monté dans les deux conteneurs
- [ ] Une URL de test retourne 200 OK: `curl -I https://votre-domaine.com/storage/test.jpeg`
- [ ] Les images s'affichent dans le navigateur

---

## 🆘 Si rien ne fonctionne

### Option nucléaire: Réinitialisation complète

```bash
# ⚠️ ATTENTION: Cela va supprimer tous les médias uploadés!

# 1. Arrêter les conteneurs
docker-compose down

# 2. Supprimer le volume storage (⚠️ perte de données!)
docker volume rm keyhome-storage-data

# 3. Recréer tout depuis zéro
docker-compose up -d

# 4. Recréer le symlink
docker-compose exec app php artisan storage:link

# 5. Re-uploader les images via Filament
```

### Contacter le support

Si le problème persiste, collectez ces informations:

```bash
# Logs complets
docker-compose logs web > nginx-logs.txt
docker-compose logs app > app-logs.txt

# Configuration Nginx
docker-compose exec web cat /etc/nginx/conf.d/default.conf > nginx-config.txt

# État des volumes
docker volume inspect keyhome-storage-data > volume-info.txt
docker-compose exec app ls -laR /var/www/storage/app/public > storage-tree.txt
```

---

## 📚 Ressources

- [Documentation Spatie Media Library](https://spatie.be/docs/laravel-medialibrary)
- [Laravel Storage](https://laravel.com/docs/filesystem)
- [Nginx Configuration](https://nginx.org/en/docs/)
- [Docker Volumes](https://docs.docker.com/storage/volumes/)
