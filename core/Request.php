<?php
namespace Core;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $body;
    public array $server;
    public array $cookies;
    public array $files;
    public array $headers;
    public array $params = [];

    public function __construct(string $method, string $path, array $query, array $body, array $server, array $cookies, array $files, array $headers, array $params = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path ?: '/';
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->headers = $headers;
        $this->params = $params;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        return new self($method, $path, $_GET, $_POST, $_SERVER, $_COOKIE, $_FILES, $headers);
    }

    public function isJson(): bool
    {
        $accept = $this->headers['accept'] ?? '';
        $contentType = $this->headers['content-type'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
