# Guide d'installation de Gendoc

## Prérequis

### Système d'exploitation
- Linux (Ubuntu 20.04+, Debian 11+, CentOS 8+)
- Windows Server 2016+ (avec WSL2 recommandé)
- macOS 10.15+ (pour le développement)

### Logiciels requis
- PHP 8.1 ou supérieur
- MySQL 8.0 ou MariaDB 10.5+
- Apache 2.4+ ou Nginx 1.18+
- Composer 2.0+
- Git

### Extensions PHP requises
- php-mysql
- php-ldap
- php-mbstring
- php-xml
- php-zip
- php-gd
- php-curl
- php-json
- php-opcache
- php-intl
- php-bcmath

## Installation automatisée

### 1. Téléchargement
```bash
# Cloner le repository
git clone https://github.com/votre-organisation/gendoc.git
cd gendoc

# Ou télécharger l'archive
wget https://github.com/votre-organisation/gendoc/archive/refs/tags/v1.0.0.zip
unzip v1.0.0.zip
cd gendoc-1.0.0
```

### 2. Exécution du script d'installation
```bash
# Rendre le script exécutable
chmod +x install.sh

# Exécuter l'installation (nécessite les droits root)
sudo ./install.sh
```

Le script d'installation va :
- Mettre à jour le système
- Installer toutes les dépendances
- Configurer Apache et MySQL
- Créer la base de données
- Installer les dépendances Composer
- Configurer les permissions
- Créer un utilisateur administrateur par défaut

### 3. Configuration initiale
Après l'installation, accédez à l'application :
```
http://votre-serveur/gendoc
```

L'application vous redirigera automatiquement vers le wizard de configuration si c'est le premier lancement.

## Installation manuelle

### 1. Installation des dépendances système

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install apache2 php8.1 php8.1-cli php8.1-common php8.1-mysql \
    php8.1-ldap php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd \
    php8.1-curl php8.1-json php8.1-opcache php8.1-intl php8.1-bcmath \
    mysql-server mysql-client unzip curl git composer
```

#### CentOS/RHEL
```bash
sudo yum install epel-release
sudo yum install httpd php php-cli php-common php-mysql php-ldap \
    php-mbstring php-xml php-zip php-gd php-curl php-json \
    php-opcache php-intl php-bcmath mysql-server mysql-client \
    unzip curl git composer
```

### 2. Configuration d'Apache
```bash
# Activer les modules nécessaires
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Créer le virtual host
sudo nano /etc/apache2/sites-available/gendoc.conf
```

Contenu du fichier de configuration :
```apache
<VirtualHost *:80>
    ServerName gendoc.local
    DocumentRoot /var/www/html/gendoc/public
    
    <Directory /var/www/html/gendoc/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/gendoc_error.log
    CustomLog ${APACHE_LOG_DIR}/gendoc_access.log combined
</VirtualHost>
```

```bash
# Activer le site
sudo a2ensite gendoc
sudo a2dissite 000-default
sudo systemctl restart apache2
```

### 3. Configuration de MySQL
```bash
# Sécuriser MySQL
sudo mysql_secure_installation

# Créer la base de données et l'utilisateur
sudo mysql -u root -p
```

```sql
CREATE DATABASE gendoc_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gendoc_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe_securise';
GRANT ALL PRIVILEGES ON gendoc_db.* TO 'gendoc_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Installation des dépendances PHP
```bash
cd /var/www/html/gendoc
composer install --no-dev --optimize-autoloader
```

### 5. Configuration des permissions
```bash
sudo chown -R www-data:www-data /var/www/html/gendoc
sudo chmod -R 755 /var/www/html/gendoc
sudo chmod -R 777 /var/www/html/gendoc/storage
sudo chmod -R 777 /var/www/html/gendoc/public/uploads
```

### 6. Création de la base de données
```bash
# Importer le schéma
mysql -u gendoc_user -p gendoc_db < install/schema.sql
```

## Configuration

### 1. Configuration de la base de données
Le fichier de configuration se trouve dans `src/config/config.php`. Modifiez les paramètres selon votre environnement :

```php
'database' => [
    'host' => 'localhost',
    'name' => 'gendoc_db',
    'user' => 'gendoc_user',
    'pass' => 'votre_mot_de_passe',
    'charset' => 'utf8mb4'
]
```

### 2. Configuration LDAP (optionnel)
Si vous utilisez l'authentification LDAP, configurez les paramètres dans l'interface d'administration ou créez le fichier `storage/ldap_config.json` :

```json
{
    "host": "ldap.votre-domaine.com",
    "port": 389,
    "search_base": "dc=votre-domaine,dc=com",
    "search_filter": "(uid=%s)",
    "bind_dn": "cn=service,dc=votre-domaine,dc=com",
    "bind_password": "mot_de_passe_service"
}
```

### 3. Configuration des emails
Configurez les paramètres SMTP dans l'interface d'administration pour l'envoi automatique de documents.

## Sécurité

### 1. HTTPS
Il est fortement recommandé d'activer HTTPS :

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir un certificat SSL
sudo certbot --apache -d gendoc.votre-domaine.com
```

### 2. Firewall
```bash
# Configurer le firewall
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

### 3. Permissions des fichiers
```bash
# Sécuriser les fichiers sensibles
sudo chmod 600 /var/www/html/gendoc/storage/*.json
sudo chmod 600 /var/www/html/gendoc/src/config/config.php
```

## Tests

### Exécution des tests unitaires
```bash
cd /var/www/html/gendoc
composer test
```

### Tests d'intégration
```bash
# Créer une base de test
mysql -u root -p -e "CREATE DATABASE gendoc_test_db;"

# Exécuter les tests d'intégration
composer test:integration
```

## Maintenance

### Sauvegarde
```bash
# Script de sauvegarde automatique
sudo crontab -e
```

Ajouter la ligne :
```
0 2 * * * /var/www/html/gendoc/scripts/backup.sh
```

### Mise à jour
```bash
cd /var/www/html/gendoc
git pull origin main
composer install --no-dev --optimize-autoloader
php install/update.php
```

### Logs
Les logs se trouvent dans :
- `/var/www/html/gendoc/storage/logs/`
- `/var/log/apache2/gendoc_*.log`
- `/var/log/mysql/error.log`

## Dépannage

### Problèmes courants

#### Erreur de connexion à la base de données
- Vérifiez les paramètres de connexion dans `src/config/config.php`
- Assurez-vous que MySQL est démarré : `sudo systemctl status mysql`
- Vérifiez les permissions de l'utilisateur MySQL

#### Erreur 500 Apache
- Vérifiez les logs Apache : `sudo tail -f /var/log/apache2/gendoc_error.log`
- Vérifiez les permissions des fichiers
- Assurez-vous que PHP est correctement configuré

#### Problèmes de permissions
```bash
sudo chown -R www-data:www-data /var/www/html/gendoc
sudo chmod -R 755 /var/www/html/gendoc
sudo chmod -R 777 /var/www/html/gendoc/storage
```

#### Problèmes LDAP
- Vérifiez la connectivité : `ldapsearch -H ldap://votre-serveur-ldap -x`
- Vérifiez les paramètres de recherche dans la configuration
- Testez l'authentification avec un utilisateur connu

## Support

Pour obtenir de l'aide :
- Consultez la documentation complète dans le dossier `docs/`
- Vérifiez les logs d'erreur
- Contactez l'équipe de support technique

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails. 