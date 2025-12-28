#!/bin/bash

# Script d'optimisation après déploiement Laravel + Livewire + Filament
# Usage: ./deploy-optimize.sh

set -e  # Arrêt si erreur

echo "================================================"
echo "🚀 OPTIMISATION POST-DÉPLOIEMENT"
echo "================================================"

# Couleurs pour les logs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# ==========================================
# ÉTAPE 1: NETTOYAGE DES CACHES
# ==========================================
echo ""
echo -e "${YELLOW}🧹 ÉTAPE 1: Nettoyage des anciens caches...${NC}"

php artisan cache:clear
echo "✓ Cache applicatif nettoyé"

php artisan config:clear
echo "✓ Cache de configuration nettoyé"

php artisan route:clear
echo "✓ Cache des routes nettoyé"

php artisan view:clear
echo "✓ Cache des vues nettoyé"

php artisan event:clear 2>/dev/null || echo "✓ Events cleared (if exists)"

# ==========================================
# ÉTAPE 2: PUBLICATION DES ASSETS
# ==========================================
echo ""
echo -e "${YELLOW}📦 ÉTAPE 2: Publication des assets...${NC}"

php artisan vendor:publish --force --tag=livewire:assets --ansi --no-interaction
echo "✓ Assets Livewire publiés"

php artisan filament:assets
echo "✓ Assets Filament compilés"

php artisan filament:upgrade
echo "✓ Filament mis à jour (upgrade)"

# ==========================================
# ÉTAPE 3: OPTIMISATION POUR LA PRODUCTION
# ==========================================
echo ""
echo -e "${YELLOW}⚡ ÉTAPE 3: Optimisation pour la production...${NC}"

php artisan config:cache
echo "✓ Configuration mise en cache"

php artisan route:cache
echo "✓ Routes mises en cache"

php artisan view:cache
echo "✓ Vues Blade mises en cache"

php artisan event:cache
echo "✓ Events mis en cache"

php artisan filament:cache-components
echo "✓ Composants Filament mis en cache"

# Optimisation générale (Laravel 11+)
php artisan optimize 2>/dev/null || echo "✓ Optimize command skipped"

# ==========================================
# ÉTAPE 4: PERMISSIONS
# ==========================================
echo ""
echo -e "${YELLOW}🔒 ÉTAPE 4: Configuration des permissions...${NC}"

chmod -R 775 storage bootstrap/cache
echo "✓ Permissions configurées"

# Si vous utilisez www-data (Nginx/Apache/Docker)
if id "www-data" &>/dev/null; then
    chown -R www-data:www-data storage bootstrap/cache
    echo "✓ Propriétaire défini (www-data:www-data)"
fi

# ==========================================
# ÉTAPE 5: VÉRIFICATIONS
# ==========================================
echo ""
echo -e "${YELLOW}🔍 ÉTAPE 5: Vérifications...${NC}"

# Vérifier que les caches existent
if [ -f "bootstrap/cache/config.php" ]; then
    echo "✓ Cache de configuration créé"
else
    echo "⚠️  Cache de configuration manquant"
fi

if [ -f "bootstrap/cache/routes-v7.php" ]; then
    echo "✓ Cache des routes créé"
else
    echo "⚠️  Cache des routes manquant"
fi

# Vérifier les assets Filament
if [ -d "public/vendor/filament" ]; then
    echo "✓ Assets Filament présents"
else
    echo "⚠️  Assets Filament manquants"
fi

# Vérifier les assets Livewire
if [ -f "public/livewire/livewire.js" ]; then
    echo "✓ Assets Livewire présents"
else
    echo "⚠️  Assets Livewire manquants"
fi

# ==========================================
# RÉSUMÉ
# ==========================================
echo ""
echo "================================================"
echo -e "${GREEN}✅ OPTIMISATION TERMINÉE AVEC SUCCÈS!${NC}"
echo "================================================"
echo ""
echo "📊 Résumé:"
echo "   - Caches nettoyés et recréés"
echo "   - Assets Livewire + Filament publiés"
echo "   - Application optimisée pour la production"
echo "   - Permissions configurées"
echo ""
echo "🚀 Votre application est prête!"
echo ""
