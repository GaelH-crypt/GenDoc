#!/bin/bash

# Script de sauvegarde automatique pour Gendoc
# Usage: ./scripts/backup.sh [destination]

set -e

# Configuration
APP_DIR="/var/www/html/gendoc"
BACKUP_DIR="${1:-/var/backups/gendoc}"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="gendoc_backup_$DATE"
TEMP_DIR="/tmp/$BACKUP_NAME"

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Fonction de logging
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERREUR] $1${NC}"
    exit 1
}

warning() {
    echo -e "${YELLOW}[ATTENTION] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Vérification des prérequis
check_prerequisites() {
    if [ ! -d "$APP_DIR" ]; then
        error "Le répertoire de l'application n'existe pas: $APP_DIR"
    fi
    
    if ! command -v mysql &> /dev/null; then
        error "MySQL n'est pas installé ou accessible"
    fi
    
    if ! command -v tar &> /dev/null; then
        error "tar n'est pas installé"
    fi
}

# Création des répertoires de sauvegarde
create_backup_dirs() {
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$TEMP_DIR"
    mkdir -p "$TEMP_DIR/files"
    mkdir -p "$TEMP_DIR/database"
}

# Sauvegarde des fichiers
backup_files() {
    log "Sauvegarde des fichiers..."
    
    # Sauvegarde du code source (excluant les fichiers temporaires)
    tar -czf "$TEMP_DIR/files/source.tar.gz" \
        --exclude="$APP_DIR/vendor" \
        --exclude="$APP_DIR/storage/logs" \
        --exclude="$APP_DIR/storage/documents" \
        --exclude="$APP_DIR/public/uploads" \
        --exclude="$APP_DIR/.git" \
        --exclude="$APP_DIR/node_modules" \
        -C "$APP_DIR" .
    
    # Sauvegarde des fichiers de stockage importants
    if [ -d "$APP_DIR/storage" ]; then
        tar -czf "$TEMP_DIR/files/storage.tar.gz" \
            --exclude="$APP_DIR/storage/logs" \
            --exclude="$APP_DIR/storage/documents" \
            -C "$APP_DIR" storage
    fi
    
    # Sauvegarde des fichiers de configuration
    if [ -f "$APP_DIR/src/config/config.php" ]; then
        cp "$APP_DIR/src/config/config.php" "$TEMP_DIR/files/"
    fi
    
    if [ -f "$APP_DIR/storage/ldap_config.json" ]; then
        cp "$APP_DIR/storage/ldap_config.json" "$TEMP_DIR/files/"
    fi
}

# Sauvegarde de la base de données
backup_database() {
    log "Sauvegarde de la base de données..."
    
    # Lecture de la configuration de la base de données
    if [ -f "$APP_DIR/src/config/config.php" ]; then
        # Extraction des paramètres de la base de données
        DB_HOST=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['host'];")
        DB_NAME=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['name'];")
        DB_USER=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['user'];")
        DB_PASS=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['pass'];")
        
        # Sauvegarde de la base de données
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$TEMP_DIR/database/backup.sql"
        
        # Compression de la sauvegarde SQL
        gzip "$TEMP_DIR/database/backup.sql"
    else
        warning "Fichier de configuration non trouvé, impossible de sauvegarder la base de données"
    fi
}

# Création de l'archive finale
create_final_archive() {
    log "Création de l'archive finale..."
    
    # Création du fichier de métadonnées
    cat > "$TEMP_DIR/metadata.txt" << EOF
Sauvegarde Gendoc
Date: $(date)
Version: $(cat "$APP_DIR/VERSION" 2>/dev/null || echo "Inconnue")
Taille des fichiers: $(du -sh "$TEMP_DIR/files" | cut -f1)
Taille de la base de données: $(du -sh "$TEMP_DIR/database" | cut -f1)
EOF
    
    # Création de l'archive finale
    tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C "$TEMP_DIR" .
    
    # Calcul de la taille et du checksum
    SIZE=$(du -h "$BACKUP_DIR/$BACKUP_NAME.tar.gz" | cut -f1)
    CHECKSUM=$(sha256sum "$BACKUP_DIR/$BACKUP_NAME.tar.gz" | cut -d' ' -f1)
    
    log "Sauvegarde créée: $BACKUP_DIR/$BACKUP_NAME.tar.gz"
    log "Taille: $SIZE"
    log "Checksum SHA256: $CHECKSUM"
}

# Nettoyage des anciennes sauvegardes
cleanup_old_backups() {
    log "Nettoyage des anciennes sauvegardes..."
    
    # Garder les 30 dernières sauvegardes
    find "$BACKUP_DIR" -name "gendoc_backup_*.tar.gz" -type f -mtime +30 -delete
    
    # Afficher l'espace utilisé
    TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
    BACKUP_COUNT=$(find "$BACKUP_DIR" -name "gendoc_backup_*.tar.gz" | wc -l)
    
    log "Espace utilisé: $TOTAL_SIZE"
    log "Nombre de sauvegardes: $BACKUP_COUNT"
}

# Nettoyage des fichiers temporaires
cleanup_temp() {
    log "Nettoyage des fichiers temporaires..."
    rm -rf "$TEMP_DIR"
}

# Vérification de l'intégrité de la sauvegarde
verify_backup() {
    log "Vérification de l'intégrité de la sauvegarde..."
    
    if tar -tzf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" > /dev/null 2>&1; then
        log "Sauvegarde vérifiée avec succès"
    else
        error "La sauvegarde semble corrompue"
    fi
}

# Fonction principale
main() {
    echo -e "${BLUE}"
    echo "=========================================="
    echo "  Sauvegarde automatique de Gendoc"
    echo "=========================================="
    echo -e "${NC}"
    
    check_prerequisites
    create_backup_dirs
    backup_files
    backup_database
    create_final_archive
    verify_backup
    cleanup_old_backups
    cleanup_temp
    
    log "Sauvegarde terminée avec succès !"
}

# Gestion des erreurs
trap 'error "Erreur lors de la sauvegarde"' ERR

# Exécution du script
main "$@" 