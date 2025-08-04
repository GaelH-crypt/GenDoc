<?php

namespace Gendoc\Controllers;

use Gendoc\Core\Request;
use Gendoc\Core\Response;
use Gendoc\Services\SessionService;
use Gendoc\Services\DatabaseService;
use Gendoc\Services\LoggerService;

/**
 * Contrôleur du dashboard
 */
class DashboardController
{
    private SessionService $session;
    private DatabaseService $database;
    private LoggerService $logger;

    public function __construct()
    {
        $this->session = new SessionService();
        $this->database = DatabaseService::getInstance();
        $this->logger = new LoggerService(__DIR__ . '/../../storage/logs');
    }

    /**
     * Affiche le dashboard principal
     */
    public function index(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user) {
            return Response::redirect('/login');
        }

        // Récupération des statistiques
        $stats = $this->getStats($user['id']);
        
        // Récupération des documents récents
        $recentDocuments = $this->getRecentDocuments($user['id']);
        
        // Récupération des données d'activité
        $activityData = $this->getActivityData($user['id']);

        return $this->renderView('dashboard/index.php', [
            'user' => $user,
            'stats' => $stats,
            'recent_documents' => $recentDocuments,
            'activity_data' => $activityData
        ]);
    }

    /**
     * Obtient les statistiques pour l'utilisateur
     */
    private function getStats(int $userId): array
    {
        // Total des documents générés par l'utilisateur
        $totalDocuments = $this->database->count('documents', 'user_id = ?', [$userId]);
        
        // Documents générés ce mois
        $thisMonth = $this->database->count(
            'documents', 
            'user_id = ? AND MONTH(date_gen) = MONTH(CURRENT_DATE()) AND YEAR(date_gen) = YEAR(CURRENT_DATE())',
            [$userId]
        );
        
        // Total des modèles disponibles
        $totalTemplates = $this->database->count('modeles', 'actif = 1');
        
        // Calcul de l'espace de stockage utilisé
        $storageUsed = $this->calculateStorageUsage($userId);

        return [
            'total_documents' => $totalDocuments,
            'this_month' => $thisMonth,
            'total_templates' => $totalTemplates,
            'storage_used' => $storageUsed
        ];
    }

    /**
     * Obtient les documents récents de l'utilisateur
     */
    private function getRecentDocuments(int $userId, int $limit = 10): array
    {
        return $this->database->query(
            "SELECT d.*, m.nom as modele_nom 
             FROM documents d 
             LEFT JOIN modeles m ON d.modele_id = m.id 
             WHERE d.user_id = ? 
             ORDER BY d.date_gen DESC 
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Obtient les données d'activité des 7 derniers jours
     */
    private function getActivityData(int $userId): array
    {
        $activityData = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $this->database->count(
                'documents',
                'user_id = ? AND DATE(date_gen) = ?',
                [$userId, $date]
            );
            
            $activityData[date('d/m', strtotime($date))] = $count;
        }
        
        return $activityData;
    }

    /**
     * Calcule l'utilisation du stockage
     */
    private function calculateStorageUsage(int $userId): int
    {
        // Calculer la taille totale des documents de l'utilisateur
        $documents = $this->database->query(
            "SELECT path_fichier FROM documents WHERE user_id = ? AND path_fichier IS NOT NULL",
            [$userId]
        );
        
        $totalSize = 0;
        foreach ($documents as $doc) {
            if (file_exists($doc['path_fichier'])) {
                $totalSize += filesize($doc['path_fichier']);
            }
        }
        
        // Limite de stockage par utilisateur (100 MB par défaut)
        $storageLimit = 100 * 1024 * 1024; // 100 MB
        
        return min(100, round(($totalSize / $storageLimit) * 100));
    }

    /**
     * API pour obtenir les statistiques en temps réel
     */
    public function getStatsApi(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user) {
            return Response::json(['error' => 'Non authentifié'], 401);
        }

        $stats = $this->getStats($user['id']);
        
        return Response::json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * API pour obtenir les documents récents
     */
    public function getRecentDocumentsApi(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user) {
            return Response::json(['error' => 'Non authentifié'], 401);
        }

        $limit = $request->getQuery('limit', 10);
        $documents = $this->getRecentDocuments($user['id'], $limit);
        
        return Response::json([
            'success' => true,
            'documents' => $documents
        ]);
    }

    /**
     * API pour obtenir les données d'activité
     */
    public function getActivityApi(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user) {
            return Response::json(['error' => 'Non authentifié'], 401);
        }

        $activityData = $this->getActivityData($user['id']);
        
        return Response::json([
            'success' => true,
            'activity' => $activityData
        ]);
    }

    /**
     * Obtient les statistiques globales (admin uniquement)
     */
    public function getGlobalStats(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user || $user['role'] !== 'admin') {
            return Response::json(['error' => 'Accès refusé'], 403);
        }

        // Statistiques globales
        $totalUsers = $this->database->count('users', 'actif = 1');
        $totalDocuments = $this->database->count('documents');
        $totalTemplates = $this->database->count('modeles', 'actif = 1');
        
        // Documents par jour (7 derniers jours)
        $dailyStats = $this->database->query(
            "SELECT DATE(date_gen) as date, COUNT(*) as count 
             FROM documents 
             WHERE date_gen >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) 
             GROUP BY DATE(date_gen) 
             ORDER BY date_gen DESC"
        );
        
        // Utilisateurs les plus actifs
        $topUsers = $this->database->query(
            "SELECT u.nom, u.prenom, COUNT(d.id) as doc_count 
             FROM users u 
             LEFT JOIN documents d ON u.id = d.user_id 
             WHERE u.actif = 1 
             GROUP BY u.id 
             ORDER BY doc_count DESC 
             LIMIT 10"
        );
        
        // Modèles les plus utilisés
        $topTemplates = $this->database->query(
            "SELECT m.nom, COUNT(d.id) as usage_count 
             FROM modeles m 
             LEFT JOIN documents d ON m.id = d.modele_id 
             WHERE m.actif = 1 
             GROUP BY m.id 
             ORDER BY usage_count DESC 
             LIMIT 10"
        );

        return Response::json([
            'success' => true,
            'stats' => [
                'total_users' => $totalUsers,
                'total_documents' => $totalDocuments,
                'total_templates' => $totalTemplates,
                'daily_stats' => $dailyStats,
                'top_users' => $topUsers,
                'top_templates' => $topTemplates
            ]
        ]);
    }

    /**
     * Obtient les notifications pour l'utilisateur
     */
    public function getNotifications(Request $request): Response
    {
        $user = $this->session->getUser();
        
        if (!$user) {
            return Response::json(['error' => 'Non authentifié'], 401);
        }

        $notifications = [];
        
        // Vérifier les nouveaux modèles disponibles
        $newTemplates = $this->database->query(
            "SELECT * FROM modeles 
             WHERE actif = 1 AND date_ajout >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
             ORDER BY date_ajout DESC 
             LIMIT 5"
        );
        
        if (!empty($newTemplates)) {
            $notifications[] = [
                'type' => 'info',
                'title' => 'Nouveaux modèles disponibles',
                'message' => count($newTemplates) . ' nouveau(x) modèle(s) disponible(s)',
                'data' => $newTemplates
            ];
        }
        
        // Vérifier l'espace de stockage
        $storageUsed = $this->calculateStorageUsage($user['id']);
        if ($storageUsed > 80) {
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Espace de stockage',
                'message' => 'Votre espace de stockage est presque plein (' . $storageUsed . '%)',
                'data' => ['usage' => $storageUsed]
            ];
        }
        
        // Vérifier les documents non téléchargés
        $unreadDocuments = $this->database->count(
            'documents',
            'user_id = ? AND statut = "generated"',
            [$user['id']]
        );
        
        if ($unreadDocuments > 0) {
            $notifications[] = [
                'type' => 'success',
                'title' => 'Documents prêts',
                'message' => $unreadDocuments . ' document(s) prêt(s) à télécharger',
                'data' => ['count' => $unreadDocuments]
            ];
        }

        return Response::json([
            'success' => true,
            'notifications' => $notifications
        ]);
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