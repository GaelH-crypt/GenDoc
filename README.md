# Gendoc - Application de Génération de Documents Personnalisés

[![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-PHPUnit-orange.svg)](tests/)

Gendoc est une application web moderne et sécurisée permettant la génération, l'archivage et la gestion de documents personnalisés à partir de modèles Word. L'application supporte l'authentification LDAP et locale, offre une interface d'administration complète et garantit une qualité logicielle élevée via des tests unitaires.

## 🚀 Fonctionnalités principales

### 🔐 Authentification et sécurité
- **Authentification LDAP** : Intégration complète avec Active Directory et annuaires LDAP
- **Comptes locaux** : Gestion d'utilisateurs internes pour maintenance et secours
- **Sécurité renforcée** : Protection CSRF, validation des sessions, chiffrement des données
- **Gestion des rôles** : Utilisateur et Administrateur avec permissions granulaires

### 📄 Gestion des modèles
- **Import Word** : Support des formats .doc et .docx
- **Détection automatique** : Reconnaissance des champs personnalisables via balises `{{Champ}}`
- **Configuration avancée** : Types de champs, validation, options de saisie
- **Versioning** : Historique, restauration et gestion des versions
- **Préservation** : Conservation intégrale de la mise en page Word

### 📋 Génération de documents
- **Formulaires dynamiques** : Génération automatique selon le modèle choisi
- **Validation en temps réel** : Contrôle des champs selon leur configuration
- **Multi-format** : Export Word (.docx) et PDF avec conversion automatique
- **Prévisualisation** : Aperçu en ligne avant export
- **Archivage** : Stockage sécurisé dans l'espace personnel

### 👤 Espace personnel
- **Tableau de bord** : Vue d'ensemble des documents générés
- **Recherche avancée** : Filtres par date, type, mots-clés
- **Actions multiples** : Prévisualiser, télécharger, dupliquer, supprimer
- **Historique complet** : Traçabilité de toutes les actions

### ⚙️ Administration
- **Interface complète** : Gestion des utilisateurs, modèles et documents
- **Statistiques** : Tableaux de bord et rapports d'utilisation
- **Logs système** : Consultation et export des journaux d'activité
- **Paramétrage centralisé** : Configuration via interface web

## 📋 Prérequis

- **PHP** : 8.1 ou supérieur
- **MySQL** : 8.0+ ou MariaDB 10.5+
- **Apache** : 2.4+ ou Nginx 1.18+
- **Composer** : 2.0+
- **Extensions PHP** : mysql, ldap, mbstring, xml, zip, gd, curl, json, opcache, intl, bcmath

## 🛠️ Installation rapide

### Installation automatisée (recommandée)

```bash
# 1. Cloner le repository
git clone https://github.com/votre-organisation/gendoc.git
cd gendoc

# 2. Rendre le script exécutable
chmod +x install.sh

# 3. Exécuter l'installation (nécessite les droits root)
sudo ./install.sh
```

### Installation manuelle

