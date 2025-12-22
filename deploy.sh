#!/bin/bash

# Script de déploiement pour Laravel en production
# Usage: ./deploy.sh

set -e  # Arrêter le script en cas d'erreur

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages colorés
print_message() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_step() {
    echo -e "\n${BLUE}🔄 $1${NC}"
}

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "artisan" ]; then
    print_error "Ce script doit être exécuté dans le répertoire racine de Laravel"
    exit 1
fi

print_message "🚀 Démarrage du script de déploiement Laravel"
print_message "📍 Répertoire: $(pwd)"

# 0. Récupérer le dernier code
print_step "Récupération du dernier code (Git Pull)"
git pull origin main || {
    print_error "Git pull a échoué"
    exit 1
}
print_success "Code mis à jour avec succès"

# 1. Mettre l'application en maintenance
print_step "Activation du mode maintenance"
php artisan down --retry=60 --secret="deploy-secret-$(date +%s)" || {
    print_warning "Impossible d'activer le mode maintenance (peut-être déjà actif)"
}

# 2. Nettoyage des caches
print_step "Nettoyage des caches"
php artisan config:clear
print_success "Cache de configuration nettoyé"

php artisan cache:clear
print_success "Cache d'application nettoyé"

php artisan route:clear
print_success "Cache des routes nettoyé"

php artisan optimize:clear
print_success "Cache des optimisations nettoyé"

php artisan event:clear 2>/dev/null || print_warning "Cache des événements non disponible"

# 3. Installation/Mise à jour des dépendances
print_step "Mise à jour des dépendances Composer"
composer install --no-dev --optimize-autoloader --no-interaction
print_success "Dépendances Composer mises à jour"

# 4. Migration de la base de données
# 4. Migration de la base de données
print_step "Migration de la base de données"
php artisan migrate --force
print_success "Migrations exécutées"

# 5. Génération de L5 Swagger
print_step "Génération de la documentation L5 Swagger"
php artisan l5-swagger:generate
print_success "Documentation Swagger générée"

# 6. Gestion des permissions
print_step "Configuration des permissions"
# On donne la propriété à l'utilisateur du runner et au groupe web
sudo chown -R gitlab-runner:www-data storage/ bootstrap/cache/
print_success "Propriétaire défini sur gitlab-runner:www-data"

# Permissions : 775 pour les dossiers, 664 pour les fichiers (groupe writeable)
sudo find storage/ -type d -exec chmod 775 {} \;
sudo find storage/ -type f -exec chmod 664 {} \;
sudo find bootstrap/cache/ -type d -exec chmod 775 {} \;
sudo find bootstrap/cache/ -type f -exec chmod 664 {} \;
print_success "Permissions configurées (775/664)"

# 7. Reconstruction des caches de production
print_step "Reconstruction des caches pour la production"
php artisan config:cache
print_success "Cache de configuration créé"

php artisan route:cache
print_success "Cache des routes créé"

php artisan view:cache
print_success "Cache des vues créé"

php artisan event:cache 2>/dev/null || print_warning "Cache des événements non disponible"

# 8. Optimisation générale
print_step "Optimisation de l'application"
php artisan optimize
print_success "Application optimisée"

# 9. Redémarrage des services
print_step "Redémarrage des services"

# Détection automatique de la version PHP
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
print_message "Version PHP détectée: $PHP_VERSION"

# Redémarrage PHP-FPM
if systemctl is-active --quiet php${PHP_VERSION}-fpm; then
    sudo systemctl restart php${PHP_VERSION}-fpm
    print_success "PHP-FPM redémarré"
elif systemctl is-active --quiet php-fpm; then
    sudo systemctl restart php-fpm
    print_success "PHP-FPM redémarré"
else
    print_warning "Service PHP-FPM non trouvé ou non actif"
fi

# Redémarrage du serveur web
if systemctl is-active --quiet nginx; then
    sudo systemctl restart nginx
    print_success "Nginx redémarré"
elif systemctl is-active --quiet apache2; then
    sudo systemctl restart apache2
    print_success "Apache2 redémarré"
else
    print_warning "Aucun serveur web (Nginx/Apache) détecté"
fi

# 10. Test de santé de l'application
print_step "Test de santé de l'application"
if command -v curl &> /dev/null; then
    DOMAIN=$(php artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -n1)
    if [[ -n "$DOMAIN" && "$DOMAIN" != "http://localhost" ]]; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$DOMAIN" || echo "000")
        if [[ "$HTTP_CODE" == "200" ]]; then
            print_success "Application accessible (HTTP $HTTP_CODE)"
        else
            print_warning "Application retourne HTTP $HTTP_CODE"
        fi
    else
        print_warning "Impossible de déterminer l'URL de l'application"
    fi
else
    print_warning "curl non disponible pour tester l'application"
fi

# 11. Nettoyage des logs anciens (optionnel)
print_step "Nettoyage des anciens logs"
find storage/logs/ -name "*.log" -mtime +7 -delete 2>/dev/null || true
print_success "Anciens logs nettoyés (>7 jours)"

# 12. Désactivation du mode maintenance
print_step "Désactivation du mode maintenance"
php artisan up
print_success "Mode maintenance désactivé"

# 13. Résumé final
print_step "🎉 Déploiement terminé avec succès!"
echo
print_message "📊 Résumé du déploiement:"
echo "  • Caches nettoyés et reconstruits"
echo "  • Dépendances mises à jour"
echo "  • Documentation Swagger générée"
echo "  • Permissions configurées"
echo "  • Services redémarrés"
echo "  • Application optimisée"
echo
print_success "🚀 Votre application Laravel est prête en production!"

# Affichage des informations utiles
echo
print_message "📝 Informations utiles:"
echo "  • Logs: tail -f storage/logs/laravel.log"
echo "  • Routes: php artisan route:list"
echo "  • Status: systemctl status php${PHP_VERSION}-fpm nginx"
echo
print_message "⏰ Déploiement terminé à $(date)"
