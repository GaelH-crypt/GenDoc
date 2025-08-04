<?php

namespace Gendoc\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Service de logging pour l'application Gendoc
 */
class LoggerService
{
    private MonologLogger $logger;
    private string $logPath;
    private DatabaseService $database;

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
        $this->database = DatabaseService::getInstance();
        $this->initializeLogger();
    }

    /**
     * Initialise le logger Monolog
     */
    private function initializeLogger(): void
    {
        $this->logger = new MonologLogger('gendoc');

        // Créer le répertoire de logs s'il n'existe pas
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }

        // Handler pour les logs généraux (rotation quotidienne)
        $generalHandler = new RotatingFileHandler(
            $this->logPath . '/app.log',
            30, // Garder 30 jours
            MonologLogger::DEBUG
        );
        $generalHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        ));
        $this->logger->pushHandler($generalHandler);

        // Handler pour les erreurs (rotation quotidienne)
        $errorHandler = new RotatingFileHandler(
            $this->logPath . '/error.log',
            30,
            MonologLogger::ERROR
        );
        $errorHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        ));
        $this->logger->pushHandler($errorHandler);

        // Handler pour les logs de sécurité
        $securityHandler = new RotatingFileHandler(
            $this->logPath . '/security.log',
            90, // Garder 90 jours
            MonologLogger::INFO
        );
        $securityHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        ));
        $this->logger->pushHandler($securityHandler);
    }

    /**
     * Log un message de debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Log un message d'information
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
        $this->logToDatabase('info', $message, $context);
    }

    /**
     * Log un avertissement
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
        $this->logToDatabase('warning', $message, $context);
    }

    /**
     * Log une erreur
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
        $this->logToDatabase('error', $message, $context);
    }

    /**
     * Log une erreur critique
     */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
        $this->logToDatabase('critical', $message, $context);
    }

    /**
     * Log une alerte
     */
    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
        $this->logToDatabase('alert', $message, $context);
    }

    /**
     * Log une urgence
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
        $this->logToDatabase('emergency', $message, $context);
    }

    /**
     * Log une action de sécurité
     */
    public function security(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'security']));
        $this->logToDatabase('security', $message, $context);
    }

    /**
     * Log une action utilisateur
     */
    public function userAction(string $action, array $context = []): void
    {
        $message = "Action utilisateur: $action";
        $this->logger->info($message, array_merge($context, ['type' => 'user_action']));
        $this->logToDatabase('user_action', $message, $context);
    }

    /**
     * Log une action d'administration
     */
    public function adminAction(string $action, array $context = []): void
    {
        $message = "Action admin: $action";
        $this->logger->info($message, array_merge($context, ['type' => 'admin_action']));
        $this->logToDatabase('admin_action', $message, $context);
    }

    /**
     * Log une action de génération de document
     */
    public function documentGeneration(string $action, array $context = []): void
    {
        $message = "Génération document: $action";
        $this->logger->info($message, array_merge($context, ['type' => 'document_generation']));
        $this->logToDatabase('document_generation', $message, $context);
    }

    /**
     * Log une action de gestion de modèle
     */
    public function templateAction(string $action, array $context = []): void
    {
        $message = "Action modèle: $action";
        $this->logger->info($message, array_merge($context, ['type' => 'template_action']));
        $this->logToDatabase('template_action', $message, $context);
    }

    /**
     * Enregistre le log dans la base de données
     */
    private function logToDatabase(string $level, string $message, array $context = []): void
    {
        try {
            $sessionService = new SessionService();
            $userId = $sessionService->getUserId();
            
            $this->database->insert('logs', [
                'user_id' => $userId,
                'action' => $level,
                'details' => json_encode([
                    'message' => $message,
                    'context' => $context
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, on log dans le fichier seulement
            $this->logger->error('Erreur lors de l\'enregistrement en base: ' . $e->getMessage());
        }
    }

    /**
     * Obtient les logs depuis la base de données
     */
    public function getLogsFromDatabase(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where .= ' AND action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where .= ' AND date_action >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND date_action <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT l.*, u.username, u.nom, u.prenom 
                FROM logs l 
                LEFT JOIN users u ON l.user_id = u.id 
                WHERE $where 
                ORDER BY l.date_action DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->database->query($sql, $params);
    }

    /**
     * Compte le nombre total de logs
     */
    public function countLogs(array $filters = []): int
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where .= ' AND action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where .= ' AND date_action >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND date_action <= ?';
            $params[] = $filters['date_to'];
        }

        return $this->database->count('logs', $where, $params);
    }

    /**
     * Nettoie les anciens logs
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));
        
        $deleted = $this->database->execute(
            'DELETE FROM logs WHERE date_action < ?',
            [$cutoffDate]
        );

        $this->info("Nettoyage des logs: $deleted entrées supprimées (plus de $daysToKeep jours)");
        
        return $deleted;
    }

    /**
     * Exporte les logs en CSV
     */
    public function exportLogsToCsv(array $filters = [], string $filename = 'logs_export.csv'): string
    {
        $logs = $this->getLogsFromDatabase(10000, 0, $filters); // Limite élevée pour l'export
        
        $csvFile = $this->logPath . '/' . $filename;
        $handle = fopen($csvFile, 'w');
        
        // En-têtes CSV
        fputcsv($handle, [
            'ID', 'Date', 'Utilisateur', 'Action', 'Message', 'Détails', 'IP', 'User Agent'
        ]);
        
        // Données
        foreach ($logs as $log) {
            $details = json_decode($log['details'], true);
            $message = $details['message'] ?? '';
            
            fputcsv($handle, [
                $log['id'],
                $log['date_action'],
                $log['username'] ? $log['nom'] . ' ' . $log['prenom'] : 'Système',
                $log['action'],
                $message,
                json_encode($details),
                $log['ip_address'],
                $log['user_agent']
            ]);
        }
        
        fclose($handle);
        
        return $csvFile;
    }

    /**
     * Obtient les statistiques des logs
     */
    public function getLogStatistics(array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['date_from'])) {
            $where .= ' AND date_action >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND date_action <= ?';
            $params[] = $filters['date_to'];
        }

        // Total des logs
        $total = $this->database->count('logs', $where, $params);

        // Logs par action
        $actions = $this->database->query(
            "SELECT action, COUNT(*) as count FROM logs WHERE $where GROUP BY action ORDER BY count DESC",
            $params
        );

        // Logs par utilisateur
        $users = $this->database->query(
            "SELECT u.username, u.nom, u.prenom, COUNT(l.id) as count 
             FROM logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             WHERE $where 
             GROUP BY l.user_id 
             ORDER BY count DESC 
             LIMIT 10",
            $params
        );

        // Logs par jour (7 derniers jours)
        $daily = $this->database->query(
            "SELECT DATE(date_action) as date, COUNT(*) as count 
             FROM logs 
             WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY DATE(date_action) 
             ORDER BY date_action DESC"
        );

        return [
            'total' => $total,
            'actions' => $actions,
            'users' => $users,
            'daily' => $daily
        ];
    }
} 