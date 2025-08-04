#!/bin/bash

# Script d'installation automatisé pour Gendoc
# Application de génération de documents personnalisés

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables globales
APP_NAME="Gendoc"
APP_VERSION="1.0.0"
INSTALL_DIR="/var/www/html/gendoc"
LOG_FILE="/var/log/gendoc_install.log"
WEB_SERVER="apache2"
DB_NAME="gendoc_db"
DB_USER="gendoc_user"
DB_PASS=$(openssl rand -base64 32)

# Fonction de logging
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERREUR] $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[ATTENTION] $1${NC}" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}" | tee -a "$LOG_FILE"
}

# Vérification des privilèges root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        error "Ce script doit être exécuté en tant que root"
    fi
}

# Détection de l'environnement
detect_environment() {
    info "Détection de l'environnement..."
    
    if [ -f /.dockerenv ]; then
        ENVIRONMENT="docker"
        info "Environnement Docker détecté"
    elif [ -f /proc/version ] && grep -q "Microsoft\|WSL" /proc/version; then
        ENVIRONMENT="wsl"
        info "Environnement WSL détecté"
    else
        ENVIRONMENT="native"
        info "Environnement natif détecté"
    fi
}

# Mise à jour du système
update_system() {
    log "Mise à jour du système..."
    apt update && apt upgrade -y
}

# Installation des dépendances système
install_system_dependencies() {
    log "Installation des dépendances système..."
    
    apt install -y \
        apache2 \
        php8.1 \
        php8.1-cli \
        php8.1-common \
        php8.1-mysql \
        php8.1-ldap \
        php8.1-mbstring \
        php8.1-xml \
        php8.1-zip \
        php8.1-gd \
        php8.1-curl \
        php8.1-json \
        php8.1-opcache \
        php8.1-intl \
        php8.1-bcmath \
        mysql-server \
        mysql-client \
        unzip \
        curl \
        git \
        composer \
        libapache2-mod-php8.1
}

# Configuration d'Apache
configure_apache() {
    log "Configuration d'Apache..."
    
    # Activation des modules nécessaires
    a2enmod rewrite
    a2enmod ssl
    a2enmod headers
    
    # Configuration du virtual host
    cat > /etc/apache2/sites-available/gendoc.conf << EOF
<VirtualHost *:80>
    ServerName gendoc.local
    DocumentRoot $INSTALL_DIR/public
    
    <Directory $INSTALL_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/gendoc_error.log
    CustomLog \${APACHE_LOG_DIR}/gendoc_access.log combined
</VirtualHost>
EOF

    a2ensite gendoc
    a2dissite 000-default
    
    systemctl restart apache2
}

# Configuration de MySQL
configure_mysql() {
    log "Configuration de MySQL..."
    
    # Démarrage et sécurisation de MySQL
    systemctl start mysql
    systemctl enable mysql
    
    # Création de la base de données et de l'utilisateur
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
}

# Configuration de PHP
configure_php() {
    log "Configuration de PHP..."
    
    # Configuration PHP pour l'application
    cat > /etc/php/8.1/apache2/conf.d/99-gendoc.ini << EOF
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 512M
date.timezone = Europe/Paris
session.gc_maxlifetime = 3600
EOF

    systemctl restart apache2
}

# Installation de Composer et dépendances PHP
install_composer_dependencies() {
    log "Installation des dépendances Composer..."
    
    cd "$INSTALL_DIR"
    
    # Création du composer.json
    cat > composer.json << EOF
{
    "name": "gendoc/app",
    "description": "Application de génération de documents personnalisés",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "phpoffice/phpword": "^1.0",
        "dompdf/dompdf": "^2.0",
        "vlucas/phpdotenv": "^5.5",
        "phpunit/phpunit": "^10.0",
        "monolog/monolog": "^3.0",
        "symfony/ldap": "^6.0",
        "symfony/security": "^6.0"
    },
    "autoload": {
        "psr-4": {
            "Gendoc\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Gendoc\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "post-install-cmd": [
            "chmod -R 755 storage/",
            "chmod -R 755 public/uploads/"
        ]
    }
}
EOF

    composer install --no-dev --optimize-autoloader
}

# Configuration des permissions
configure_permissions() {
    log "Configuration des permissions..."
    
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 777 "$INSTALL_DIR/storage"
    chmod -R 777 "$INSTALL_DIR/public/uploads"
}

# Création du fichier .htaccess
create_htaccess() {
    log "Création du fichier .htaccess..."
    
    cat > "$INSTALL_DIR/public/.htaccess" << EOF
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Sécurité
<Files "*.env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>
EOF
}

