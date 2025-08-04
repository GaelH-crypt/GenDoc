<?php

namespace Gendoc\Controllers;

use Gendoc\Core\Request;
use Gendoc\Core\Response;
use Gendoc\Services\SessionService;
use Gendoc\Services\DatabaseService;
use Gendoc\Services\LoggerService;
use Gendoc\Services\LdapService;

/**
 * Contrôleur d'authentification
 */
class AuthController
{
    private SessionService $session;
    private DatabaseService $database;
    private LoggerService $logger;
    private LdapService $ldap;

    public function __construct()
    {
        $this->session = new SessionService();
        $this->database = DatabaseService::getInstance();
        $this->logger = new LoggerService(__DIR__ . '/../../storage/logs');
        $this->ldap = new LdapService();
    }

    /**
     * Affiche la page de connexion
     */
    public function login(Request $request): Response
    {
        // Si l'utilisateur est déjà connecté, redirection vers le dashboard
        if ($this->session->isAuthenticated()) {
            return Response::redirect('/dashboard');
        }

        // Vérifier si c'est le premier lancement
        if ($this->isFirstLaunch()) {
            return Response::redirect('/install');
        }

        $error = $this->session->getFlash('error');
        $success = $this->session->getFlash('success');

        return $this->renderView('auth/login.php', [
            'error' => $error,
            'success' => $success,
            'csrf_token' => $this->session->setCsrfToken()
        ]);
    }

    /**
     * Traite l'authentification
     */
    public function authenticate(Request $request): Response
    {
        // Vérification du token CSRF
        if (!$this->session->verifyCsrfToken($request->getPost('csrf_token'))) {
            $this->session->setFlash('error', 'Token de sécurité invalide');
            return Response::redirect('/login');
        }

        $username = $request->sanitize($request->getPost('username'));
        $password = $request->getPost('password');
        $authType = $request->getPost('auth_type', 'local');

        // Validation des données
        if (empty($username) || empty($password)) {
            $this->session->setFlash('error', 'Nom d\'utilisateur et mot de passe requis');
            return Response::redirect('/login');
        }

        // Vérification du blocage de compte
        if ($this->session->isAccountLocked($username)) {
            $this->session->setFlash('error', 'Compte temporairement bloqué. Réessayez dans 15 minutes.');
            return Response::redirect('/login');
        }

        try {
            $user = null;

            if ($authType === 'ldap') {
                $user = $this->authenticateLdap($username, $password);
            } else {
                $user = $this->authenticateLocal($username, $password);
            }

            if ($user) {
                // Authentification réussie
                $this->session->authenticate($user['id'], $user);
                $this->session->resetFailedLoginCount($username);
                
                $this->logger->security("Connexion réussie pour l'utilisateur: {$user['username']}", [
                    'user_id' => $user['id'],
                    'auth_type' => $authType,
                    'ip' => $request->getClientIp()
                ]);

                return Response::redirect('/dashboard');
            } else {
                // Authentification échouée
                $this->session->recordFailedLogin($username, $request->getClientIp());
                $this->session->setFlash('error', 'Nom d\'utilisateur ou mot de passe incorrect');
                
                $this->logger->security("Tentative de connexion échouée pour: $username", [
                    'auth_type' => $authType,
                    'ip' => $request->getClientIp()
                ]);

                return Response::redirect('/login');
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'authentification: ' . $e->getMessage());
            $this->session->setFlash('error', 'Erreur lors de l\'authentification. Veuillez réessayer.');
            return Response::redirect('/login');
        }
    }

