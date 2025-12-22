#!/bin/bash

# Configuration
PROJECT_DIR="/var/www/ImmoApp-Backend"
BACKUP_DIR="${PROJECT_DIR}/storage/backups"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_NAME="keyhome_db_${TIMESTAMP}.sql.gz"
DB_CONTAINER="keyhome-db"
DB_NAME="keyhome"    # Nom de la DB défini dans docker-compose
DB_USER="postgres"  # Utilisateur par défaut de l'image PostGIS

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}[$(date)] Démarrage de la sauvegarde de la base de données...${NC}"

# Créer le répertoire de backup s'il n'existe pas
mkdir -p ${BACKUP_DIR}

# Exécuter pg_dump à l'intérieur du conteneur Docker et compresser à la volée
docker exec ${DB_CONTAINER} pg_dump -U ${DB_USER} ${DB_NAME} | gzip > ${BACKUP_DIR}/${BACKUP_NAME}

# Vérifier si la sauvegarde a réussi
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Sauvegarde réussie : ${BACKUP_DIR}/${BACKUP_NAME}${NC}"
    # Nettoyage : Garder seulement les sauvegardes de moins de 30 jours
    find ${BACKUP_DIR} -name "keyhome_db_*.sql.gz" -mtime +30 -delete
    echo -e "${BLUE}🧹 Nettoyage terminé (Sauvegardes > 30 jours supprimées).${NC}"
else
    echo -e "\033[0;31m❌ Échec de la sauvegarde !\033[0m"
    exit 1
fi

echo -e "${BLUE}[$(date)] Processus de sauvegarde terminé.${NC}"
