<?php

namespace Gendoc\Core;

use Gendoc\Services\SessionService;

/**
 * Classe de routage pour l'application Gendoc
 */
class Router
{
    private array $routes = [];
    private SessionService $session;

    public function __construct()
    {
        $this->session = new SessionService();
    }

    /**
     * Enregistre une route GET
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route POST
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route PUT
     */
    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route DELETE
     */
    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Ajoute une route
     */
    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Dispatch la requête vers le bon contrôleur
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                // Vérification des middlewares
                if (!$this->checkMiddleware($route['middleware'], $request)) {
                    return new Response('Accès refusé', 403);
                }

                // Extraction des paramètres de l'URL
                $params = $this->extractParams($route['path'], $path);
                $request->setParams($params);

                // Exécution du handler
                return $this->executeHandler($route['handler'], $request);
            }
        }

        // Route non trouvée
        return new Response('Page non trouvée', 404);
    }

    /**
     * Vérifie si le chemin correspond au pattern
     */
    private function matchPath(string $pattern, string $path): bool
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        return preg_match($pattern, $path);
    }

    /**
     * Extrait les paramètres de l'URL
     */
    private function extractParams(string $pattern, string $path): array
    {
        $params = [];
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        foreach ($patternParts as $index => $part) {
            if (preg_match('/\{([^}]+)\}/', $part, $matches)) {
                $paramName = $matches[1];
                $params[$paramName] = $pathParts[$index] ?? null;
            }
        }

        return $params;
    }

    /**
     * Vérifie les middlewares
     */
    private function checkMiddleware(array $middleware, Request $request): bool
    {
        // Vérification de l'authentification
        if (isset($middleware['auth']) && $middleware['auth']) {
            if (!$this->session->isAuthenticated()) {
                return false;
            }
        }

        // Vérification du rôle
        if (isset($middleware['role'])) {
            $user = $this->session->getUser();
            if (!$user || $user['role'] !== $middleware['role']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exécute le handler (contrôleur)
     */
    private function executeHandler(array $handler, Request $request): Response
    {
        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            throw new \Exception("Contrôleur non trouvé: $controllerClass");
        }

        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            throw new \Exception("Méthode non trouvée: $method dans $controllerClass");
        }

        $result = $controller->$method($request);

        if ($result instanceof Response) {
            return $result;
        }

        // Si le résultat n'est pas une Response, on en crée une
        if (is_array($result)) {
            return new Response(json_encode($result), 200, ['Content-Type' => 'application/json']);
        }

        return new Response($result);
    }
} 