<?php

namespace Gendoc\Core;

/**
 * Classe Response pour gérer les réponses HTTP
 */
class Response
{
    private string $content;
    private int $statusCode;
    private array $headers;
    private string $contentType;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = array_merge([
            'Content-Type' => 'text/html; charset=UTF-8'
        ], $headers);
        $this->contentType = $this->headers['Content-Type'];
    }

    /**
     * Crée une réponse JSON
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Crée une réponse de redirection
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * Crée une réponse d'erreur
     */
    public static function error(string $message, int $statusCode = 500): self
    {
        return new self($message, $statusCode);
    }

    /**
     * Crée une réponse de succès
     */
    public static function success(string $message, int $statusCode = 200): self
    {
        return new self($message, $statusCode);
    }

    /**
     * Définit le contenu de la réponse
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Obtient le contenu de la réponse
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Définit le code de statut HTTP
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Obtient le code de statut HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Définit un header HTTP
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Obtient tous les headers HTTP
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Définit le type de contenu
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        $this->headers['Content-Type'] = $contentType;
        return $this;
    }

    /**
     * Obtient le type de contenu
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Définit le type de contenu pour JSON
     */
    public function setJson(): self
    {
        return $this->setContentType('application/json; charset=UTF-8');
    }

    /**
     * Définit le type de contenu pour XML
     */
    public function setXml(): self
    {
        return $this->setContentType('application/xml; charset=UTF-8');
    }

    /**
     * Définit le type de contenu pour du texte brut
     */
    public function setText(): self
    {
        return $this->setContentType('text/plain; charset=UTF-8');
    }

    /**
     * Définit le type de contenu pour HTML
     */
    public function setHtml(): self
    {
        return $this->setContentType('text/html; charset=UTF-8');
    }

    /**
     * Définit le type de contenu pour un fichier à télécharger
     */
    public function setDownload(string $filename, string $contentType = 'application/octet-stream'): self
    {
        $this->headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        return $this->setContentType($contentType);
    }

    /**
     * Ajoute un header de cache
     */
    public function setCache(int $seconds): self
    {
        $this->headers['Cache-Control'] = "public, max-age=$seconds";
        return $this;
    }

    /**
     * Désactive le cache
     */
    public function setNoCache(): self
    {
        $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['Expires'] = '0';
        return $this;
    }

    /**
     * Envoie la réponse au navigateur
     */
    public function send(): void
    {
        // Envoi du code de statut HTTP
        http_response_code($this->statusCode);

        // Envoi des headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Envoi du contenu
        echo $this->content;
    }

    /**
     * Vérifie si la réponse est une redirection
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Vérifie si la réponse est une erreur
     */
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }

    /**
     * Vérifie si la réponse est un succès
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Obtient le message de statut HTTP
     */
    public function getStatusText(): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];

        return $statusTexts[$this->statusCode] ?? 'Unknown';
    }
} 