Consultez le [Guide d'installation complet](docs/INSTALLATION.md) pour les instructions détaillées.

## 🚀 Démarrage rapide

1. **Accéder à l'application** :
   ```
   http://votre-serveur/gendoc
   ```

2. **Configuration initiale** :
   - L'application vous redirige automatiquement vers le wizard de configuration
   - Configurez la base de données
   - Créez le compte administrateur principal

3. **Première connexion** :
   - Utilisateur : `admin`
   - Mot de passe : `password` (à changer immédiatement)

## 📁 Structure du projet

```
gendoc/
├── src/                    # Code source de l'application
│   ├── Controllers/        # Contrôleurs MVC
│   ├── Services/          # Services métier
│   ├── Models/            # Modèles de données
│   ├── Core/              # Classes de base
│   └── config/            # Configuration
├── views/                 # Templates et vues
├── public/                # Fichiers publics (CSS, JS, images)
├── storage/               # Stockage des fichiers
│   ├── templates/         # Modèles Word
│   ├── documents/         # Documents générés
│   └── logs/              # Journaux d'activité
├── tests/                 # Tests unitaires et d'intégration
├── docs/                  # Documentation
├── install/               # Scripts d'installation
└── vendor/                # Dépendances Composer
```

## 🧪 Tests

### Exécution des tests
```bash
# Tests unitaires
composer test

# Tests d'intégration
composer test:integration

# Couverture de code
composer test:coverage
```

### Configuration des tests
- Base de données de test : `gendoc_test_db`
- Configuration : `phpunit.xml`
- Rapports : `tests/coverage/`

## 🔧 Configuration

### Base de données
```php
// src/config/config.php
'database' => [
    'host' => 'localhost',
    'name' => 'gendoc_db',
    'user' => 'gendoc_user',
    'pass' => 'votre_mot_de_passe',
    'charset' => 'utf8mb4'
]
```

### LDAP (optionnel)
```json
// storage/ldap_config.json
{
    "host": "ldap.votre-domaine.com",
    "port": 389,
    "search_base": "dc=votre-domaine,dc=com",
    "search_filter": "(uid=%s)",
    "bind_dn": "cn=service,dc=votre-domaine,dc=com",
    "bind_password": "mot_de_passe_service"
}
```

## 🔒 Sécurité

### Recommandations
- **HTTPS obligatoire** en production
- **Firewall** configuré correctement
- **Permissions** des fichiers sécurisées
- **Mots de passe** forts et changés régulièrement
- **Sauvegardes** automatiques configurées

### Audit de sécurité
```bash
# Vérification des permissions
sudo find /var/www/html/gendoc -type f -exec chmod 644 {} \;
sudo find /var/www/html/gendoc -type d -exec chmod 755 {} \;

# Sécurisation des fichiers sensibles
sudo chmod 600 /var/www/html/gendoc/storage/*.json
sudo chmod 600 /var/www/html/gendoc/src/config/config.php
```

## 📊 Monitoring et maintenance

### Logs
- **Application** : `storage/logs/`
- **Apache** : `/var/log/apache2/gendoc_*.log`
- **MySQL** : `/var/log/mysql/error.log`

### Sauvegarde
```bash
# Script de sauvegarde automatique
0 2 * * * /var/www/html/gendoc/scripts/backup.sh
```

### Mise à jour
```bash
cd /var/www/html/gendoc
git pull origin main
composer install --no-dev --optimize-autoloader
php install/update.php
```

## 🤝 Contribution

1. **Fork** le projet
2. **Créez** une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. **Commitez** vos changements (`git commit -m 'Add some AmazingFeature'`)
4. **Poussez** vers la branche (`git push origin feature/AmazingFeature`)
5. **Ouvrez** une Pull Request

### Standards de code
- **PSR-12** : Standards de codage PHP
- **Tests** : Couverture minimale de 80%
- **Documentation** : PHPDoc pour toutes les méthodes publiques

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🆘 Support

### Documentation
- [Guide d'installation](docs/INSTALLATION.md)
- [Guide utilisateur](docs/USER_GUIDE.md)
- [Guide administrateur](docs/ADMIN_GUIDE.md)
- [API Reference](docs/API.md)

### Problèmes courants
- [FAQ](docs/FAQ.md)
- [Dépannage](docs/TROUBLESHOOTING.md)

### Contact
- **Issues** : [GitHub Issues](https://github.com/votre-organisation/gendoc/issues)
- **Documentation** : [Wiki](https://github.com/votre-organisation/gendoc/wiki)

## 🙏 Remerciements

- **PHPWord** : Génération de documents Word
- **DOMPDF** : Conversion PDF
- **Bootstrap** : Interface utilisateur
- **Chart.js** : Graphiques et statistiques
- **Font Awesome** : Icônes

---

**Gendoc** - Simplifiez la génération de vos documents personnalisés ! 📄✨ 