    /**
     * Authentification LDAP
     */
    private function authenticateLdap(string $username, string $password): ?array
    {
        try {
            $ldapUser = $this->ldap->authenticate($username, $password);
            
            if ($ldapUser) {
                // Vérifier si l'utilisateur existe en base
                $user = $this->database->queryOne(
                    "SELECT * FROM users WHERE ldap_uid = ? AND actif = 1",
                    [$ldapUser['uid']]
                );

                if ($user) {
                    // Mettre à jour les informations utilisateur
                    $this->database->update('users', [
                        'nom' => $ldapUser['sn'],
                        'prenom' => $ldapUser['givenName'],
                        'email' => $ldapUser['mail'],
                        'date_modification' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$user['id']]);

                    return array_merge($user, $ldapUser);
                } else {
                    // Créer un nouvel utilisateur LDAP
                    $userId = $this->database->insert('users', [
                        'ldap_uid' => $ldapUser['uid'],
                        'username' => $username,
                        'nom' => $ldapUser['sn'],
                        'prenom' => $ldapUser['givenName'],
                        'email' => $ldapUser['mail'],
                        'role' => 'user',
                        'type' => 'ldap',
                        'actif' => 1
                    ]);

                    return array_merge($ldapUser, ['id' => $userId]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur LDAP: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Authentification locale
     */
    private function authenticateLocal(string $username, string $password): ?array
    {
        $user = $this->database->queryOne(
            "SELECT * FROM users WHERE username = ? AND type = 'local' AND actif = 1",
            [$username]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return null;
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if ($user) {
            $this->logger->security("Déconnexion de l'utilisateur: {$user['username']}", [
                'user_id' => $user['id'],
                'ip' => $request->getClientIp()
            ]);
        }

        $this->session->logout();
        $this->session->setFlash('success', 'Vous avez été déconnecté avec succès');
        
        return Response::redirect('/login');
    }

    /**
     * Wizard d'installation (premier lancement)
     */
    public function installWizard(Request $request): Response
    {
        // Vérifier si l'application est déjà installée
        if (!$this->isFirstLaunch()) {
            return Response::redirect('/login');
        }

        $step = $request->getQuery('step', 1);
        $error = $this->session->getFlash('error');
        $success = $this->session->getFlash('success');

        switch ($step) {
            case 1:
                return $this->renderView('install/welcome.php', [
                    'error' => $error,
                    'success' => $success
                ]);

            case 2:
                return $this->renderView('install/database.php', [
                    'error' => $error,
                    'success' => $success,
                    'csrf_token' => $this->session->setCsrfToken()
                ]);

            case 3:
                return $this->renderView('install/admin.php', [
                    'error' => $error,
                    'success' => $success,
                    'csrf_token' => $this->session->setCsrfToken()
                ]);

            case 4:
                return $this->renderView('install/finish.php', [
                    'error' => $error,
                    'success' => $success
                ]);

            default:
                return Response::redirect('/install?step=1');
        }
    }

    /**
     * Traite l'étape de configuration de la base de données
     */
    public function configureDatabase(Request $request): Response
    {
        if (!$this->session->verifyCsrfToken($request->getPost('csrf_token'))) {
            $this->session->setFlash('error', 'Token de sécurité invalide');
            return Response::redirect('/install?step=2');
        }

        $host = $request->sanitize($request->getPost('db_host'));
        $name = $request->sanitize($request->getPost('db_name'));
        $user = $request->sanitize($request->getPost('db_user'));
        $pass = $request->getPost('db_pass');

        // Test de connexion
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Sauvegarder la configuration
            $this->saveDatabaseConfig($host, $name, $user, $pass);
            
            $this->session->setFlash('success', 'Configuration de la base de données réussie');
            return Response::redirect('/install?step=3');
        } catch (\Exception $e) {
            $this->session->setFlash('error', 'Erreur de connexion à la base de données: ' . $e->getMessage());
            return Response::redirect('/install?step=2');
        }
    }

    /**
     * Traite l'étape de création du compte administrateur
     */
    public function createAdmin(Request $request): Response
    {
        if (!$this->session->verifyCsrfToken($request->getPost('csrf_token'))) {
            $this->session->setFlash('error', 'Token de sécurité invalide');
            return Response::redirect('/install?step=3');
        }

        $username = $request->sanitize($request->getPost('admin_username'));
        $password = $request->getPost('admin_password');
        $confirmPassword = $request->getPost('admin_confirm_password');
        $nom = $request->sanitize($request->getPost('admin_nom'));
        $prenom = $request->sanitize($request->getPost('admin_prenom'));
        $email = $request->sanitize($request->getPost('admin_email'));

        // Validation
        if (empty($username) || empty($password) || empty($nom) || empty($prenom) || empty($email)) {
            $this->session->setFlash('error', 'Tous les champs sont requis');
            return Response::redirect('/install?step=3');
        }

        if ($password !== $confirmPassword) {
            $this->session->setFlash('error', 'Les mots de passe ne correspondent pas');
            return Response::redirect('/install?step=3');
        }

        if (strlen($password) < 8) {
            $this->session->setFlash('error', 'Le mot de passe doit contenir au moins 8 caractères');
            return Response::redirect('/install?step=3');
        }

        try {
            // Créer le compte administrateur
            $userId = $this->database->insert('users', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'role' => 'admin',
                'type' => 'local',
                'actif' => 1
            ]);

            // Marquer l'installation comme terminée
            $this->markInstallationComplete();

            $this->session->setFlash('success', 'Installation terminée avec succès');
            return Response::redirect('/install?step=4');
        } catch (\Exception $e) {
            $this->session->setFlash('error', 'Erreur lors de la création du compte: ' . $e->getMessage());
            return Response::redirect('/install?step=3');
        }
    }

    /**
     * Vérifie si c'est le premier lancement
     */
    private function isFirstLaunch(): bool
    {
        return !file_exists(__DIR__ . '/../../storage/installed');
    }

    /**
     * Sauvegarde la configuration de la base de données
     */
    private function saveDatabaseConfig(string $host, string $name, string $user, string $pass): void
    {
        $config = [
            'database' => [
                'host' => $host,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
                'charset' => 'utf8mb4'
            ]
        ];

        file_put_contents(__DIR__ . '/../../storage/database_config.json', json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Marque l'installation comme terminée
     */
    private function markInstallationComplete(): void
    {
        file_put_contents(__DIR__ . '/../../storage/installed', date('Y-m-d H:i:s'));
    }

    /**
     * Rend une vue
     */
    private function renderView(string $view, array $data = []): Response
    {
        extract($data);
        
        ob_start();
        include __DIR__ . '/../../views/' . $view;
        $content = ob_get_clean();
        
        return new Response($content);
    }
} 