#!/bin/bash

# Script d'optimisation post-déploiement Laravel + Livewire + Filament
#
# NOTE: Livewire and Filament static assets (public/livewire/, public/vendor/filament/)
# are now published during the Docker image build (Dockerfile stage 2).
# This script is ONLY responsible for rebuilding runtime caches that depend on
# the live .env (config, routes, views, events) — things that cannot be done
# at build time.
#
# Usage: bash resetFilamentLivewire.sh

set -e

echo "================================================"
echo "🚀 OPTIMISATION POST-DÉPLOIEMENT"
echo "================================================"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

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
# ÉTAPE 2: OPTIMISATION POUR LA PRODUCTION
# ==========================================
echo ""
echo -e "${YELLOW}⚡ ÉTAPE 2: Optimisation pour la production...${NC}"

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

# ==========================================
# ÉTAPE 3: PERMISSIONS
# ==========================================
echo ""
echo -e "${YELLOW}🔒 ÉTAPE 3: Configuration des permissions...${NC}"

chmod -R 775 storage bootstrap/cache
echo "✓ Permissions configurées"

if id "www-data" &>/dev/null; then
    chown -R www-data:www-data storage bootstrap/cache
    echo "✓ Propriétaire défini (www-data:www-data)"
fi

# ==========================================
# ÉTAPE 4: VÉRIFICATIONS
# ==========================================
echo ""
echo -e "${YELLOW}🔍 ÉTAPE 4: Vérifications...${NC}"

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

# Assets are baked into the image — verify they survived code-sync
if [ -d "public/vendor/filament" ]; then
    echo "✓ Assets Filament présents"
else
    echo "⚠️  Assets Filament manquants (check Docker build logs)"
fi

if [ -f "public/livewire/livewire.js" ]; then
    echo "✓ Assets Livewire présents"
else
    echo "⚠️  Assets Livewire manquants (check Docker build logs)"
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
echo "   - Caches nettoyés et recréés (config/routes/views/events)"
echo "   - Composants Filament mis en cache"
echo "   - Permissions configurées"
echo ""
echo "🚀 Votre application est prête!"
echo ""
