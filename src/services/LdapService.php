<?php

namespace Gendoc\Services;

use Exception;

/**
 * Service de gestion LDAP
 */
class LdapService
{
    private array $config;
    private $connection;
    private bool $connected = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->loadConfig();
    }

    /**
     * Charge la configuration LDAP
     */
    private function loadConfig(): void
    {
        $configFile = __DIR__ . '/../../storage/ldap_config.json';
        
        if (file_exists($configFile)) {
            $this->config = array_merge($this->config, json_decode(file_get_contents($configFile), true));
        }
    }

    /**
     * Établit la connexion LDAP
     */
    private function connect(): bool
    {
        if ($this->connected && $this->connection) {
            return true;
        }

        if (empty($this->config['host']) || empty($this->config['port'])) {
            throw new Exception('Configuration LDAP incomplète');
        }

        $this->connection = ldap_connect($this->config['host'], $this->config['port']);
        
        if (!$this->connection) {
            throw new Exception('Impossible de se connecter au serveur LDAP');
        }

        // Configuration des options LDAP
        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, 10);

        // Connexion avec les identifiants de service
        if (!empty($this->config['bind_dn']) && !empty($this->config['bind_password'])) {
            $bind = ldap_bind($this->connection, $this->config['bind_dn'], $this->config['bind_password']);
            
            if (!$bind) {
                throw new Exception('Échec de l\'authentification LDAP: ' . ldap_error($this->connection));
            }
        }

        $this->connected = true;
        return true;
    }

    /**
     * Authentifie un utilisateur
     */
    public function authenticate(string $username, string $password): ?array
    {
        try {
            $this->connect();

            // Recherche de l'utilisateur
            $user = $this->findUser($username);
            
            if (!$user) {
                return null;
            }

            // Tentative d'authentification avec les identifiants fournis
            $bind = ldap_bind($this->connection, $user['dn'], $password);
            
            if (!$bind) {
                return null;
            }

            return $user;
        } catch (Exception $e) {
            error_log('Erreur LDAP: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Recherche un utilisateur par son nom d'utilisateur
     */
    public function findUser(string $username): ?array
    {
        try {
            $this->connect();

            $searchBase = $this->config['search_base'] ?? '';
            $searchFilter = sprintf($this->config['search_filter'] ?? '(uid=%s)', $username);
            
            $result = ldap_search($this->connection, $searchBase, $searchFilter);
            
            if (!$result) {
                return null;
            }

            $entries = ldap_get_entries($this->connection, $result);
            
            if ($entries['count'] === 0) {
                return null;
            }

            $entry = $entries[0];
            
            return [
                'dn' => $entry['dn'],
                'uid' => $entry['uid'][0] ?? $username,
                'username' => $entry['uid'][0] ?? $username,
                'sn' => $entry['sn'][0] ?? '',
                'givenName' => $entry['givenname'][0] ?? '',
                'mail' => $entry['mail'][0] ?? '',
                'cn' => $entry['cn'][0] ?? '',
                'displayName' => $entry['displayname'][0] ?? '',
                'memberOf' => $entry['memberof'] ?? []
            ];
        } catch (Exception $e) {
            error_log('Erreur lors de la recherche LDAP: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Recherche des utilisateurs avec filtres
     */
    public function searchUsers(array $filters = [], int $limit = 100): array
    {
        try {
            $this->connect();

            $searchBase = $this->config['search_base'] ?? '';
            $searchFilter = $this->buildSearchFilter($filters);
            
            $result = ldap_search($this->connection, $searchBase, $searchFilter);
            
            if (!$result) {
                return [];
            }

            $entries = ldap_get_entries($this->connection, $result);
            $users = [];

            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];
                
                $users[] = [
                    'dn' => $entry['dn'],
                    'uid' => $entry['uid'][0] ?? '',
                    'username' => $entry['uid'][0] ?? '',
                    'sn' => $entry['sn'][0] ?? '',
                    'givenName' => $entry['givenname'][0] ?? '',
                    'mail' => $entry['mail'][0] ?? '',
                    'cn' => $entry['cn'][0] ?? '',
                    'displayName' => $entry['displayname'][0] ?? '',
                    'memberOf' => $entry['memberof'] ?? []
                ];

                if (count($users) >= $limit) {
                    break;
                }
            }

            return $users;
        } catch (Exception $e) {
            error_log('Erreur lors de la recherche LDAP: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Construit un filtre de recherche LDAP
     */
    private function buildSearchFilter(array $filters): string
    {
        $conditions = [];

        if (!empty($filters['username'])) {
            $conditions[] = sprintf('(uid=%s)', ldap_escape($filters['username'], '', LDAP_ESCAPE_FILTER));
        }

        if (!empty($filters['email'])) {
            $conditions[] = sprintf('(mail=%s)', ldap_escape($filters['email'], '', LDAP_ESCAPE_FILTER));
        }

        if (!empty($filters['name'])) {
            $conditions[] = sprintf('(|(cn=*%s*)(sn=*%s*)(givenname=*%s*))', 
                ldap_escape($filters['name'], '', LDAP_ESCAPE_FILTER),
                ldap_escape($filters['name'], '', LDAP_ESCAPE_FILTER),
                ldap_escape($filters['name'], '', LDAP_ESCAPE_FILTER)
            );
        }

        if (!empty($filters['group'])) {
            $conditions[] = sprintf('(memberOf=%s)', ldap_escape($filters['group'], '', LDAP_ESCAPE_FILTER));
        }

        if (empty($conditions)) {
            return '(objectClass=person)';
        }

        return '(&(objectClass=person)' . implode('', $conditions) . ')';
    }

    /**
     * Teste la connexion LDAP
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            $this->connect();
            
            $result['success'] = true;
            $result['message'] = 'Connexion LDAP réussie';
            $result['details'] = [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'search_base' => $this->config['search_base'] ?? 'Non défini'
            ];
        } catch (Exception $e) {
            $result['message'] = 'Erreur de connexion LDAP: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Teste l'authentification d'un utilisateur
     */
    public function testAuthentication(string $username, string $password): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'user' => null
        ];

        try {
            $user = $this->authenticate($username, $password);
            
            if ($user) {
                $result['success'] = true;
                $result['message'] = 'Authentification réussie';
                $result['user'] = $user;
            } else {
                $result['message'] = 'Nom d\'utilisateur ou mot de passe incorrect';
            }
        } catch (Exception $e) {
            $result['message'] = 'Erreur lors de l\'authentification: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Synchronise les utilisateurs LDAP avec la base de données
     */
    public function syncUsers(DatabaseService $database): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];

        try {
            $ldapUsers = $this->searchUsers([], 1000);
            
            foreach ($ldapUsers as $ldapUser) {
                try {
                    // Vérifier si l'utilisateur existe déjà
                    $existingUser = $database->queryOne(
                        "SELECT * FROM users WHERE ldap_uid = ?",
                        [$ldapUser['uid']]
                    );

                    if ($existingUser) {
                        // Mettre à jour l'utilisateur existant
                        $database->update('users', [
                            'nom' => $ldapUser['sn'],
                            'prenom' => $ldapUser['givenName'],
                            'email' => $ldapUser['mail'],
                            'date_modification' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$existingUser['id']]);

                        $result['updated']++;
                        $result['details'][] = "Utilisateur mis à jour: {$ldapUser['username']}";
                    } else {
                        // Créer un nouvel utilisateur
                        $database->insert('users', [
                            'ldap_uid' => $ldapUser['uid'],
                            'username' => $ldapUser['username'],
                            'nom' => $ldapUser['sn'],
                            'prenom' => $ldapUser['givenName'],
                            'email' => $ldapUser['mail'],
                            'role' => 'user',
                            'type' => 'ldap',
                            'actif' => 1
                        ]);

                        $result['created']++;
                        $result['details'][] = "Utilisateur créé: {$ldapUser['username']}";
                    }
                } catch (Exception $e) {
                    $result['errors']++;
                    $result['details'][] = "Erreur pour {$ldapUser['username']}: " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $result['errors']++;
            $result['details'][] = "Erreur générale: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Sauvegarde la configuration LDAP
     */
    public function saveConfig(array $config): bool
    {
        try {
            $configFile = __DIR__ . '/../../storage/ldap_config.json';
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            
            $this->config = $config;
            $this->connected = false; // Forcer une nouvelle connexion
            
            return true;
        } catch (Exception $e) {
            error_log('Erreur lors de la sauvegarde de la config LDAP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtient la configuration LDAP
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Ferme la connexion LDAP
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            ldap_unbind($this->connection);
            $this->connection = null;
            $this->connected = false;
        }
    }

    /**
     * Destructeur pour fermer la connexion
     */
    public function __destruct()
    {
        $this->disconnect();
    }
} 