# Création du fichier de configuration
create_config() {
    log "Création du fichier de configuration..."
    
    cat > "$INSTALL_DIR/src/config/config.php" << EOF
<?php
return [
    'database' => [
        'host' => 'localhost',
        'name' => '$DB_NAME',
        'user' => '$DB_USER',
        'pass' => '$DB_PASS',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => '$APP_NAME',
        'version' => '$APP_VERSION',
        'debug' => false,
        'timezone' => 'Europe/Paris'
    ],
    'security' => [
        'session_timeout' => 3600,
        'password_min_length' => 8,
        'max_login_attempts' => 5
    ],
    'storage' => [
        'templates' => __DIR__ . '/../../storage/templates',
        'documents' => __DIR__ . '/../../storage/documents',
        'logs' => __DIR__ . '/../../storage/logs'
    ]
];
EOF
}

# Création de la base de données
create_database_schema() {
    log "Création du schéma de base de données..."
    
    cat > "$INSTALL_DIR/install/schema.sql" << EOF
-- Schéma de base de données pour Gendoc

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ldap_uid VARCHAR(255) UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    type ENUM('ldap', 'local') DEFAULT 'local',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS modeles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    fichier_path VARCHAR(500) NOT NULL,
    champs_json JSON,
    version INT DEFAULT 1,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    modele_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    date_gen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nom_fichier VARCHAR(500),
    statut ENUM('draft', 'generated', 'archived') DEFAULT 'draft',
    contenu_json JSON,
    path_fichier VARCHAR(500),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (modele_id) REFERENCES modeles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Index pour les performances
CREATE INDEX idx_users_ldap_uid ON users(ldap_uid);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_documents_user_id ON documents(user_id);
CREATE INDEX idx_documents_modele_id ON documents(modele_id);
CREATE INDEX idx_logs_user_id ON logs(user_id);
CREATE INDEX idx_logs_date_action ON logs(date_action);

-- Insertion d'un utilisateur administrateur par défaut
INSERT INTO users (username, nom, prenom, email, password_hash, role, type) 
VALUES ('admin', 'Administrateur', 'Gendoc', 'admin@gendoc.local', 
        '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'local')
ON DUPLICATE KEY UPDATE id=id;
EOF

    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/install/schema.sql"
}

# Création du fichier index.php principal
create_index_php() {
    log "Création du fichier index.php principal..."
    
    cat > "$INSTALL_DIR/public/index.php" << 'EOF'
<?php
/**
 * Gendoc - Application de génération de documents personnalisés
 * Point d'entrée principal de l'application
 */

// Démarrage de la session
session_start();

// Chargement de l'autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Chargement de la configuration
$config = require_once __DIR__ . '/../src/config/config.php';

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');

// Définition du fuseau horaire
date_default_timezone_set($config['app']['timezone']);

// Initialisation de l'application
try {
    $app = new \Gendoc\Core\Application($config);
    $app->run();
} catch (Exception $e) {
    if ($config['app']['debug']) {
        throw $e;
    } else {
        http_response_code(500);
        echo "Une erreur est survenue. Veuillez contacter l'administrateur.";
    }
}
EOF
}

# Création du fichier de test
create_test_file() {
    log "Création d'un fichier de test..."
    
    cat > "$INSTALL_DIR/tests/unit/ExampleTest.php" << 'EOF'
<?php

namespace Gendoc\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testBasicFunctionality()
    {
        $this->assertTrue(true);
    }
}
EOF
}

# Nettoyage et finalisation
finalize_installation() {
    log "Finalisation de l'installation..."
    
    # Création du fichier de version
    echo "$APP_VERSION" > "$INSTALL_DIR/VERSION"
    
    # Création du fichier README
    cat > "$INSTALL_DIR/README.md" << EOF
# Gendoc - Application de génération de documents personnalisés

## Version
$APP_VERSION

## Installation
L'application a été installée automatiquement via le script install.sh

## Accès
- URL: http://localhost/gendoc
- Utilisateur admin par défaut: admin
- Mot de passe: password

## Configuration
Toute la configuration se fait via l'interface web d'administration.

## Support
Pour toute question ou problème, consultez la documentation dans le dossier docs/.
EOF

    log "Installation terminée avec succès !"
    info "URL d'accès: http://localhost/gendoc"
    info "Utilisateur admin: admin"
    info "Mot de passe: password"
    info "Log d'installation: $LOG_FILE"
}

# Fonction principale
main() {
    echo -e "${BLUE}"
    echo "=========================================="
    echo "  Installation de $APP_NAME v$APP_VERSION"
    echo "=========================================="
    echo -e "${NC}"
    
    check_root
    detect_environment
    update_system
    install_system_dependencies
    configure_apache
    configure_mysql
    configure_php
    install_composer_dependencies
    configure_permissions
    create_htaccess
    create_config
    create_database_schema
    create_index_php
    create_test_file
    finalize_installation
}

# Exécution du script
main "$@" 