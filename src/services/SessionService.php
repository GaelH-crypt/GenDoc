<?php

namespace Gendoc\Services;

use Gendoc\Models\User;

/**
 * Service de gestion des sessions utilisateur
 */
class SessionService
{
    private array $config;
    private DatabaseService $database;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->database = DatabaseService::getInstance();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Authentifie un utilisateur
     */
    public function authenticate(int $userId, array $userData = []): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_data'] = $userData;
        $_SESSION['auth_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Régénération de l'ID de session pour la sécurité
        session_regenerate_id(true);
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        // Détruire toutes les données de session
        $_SESSION = [];
        
        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Détruire la session
        session_destroy();
    }

    /**
     * Obtient l'ID de l'utilisateur connecté
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Obtient les données de l'utilisateur connecté
     */
    public function getUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        // Si les données utilisateur ne sont pas en session, les récupérer depuis la DB
        if (!isset($_SESSION['user_data']) || empty($_SESSION['user_data'])) {
            $user = $this->database->queryOne(
                "SELECT * FROM users WHERE id = ? AND actif = 1",
                [$this->getUserId()]
            );
            
            if ($user) {
                $_SESSION['user_data'] = $user;
            } else {
                $this->logout();
                return null;
            }
        }

        return $_SESSION['user_data'];
    }

    /**
     * Met à jour les données utilisateur en session
     */
    public function updateUserData(array $userData): void
    {
        if ($this->isAuthenticated()) {
            $_SESSION['user_data'] = array_merge($_SESSION['user_data'] ?? [], $userData);
        }
    }

    /**
     * Vérifie si la session a expiré
     */
    public function isSessionExpired(): bool
    {
        if (!$this->isAuthenticated()) {
            return true;
        }

        $timeout = $this->config['session_timeout'] ?? 3600; // 1 heure par défaut
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        
        return (time() - $lastActivity) > $timeout;
    }

    /**
     * Met à jour l'activité de la session
     */
    public function updateActivity(): void
    {
        if ($this->isAuthenticated()) {
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Vérifie et nettoie les sessions expirées
     */
    public function cleanupExpiredSessions(): void
    {
        if ($this->isSessionExpired()) {
            $this->logout();
        }
    }

    /**
     * Définit une variable de session
     */
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Obtient une variable de session
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Supprime une variable de session
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Vérifie si une variable de session existe
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Obtient toutes les variables de session
     */
    public function all(): array
    {
        return $_SESSION;
    }

    /**
     * Efface toutes les variables de session
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Définit un message flash (temporaire)
     */
    public function setFlash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Obtient un message flash et le supprime
     */
    public function getFlash(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    /**
     * Vérifie si un message flash existe
     */
    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['flash'][$key]);
    }

    /**
     * Obtient tous les messages flash
     */
    public function getAllFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Définit un token CSRF
     */
    public function setCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Vérifie un token CSRF
     */
    public function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Obtient le token CSRF actuel
     */
    public function getCsrfToken(): ?string
    {
        return $_SESSION['csrf_token'] ?? null;
    }

    /**
     * Vérifie les permissions de l'utilisateur
     */
    public function hasPermission(string $permission): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        // Vérification du rôle administrateur
        if ($user['role'] === 'admin') {
            return true;
        }

        // Vérification des permissions spécifiques
        switch ($permission) {
            case 'create_document':
                return true; // Tous les utilisateurs peuvent créer des documents
            case 'manage_templates':
                return $user['role'] === 'admin';
            case 'manage_users':
                return $user['role'] === 'admin';
            case 'view_logs':
                return $user['role'] === 'admin';
            default:
                return false;
        }
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getUser();
        return $user && $user['role'] === $role;
    }

    /**
     * Obtient le rôle de l'utilisateur connecté
     */
    public function getUserRole(): ?string
    {
        $user = $this->getUser();
        return $user['role'] ?? null;
    }

    /**
     * Enregistre une tentative de connexion échouée
     */
    public function recordFailedLogin(string $username, string $ip): void
    {
        $key = "failed_login_$username";
        $attempts = $this->get($key, 0);
        $this->set($key, $attempts + 1);
        
        // Enregistrer dans la base de données
        $this->database->insert('logs', [
            'user_id' => null,
            'action' => 'failed_login',
            'details' => json_encode([
                'username' => $username,
                'ip_address' => $ip,
                'attempts' => $attempts + 1
            ]),
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Vérifie si un compte est temporairement bloqué
     */
    public function isAccountLocked(string $username): bool
    {
        $maxAttempts = $this->config['max_login_attempts'] ?? 5;
        $lockoutTime = 900; // 15 minutes
        
        $key = "failed_login_$username";
        $attempts = $this->get($key, 0);
        $lastAttempt = $this->get("last_failed_login_$username", 0);
        
        if ($attempts >= $maxAttempts && (time() - $lastAttempt) < $lockoutTime) {
            return true;
        }
        
        // Réinitialiser le compteur si le temps de blocage est écoulé
        if ((time() - $lastAttempt) >= $lockoutTime) {
            $this->remove($key);
            $this->remove("last_failed_login_$username");
        }
        
        return false;
    }

    /**
     * Réinitialise le compteur de tentatives échouées
     */
    public function resetFailedLoginCount(string $username): void
    {
        $this->remove("failed_login_$username");
        $this->remove("last_failed_login_$username");
    }
} 