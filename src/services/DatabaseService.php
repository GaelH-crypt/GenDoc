<?php

namespace Gendoc\Services;

use PDO;
use PDOException;
use Exception;

/**
 * Service de gestion de la base de données
 */
class DatabaseService
{
    private PDO $pdo;
    private array $config;
    private static ?self $instance = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Singleton pour obtenir l'instance de la base de données
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Établit la connexion à la base de données
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['name'],
                $this->config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];

            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], $options);
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    /**
     * Obtient la connexion PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Exécute une requête SELECT et retourne tous les résultats
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'exécution de la requête: " . $e->getMessage());
        }
    }

    /**
     * Exécute une requête SELECT et retourne le premier résultat
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'exécution de la requête: " . $e->getMessage());
        }
    }

    /**
     * Exécute une requête INSERT, UPDATE ou DELETE
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'exécution de la requête: " . $e->getMessage());
        }
    }

    /**
     * Insère une ligne et retourne l'ID généré
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'insertion: " . $e->getMessage());
        }
    }

    /**
     * Met à jour une ligne
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = :$column";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($data, $whereParams));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la mise à jour: " . $e->getMessage());
        }
    }

    /**
     * Supprime une ligne
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM $table WHERE $where";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la suppression: " . $e->getMessage());
        }
    }

    /**
     * Compte le nombre de lignes
     */
    public function count(string $table, string $where = '1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE $where";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return (int) $result['count'];
        } catch (PDOException $e) {
            throw new Exception("Erreur lors du comptage: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si une table existe
     */
    public function tableExists(string $table): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['table' => $table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtient la structure d'une table
     */
    public function getTableStructure(string $table): array
    {
        $sql = "DESCRIBE $table";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération de la structure: " . $e->getMessage());
        }
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback(): void
    {
        $this->pdo->rollback();
    }

    /**
     * Vérifie si une transaction est en cours
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Échappe une chaîne pour éviter les injections SQL
     */
    public function escape(string $value): string
    {
        return $this->pdo->quote($value);
    }

    /**
     * Teste la connexion à la base de données
     */
    public function testConnection(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtient les informations sur la base de données
     */
    public function getDatabaseInfo(): array
    {
        try {
            $version = $this->pdo->query('SELECT VERSION() as version')->fetch();
            $charset = $this->pdo->query('SELECT @@character_set_database as charset')->fetch();
            
            return [
                'version' => $version['version'] ?? 'Unknown',
                'charset' => $charset['charset'] ?? 'Unknown',
                'name' => $this->config['name']
            ];
        } catch (PDOException $e) {
            return [
                'version' => 'Unknown',
                'charset' => 'Unknown',
                'name' => $this->config['name']
            ];
        }
    }
} 