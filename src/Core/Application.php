<?php

namespace Gendoc\Core;

use Gendoc\Controllers\AuthController;
use Gendoc\Controllers\DashboardController;
use Gendoc\Controllers\DocumentController;
use Gendoc\Controllers\TemplateController;
use Gendoc\Controllers\AdminController;
use Gendoc\Controllers\SettingsController;
use Gendoc\Services\DatabaseService;
use Gendoc\Services\SessionService;
use Gendoc\Services\LoggerService;

/**
 * Classe principale de l'application Gendoc
 */
class Application
{
    private array $config;
    private DatabaseService $database;
    private SessionService $session;
    private LoggerService $logger;
    private Router $router;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeServices();
        $this->setupRouter();
    }

    /**
     * Initialise les services de base
     */
    private function initializeServices(): void
    {
        $this->database = new DatabaseService($this->config['database']);
        $this->session = new SessionService($this->config['security']);
        $this->logger = new LoggerService($this->config['storage']['logs']);
        $this->router = new Router();
    }

    /**
     * Configure le routage de l'application
     */
    private function setupRouter(): void
    {
        // Routes publiques
        $this->router->get('/', [AuthController::class, 'login']);
        $this->router->get('/login', [AuthController::class, 'login']);
        $this->router->post('/login', [AuthController::class, 'authenticate']);
        $this->router->get('/logout', [AuthController::class, 'logout']);
        $this->router->get('/install', [AuthController::class, 'installWizard']);

        // Routes protégées (nécessitent une authentification)
        $this->router->get('/dashboard', [DashboardController::class, 'index'], ['auth' => true]);
        $this->router->get('/documents', [DocumentController::class, 'index'], ['auth' => true]);
        $this->router->get('/documents/create', [DocumentController::class, 'create'], ['auth' => true]);
        $this->router->post('/documents/generate', [DocumentController::class, 'generate'], ['auth' => true]);
        $this->router->get('/documents/download/{id}', [DocumentController::class, 'download'], ['auth' => true]);
        $this->router->delete('/documents/{id}', [DocumentController::class, 'delete'], ['auth' => true]);

        // Routes pour les modèles (admin uniquement)
        $this->router->get('/templates', [TemplateController::class, 'index'], ['auth' => true, 'role' => 'admin']);
        $this->router->get('/templates/create', [TemplateController::class, 'create'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/templates/upload', [TemplateController::class, 'upload'], ['auth' => true, 'role' => 'admin']);
        $this->router->get('/templates/edit/{id}', [TemplateController::class, 'edit'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/templates/update/{id}', [TemplateController::class, 'update'], ['auth' => true, 'role' => 'admin']);
        $this->router->delete('/templates/{id}', [TemplateController::class, 'delete'], ['auth' => true, 'role' => 'admin']);

        // Routes d'administration
        $this->router->get('/admin', [AdminController::class, 'index'], ['auth' => true, 'role' => 'admin']);
        $this->router->get('/admin/users', [AdminController::class, 'users'], ['auth' => true, 'role' => 'admin']);
        $this->router->get('/admin/logs', [AdminController::class, 'logs'], ['auth' => true, 'role' => 'admin']);
        $this->router->get('/admin/stats', [AdminController::class, 'stats'], ['auth' => true, 'role' => 'admin']);

        // Routes de paramétrage
        $this->router->get('/settings', [SettingsController::class, 'index'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/settings/database', [SettingsController::class, 'updateDatabase'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/settings/ldap', [SettingsController::class, 'updateLdap'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/settings/security', [SettingsController::class, 'updateSecurity'], ['auth' => true, 'role' => 'admin']);
        $this->router->post('/settings/email', [SettingsController::class, 'updateEmail'], ['auth' => true, 'role' => 'admin']);

        // API routes
        $this->router->get('/api/templates', [TemplateController::class, 'apiList'], ['auth' => true]);
        $this->router->get('/api/templates/{id}/fields', [TemplateController::class, 'apiFields'], ['auth' => true]);
        $this->router->post('/api/documents/preview', [DocumentController::class, 'apiPreview'], ['auth' => true]);
    }

    /**
     * Exécute l'application
     */
    public function run(): void
    {
        try {
            $request = new Request();
            $response = $this->router->dispatch($request);
            $response->send();
        } catch (\Exception $e) {
            $this->logger->error('Erreur application: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($this->config['app']['debug']) {
                throw $e;
            } else {
                $this->handleError($e);
            }
        }
    }

    /**
     * Gère les erreurs en mode production
     */
    private function handleError(\Exception $e): void
    {
        http_response_code(500);
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Une erreur interne est survenue']);
        } else {
            include __DIR__ . '/../../views/errors/500.php';
        }
    }

    /**
     * Vérifie si la requête est AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Obtient la configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Obtient le service de base de données
     */
    public function getDatabase(): DatabaseService
    {
        return $this->database;
    }

    /**
     * Obtient le service de session
     */
    public function getSession(): SessionService
    {
        return $this->session;
    }

    /**
     * Obtient le service de logging
     */
    public function getLogger(): LoggerService
    {
        return $this->logger;
    }
} 