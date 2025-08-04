#!/bin/bash

# Script de restauration pour Gendoc
# Usage: ./scripts/restore.sh [fichier_sauvegarde]

set -e

# Configuration
APP_DIR="/var/www/html/gendoc"
BACKUP_DIR="/var/backups/gendoc"
TEMP_DIR="/tmp/gendoc_restore_$$"

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

# Sélection du fichier de sauvegarde
select_backup_file() {
    if [ -n "$1" ]; then
        BACKUP_FILE="$1"
    else
        # Lister les sauvegardes disponibles
        echo "Sauvegardes disponibles:"
        ls -la "$BACKUP_DIR"/gendoc_backup_*.tar.gz 2>/dev/null | nl || {
            error "Aucune sauvegarde trouvée dans $BACKUP_DIR"
        }
        
        echo
        read -p "Entrez le numéro de la sauvegarde à restaurer: " BACKUP_NUM
        
        BACKUP_FILE=$(ls "$BACKUP_DIR"/gendoc_backup_*.tar.gz | sed -n "${BACKUP_NUM}p")
        
        if [ ! -f "$BACKUP_FILE" ]; then
            error "Fichier de sauvegarde invalide"
        fi
    fi
    
    log "Sauvegarde sélectionnée: $BACKUP_FILE"
}

# Création du répertoire temporaire
create_temp_dir() {
    mkdir -p "$TEMP_DIR"
    log "Répertoire temporaire créé: $TEMP_DIR"
}

# Extraction de la sauvegarde
extract_backup() {
    log "Extraction de la sauvegarde..."
    
    if ! tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"; then
        error "Erreur lors de l'extraction de la sauvegarde"
    fi
    
    log "Sauvegarde extraite avec succès"
}

# Sauvegarde de l'état actuel
backup_current_state() {
    log "Sauvegarde de l'état actuel..."
    
    CURRENT_BACKUP="$BACKUP_DIR/pre_restore_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
    
    tar -czf "$CURRENT_BACKUP" \
        --exclude="$APP_DIR/vendor" \
        --exclude="$APP_DIR/storage/logs" \
        --exclude="$APP_DIR/storage/documents" \
        --exclude="$APP_DIR/public/uploads" \
        -C "$APP_DIR" .
    
    log "État actuel sauvegardé: $CURRENT_BACKUP"
}

# Restauration des fichiers
restore_files() {
    log "Restauration des fichiers..."
    
    # Restauration du code source
    if [ -f "$TEMP_DIR/files/source.tar.gz" ]; then
        tar -xzf "$TEMP_DIR/files/source.tar.gz" -C "$APP_DIR"
        log "Code source restauré"
    fi
    
    # Restauration des fichiers de stockage
    if [ -f "$TEMP_DIR/files/storage.tar.gz" ]; then
        tar -xzf "$TEMP_DIR/files/storage.tar.gz" -C "$APP_DIR"
        log "Fichiers de stockage restaurés"
    fi
    
    # Restauration des fichiers de configuration
    if [ -f "$TEMP_DIR/files/config.php" ]; then
        cp "$TEMP_DIR/files/config.php" "$APP_DIR/src/config/"
        log "Configuration restaurée"
    fi
    
    if [ -f "$TEMP_DIR/files/ldap_config.json" ]; then
        cp "$TEMP_DIR/files/ldap_config.json" "$APP_DIR/storage/"
        log "Configuration LDAP restaurée"
    fi
}

# Restauration de la base de données
restore_database() {
    log "Restauration de la base de données..."
    
    if [ -f "$TEMP_DIR/database/backup.sql.gz" ]; then
        # Lecture de la configuration de la base de données
        if [ -f "$APP_DIR/src/config/config.php" ]; then
            DB_HOST=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['host'];")
            DB_NAME=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['name'];")
            DB_USER=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['user'];")
            DB_PASS=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['pass'];")
            
            # Décompression et restauration
            gunzip -c "$TEMP_DIR/database/backup.sql.gz" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
            
            log "Base de données restaurée"
        else
            warning "Fichier de configuration non trouvé, impossible de restaurer la base de données"
        fi
    else
        warning "Aucune sauvegarde de base de données trouvée"
    fi
}

# Mise à jour des permissions
update_permissions() {
    log "Mise à jour des permissions..."
    
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 755 "$APP_DIR"
    chmod -R 777 "$APP_DIR/storage"
    chmod -R 777 "$APP_DIR/public/uploads"
    
    log "Permissions mises à jour"
}

# Nettoyage du cache
clear_cache() {
    log "Nettoyage du cache..."
    
    # Suppression des fichiers de cache PHP
    find "$APP_DIR" -name "*.cache" -delete 2>/dev/null || true
    find "$APP_DIR" -name ".phpunit.cache" -type d -exec rm -rf {} + 2>/dev/null || true
    
    log "Cache nettoyé"
}

# Vérification de la restauration
verify_restoration() {
    log "Vérification de la restauration..."
    
    # Vérification des fichiers essentiels
    if [ ! -f "$APP_DIR/public/index.php" ]; then
        error "Le fichier index.php n'a pas été restauré"
    fi
    
    if [ ! -f "$APP_DIR/src/config/config.php" ]; then
        error "Le fichier de configuration n'a pas été restauré"
    fi
    
    # Test de connexion à la base de données
    if [ -f "$APP_DIR/src/config/config.php" ]; then
        DB_HOST=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['host'];")
        DB_NAME=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['name'];")
        DB_USER=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['user'];")
        DB_PASS=$(php -r "include '$APP_DIR/src/config/config.php'; echo \$config['database']['pass'];")
        
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
            log "Connexion à la base de données vérifiée"
        else
            warning "Impossible de vérifier la connexion à la base de données"
        fi
    fi
    
    log "Restauration vérifiée avec succès"
}

# Nettoyage des fichiers temporaires
cleanup() {
    log "Nettoyage des fichiers temporaires..."
    rm -rf "$TEMP_DIR"
}

# Fonction principale
main() {
    echo -e "${BLUE}"
    echo "=========================================="
    echo "  Restauration de Gendoc"
    echo "=========================================="
    echo -e "${NC}"
    
    check_prerequisites
    select_backup_file "$1"
    create_temp_dir
    extract_backup
    backup_current_state
    restore_files
    restore_database
    update_permissions
    clear_cache
    verify_restoration
    cleanup
    
    log "Restauration terminée avec succès !"
    echo
    echo -e "${GREEN}L'application a été restaurée. Vous pouvez maintenant y accéder.${NC}"
}

# Gestion des erreurs
trap 'error "Erreur lors de la restauration"; cleanup' ERR

# Exécution du script
main "$@" 