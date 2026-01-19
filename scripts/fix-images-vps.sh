#!/bin/bash

# Script de diagnostic et correction du problème d'affichage des images
# À exécuter sur le VPS

echo "🔍 Diagnostic du problème d'images..."
echo ""

# 1. Vérifier que les conteneurs tournent
echo "1️⃣ Vérification des conteneurs..."
docker-compose ps

# 2. Vérifier le symlink storage dans public
echo ""
echo "2️⃣ Vérification du symlink storage..."
docker-compose exec app ls -la /var/www/public/storage

# 3. Vérifier que les fichiers existent dans storage/app/public
echo ""
echo "3️⃣ Vérification des fichiers média..."
docker-compose exec app ls -la /var/www/storage/app/public/ | head -20

# 4. Vérifier les permissions
echo ""
echo "4️⃣ Vérification des permissions du storage..."
docker-compose exec app ls -ld /var/www/storage
docker-compose exec app ls -ld /var/www/storage/app
docker-compose exec app ls -ld /var/www/storage/app/public

# 5. Tester l'accès depuis le conteneur web (Nginx)
echo ""
echo "5️⃣ Test d'accès depuis Nginx..."
docker-compose exec web ls -la /var/www/public/storage 2>&1
docker-compose exec web ls -la /var/www/storage/app/public/ 2>&1 | head -10

# 6. Vérifier la config Nginx
echo ""
echo "6️⃣ Configuration Nginx pour /storage/..."
docker-compose exec web cat /etc/nginx/conf.d/default.conf | grep -A 3 "location /storage"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔧 CORRECTIONS AUTOMATIQUES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Correction 1: Recréer le symlink si nécessaire
echo ""
echo "✅ Correction 1: Recréer le symlink storage..."
docker-compose exec app rm -f /var/www/public/storage
docker-compose exec app php artisan storage:link

# Correction 2: Corriger les permissions
echo ""
echo "✅ Correction 2: Corriger les permissions..."
docker-compose exec app chmod -R 755 /var/www/storage
docker-compose exec app chown -R www-data:www-data /var/www/storage

# Correction 3: Vérifier que le volume est bien monté
echo ""
echo "✅ Correction 3: Vérifier les volumes Docker..."
docker volume ls | grep keyhome

# Test final
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 TEST FINAL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo ""
echo "Tentative d'accès à un fichier test..."
# Liste un fichier existant
TEST_FILE=$(docker-compose exec app find /var/www/storage/app/public -name "*.jpeg" -o -name "*.jpg" -o -name "*.png" | head -1 | tr -d '\r')

if [ -n "$TEST_FILE" ]; then
    echo "Fichier trouvé: $TEST_FILE"
    
    # Extraire le chemin relatif après storage/app/public/
    REL_PATH=$(echo "$TEST_FILE" | sed 's|.*/storage/app/public/||')
    
    echo "Test d'accès depuis Nginx: /storage/$REL_PATH"
    docker-compose exec web test -f "/var/www/storage/app/public/$REL_PATH" && echo "✅ Nginx peut accéder au fichier" || echo "❌ Nginx ne peut PAS accéder au fichier"
    
    echo ""
    echo "Test HTTP (à exécuter depuis votre navigateur):"
    echo "👉 http://votre-domaine.com/storage/$REL_PATH"
else
    echo "❌ Aucun fichier image trouvé dans storage/app/public/"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📝 RÉSUMÉ"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Si les images ne s'affichent toujours pas:"
echo ""
echo "1. Vérifiez que le volume keyhome-storage-data contient les images"
echo "   docker volume inspect keyhome-storage-data"
echo ""
echo "2. Vérifiez les logs Nginx pour voir l'erreur exacte:"
echo "   docker-compose logs web | grep -i error"
echo ""
echo "3. Uploadez une nouvelle image de test depuis Filament"
echo "   et vérifiez qu'elle apparaît bien dans storage/app/public/"
echo ""
echo "4. Si nécessaire, redémarrez les conteneurs:"
echo "   docker-compose restart app web"
echo ""
