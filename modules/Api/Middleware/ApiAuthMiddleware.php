<?php
declare(strict_types=1);

namespace Modules\Api\Middleware;

use Closure;
use Core\Container;
use Core\Database;
use Core\Logger;
use Core\Request;
use Core\Response;

class ApiAuthMiddleware
{
    private array $scopeMap;

    public function __construct(array $scopeMap = [])
    {
        $this->scopeMap = $scopeMap;
    }

    public function __invoke(Request $req, Closure $next, Container $c): Response
    {
        if (str_starts_with($req->path, '/api/') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $token = $this->extractToken($req);
        if ($token === '') {
            return $this->jsonError('unauthorized', 401);
        }

        $hash = hash('sha256', $token);
        try {
            $db = $c->get(Database::class);
        } catch (\Throwable $e) {
            Logger::log('API auth db unavailable: ' . $e->getMessage());
            return $this->jsonError('internal_error', 500);
        }

        $row = $db->fetch(
            'SELECT id, name, token_hash, scopes, enabled, last_used_at FROM api_keys WHERE token_hash = :hash LIMIT 1',
            [':hash' => $hash]
        );

        if (!$row || !hash_equals((string)($row['token_hash'] ?? ''), $hash)) {
            return $this->jsonError('unauthorized', 401);
        }

        if (empty($row['enabled'])) {
            return $this->jsonError('forbidden', 403);
        }

        $requiredScopes = $this->requiredScopes($req);
        if (!empty($requiredScopes)) {
            $tokenScopes = $this->normalizeScopes($row['scopes'] ?? null);
            if (!$this->hasScopes($tokenScopes, $requiredScopes)) {
                return $this->jsonError('forbidden', 403);
            }
        }

        try {
            $db->execute('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id', [':id' => $row['id']]);
        } catch (\Throwable $e) {
            Logger::log('API key last_used_at update failed: ' . $e->getMessage());
        }

        $row['scopes'] = $this->normalizeScopes($row['scopes'] ?? null);
        $req->attrs['api_key'] = $row;
        return $next($req);
    }

    private function extractToken(Request $req): string
    {
        $auth = $req->headers['authorization'] ?? ($req->server['HTTP_AUTHORIZATION'] ?? '');
        if ($auth === '') {
            return '';
        }
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        return '';
    }

    private function requiredScopes(Request $req): array
    {
        foreach ($this->scopeMap as $route) {
            if (($route['method'] ?? '') !== $req->method) {
                continue;
            }
            if (($route['path'] ?? '') === $req->path) {
                return $route['scopes'] ?? [];
            }
            if (!empty($route['regex']) && preg_match($route['regex'], $req->path) === 1) {
                return $route['scopes'] ?? [];
            }
        }
        return [];
    }

    private function normalizeScopes($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if ($raw === null) {
            return [];
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[' || $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        $parts = preg_split('/[\s,]+/', $raw);
        return array_values(array_filter(array_map('strval', $parts)));
    }

    private function hasScopes(array $tokenScopes, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                return false;
            }
        }
        return true;
    }

    private function jsonError(string $code, int $status): Response
    {
        try {
            $json = json_encode(['error' => $code], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::log('API auth json error failed: ' . $e->getMessage());
            $json = '{"error":"internal_error"}';
            $status = 500;
        }
        if (function_exists('header_remove')) {
            header_remove('Set-Cookie');
        }
        return new Response($json, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
