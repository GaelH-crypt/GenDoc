<?php

namespace Gendoc\Core;

/**
 * Classe Request pour encapsuler les données de la requête HTTP
 */
class Request
{
    private array $params = [];
    private array $query = [];
    private array $post = [];
    private array $files = [];
    private array $headers = [];
    private string $method;
    private string $path;
    private string $body;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->headers = $this->getRequestHeaders();
        $this->body = file_get_contents('php://input');
    }

    /**
     * Obtient la méthode HTTP
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtient le chemin de la requête
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Obtient un paramètre de l'URL
     */
    public function getParam(string $name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Obtient tous les paramètres de l'URL
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Définit les paramètres de l'URL
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Obtient un paramètre GET
     */
    public function getQuery(string $name, $default = null)
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * Obtient tous les paramètres GET
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Obtient un paramètre POST
     */
    public function getPost(string $name, $default = null)
    {
        return $this->post[$name] ?? $default;
    }

    /**
     * Obtient tous les paramètres POST
     */
    public function getPostParams(): array
    {
        return $this->post;
    }

    /**
     * Obtient un fichier uploadé
     */
    public function getFile(string $name)
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Obtient tous les fichiers uploadés
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Obtient un header HTTP
     */
    public function getHeader(string $name, $default = null)
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Obtient tous les headers HTTP
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtient le corps de la requête
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Obtient le corps de la requête décodé en JSON
     */
    public function getJsonBody(): array
    {
        $json = json_decode($this->body, true);
        return is_array($json) ? $json : [];
    }

    /**
     * Vérifie si la requête est AJAX
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Vérifie si la requête est en JSON
     */
    public function isJson(): bool
    {
        return strpos($this->getHeader('Content-Type', ''), 'application/json') === 0;
    }

    /**
     * Obtient l'adresse IP du client
     */
    public function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
               $_SERVER['HTTP_CLIENT_IP'] ?? 
               $_SERVER['REMOTE_ADDR'] ?? 
               'unknown';
    }

    /**
     * Obtient l'agent utilisateur
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Récupère tous les headers HTTP
     */
    private function getRequestHeaders(): array
    {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
                }
            }
        }

        return array_change_key_case($headers, CASE_LOWER);
    }

    /**
     * Valide et nettoie une donnée
     */
    public function sanitize(string $data): string
    {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valide un email
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide un nombre entier
     */
    public function validateInteger(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Valide un nombre décimal
     */
    public function validateFloat(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
} 