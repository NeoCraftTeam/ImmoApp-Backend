#!/bin/bash

# Détection de la commande docker-compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo "❌ Erreur: Ni 'docker-compose' ni 'docker compose' n'ont été trouvés."
    exit 1
fi

echo "✅ Utilisation de la commande: $DOCKER_COMPOSE"

# Vérification de l'espace disque
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')
if [ "$DISK_USAGE" -gt 90 ]; then
    echo "⚠️  ATTENTION: Disque plein à ${DISK_USAGE}% !"
    echo "   Tentative de nettoyage Docker..."
    docker system prune -f
else
    echo "✅ Espace disque OK (${DISK_USAGE}%)"
fi

echo "🔍 Diagnostic du problème d'images..."
echo ""

# 1. Vérifier que les conteneurs tournent
echo "1️⃣ Vérification des conteneurs..."
$DOCKER_COMPOSE ps

# 2. Vérifier le symlink storage dans public
echo ""
echo "2️⃣ Vérification du symlink storage..."
$DOCKER_COMPOSE exec app ls -la /var/www/public/storage

# 3. Vérifier que les fichiers existent dans storage/app/public
echo ""
echo "3️⃣ Vérification des fichiers média..."
$DOCKER_COMPOSE exec app ls -la /var/www/storage/app/public/ | head -20

# 4. Vérifier les permissions
echo ""
echo "4️⃣ Vérification des permissions du storage..."
$DOCKER_COMPOSE exec app ls -ld /var/www/storage
$DOCKER_COMPOSE exec app ls -ld /var/www/storage/app
$DOCKER_COMPOSE exec app ls -ld /var/www/storage/app/public

# 5. Tester l'accès depuis le conteneur web (Nginx)
echo ""
echo "5️⃣ Test d'accès depuis Nginx..."
$DOCKER_COMPOSE exec web ls -la /var/www/public/storage 2>&1
$DOCKER_COMPOSE exec web ls -la /var/www/storage/app/public/ 2>&1 | head -10

# 6. Vérifier la config Nginx
echo ""
echo "6️⃣ Configuration Nginx pour /storage/..."
$DOCKER_COMPOSE exec web cat /etc/nginx/conf.d/default.conf | grep -A 3 "location /storage"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔧 CORRECTIONS AUTOMATIQUES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Correction 1: Recréer le symlink si nécessaire
echo ""
echo "✅ Correction 1: Recréer le symlink storage..."
$DOCKER_COMPOSE exec app rm -f /var/www/public/storage
$DOCKER_COMPOSE exec app php artisan storage:link

# Correction 2: Corriger les permissions
echo ""
echo "✅ Correction 2: Corriger les permissions..."
$DOCKER_COMPOSE exec app chmod -R 755 /var/www/storage
$DOCKER_COMPOSE exec app chown -R www-data:www-data /var/www/storage

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
TEST_FILE=$($DOCKER_COMPOSE exec app find /var/www/storage/app/public -name "*.jpeg" -o -name "*.jpg" -o -name "*.png" | head -1 | tr -d '\r')

if [ -n "$TEST_FILE" ]; then
    echo "Fichier trouvé: $TEST_FILE"
    
    # Extraire le chemin relatif après storage/app/public/
    REL_PATH=$(echo "$TEST_FILE" | sed 's|.*/storage/app/public/||')
    
    echo "Test d'accès depuis Nginx: /storage/$REL_PATH"
    $DOCKER_COMPOSE exec web test -f "/var/www/storage/app/public/$REL_PATH" && echo "✅ Nginx peut accéder au fichier" || echo "❌ Nginx ne peut PAS accéder au fichier"
    
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
echo "   $DOCKER_COMPOSE logs web | grep -i error"
echo ""
echo "3. Uploadez une nouvelle image de test depuis Filament"
echo "   et vérifiez qu'elle apparaît bien dans storage/app/public/"
echo ""
echo "4. Si nécessaire, redémarrez les conteneurs:"
echo "   $DOCKER_COMPOSE restart app web"
echo ""
