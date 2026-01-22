<?php
declare(strict_types=1);

namespace Modules\Api\Controllers;

use Core\Container;
use Core\Database;
use Core\Logger;
use Core\Request;
use Core\Response;
use Modules\Api\OpenApi\Attributes\ApiParam;
use Modules\Api\OpenApi\Attributes\ApiResponse;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiSecurity;
use Modules\Api\OpenApi\Attributes\ApiTag;
use Modules\Api\Support\JsonNegotiationTrait;

#[ApiTag('API Keys')]
#[ApiSecurity(['admin'])]
class ApiKeysController
{
    use JsonNegotiationTrait;

    private Database $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(Database::class);
    }

    #[ApiRoute('GET', '/api/v1/api-keys', auth: true)]
    #[ApiResponse(200, 'List of API keys')]
    public function index(Request $request): Response
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, scopes, enabled, last_used_at, created_at FROM api_keys ORDER BY id ASC'
        );
        $keys = array_map(function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'scopes' => $this->decodeScopes($row['scopes'] ?? null),
                'enabled' => (bool)($row['enabled'] ?? false),
                'last_used_at' => $row['last_used_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);

        return $this->respond($request, ['data' => $keys]);
    }

    #[ApiRoute('POST', '/api/v1/api-keys', auth: true)]
    #[ApiResponse(201, 'API key created')]
    #[ApiResponse(400, 'Bad request')]
    public function create(Request $request): Response
    {
        $data = $this->readJsonBody($request);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return $this->respond($request, ['error' => 'name_required'], 400);
        }

        $scopes = $data['scopes'] ?? [];
        if (!is_array($scopes)) {
            return $this->respond($request, ['error' => 'invalid_scopes'], 400);
        }
        $scopes = $this->normalizeScopes($scopes);
        $enabled = array_key_exists('enabled', $data) ? (bool)$data['enabled'] : true;

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $scopesJson = $this->encodeScopes($scopes);

        $this->db->execute(
            'INSERT INTO api_keys (name, token_hash, scopes, enabled, created_at) VALUES (:name, :hash, :scopes, :enabled, NOW())',
            [
                ':name' => $name,
                ':hash' => $hash,
                ':scopes' => $scopesJson,
                ':enabled' => $enabled ? 1 : 0,
            ]
        );
        $idRow = $this->db->fetch('SELECT LAST_INSERT_ID() as id');
        $id = (int)($idRow['id'] ?? 0);

        return $this->respond($request, [
            'token' => $token,
            'key' => [
                'id' => $id,
                'name' => $name,
                'scopes' => $scopes,
                'enabled' => $enabled,
                'last_used_at' => null,
                'created_at' => null,
            ],
        ], 201);
    }

    #[ApiRoute('POST', '/api/v1/api-keys/{id}/disable', auth: true)]
    #[ApiParam('id', 'path', true, ['type' => 'integer'])]
    #[ApiResponse(200, 'API key disabled')]
    #[ApiResponse(400, 'Bad request')]
    public function disable(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        if ($id <= 0) {
            return $this->respond($request, ['error' => 'invalid_id'], 400);
        }
        $this->db->execute('UPDATE api_keys SET enabled = 0 WHERE id = :id', [':id' => $id]);
        return $this->respond($request, ['ok' => true]);
    }

    #[ApiRoute('POST', '/api/v1/api-keys/{id}/rotate', auth: true)]
    #[ApiParam('id', 'path', true, ['type' => 'integer'])]
    #[ApiResponse(200, 'API key rotated')]
    #[ApiResponse(400, 'Bad request')]
    public function rotate(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        if ($id <= 0) {
            return $this->respond($request, ['error' => 'invalid_id'], 400);
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->db->execute(
            'UPDATE api_keys SET token_hash = :hash, last_used_at = NULL WHERE id = :id',
            [':hash' => $hash, ':id' => $id]
        );

        return $this->respond($request, ['ok' => true, 'token' => $token]);
    }

    private function readJsonBody(Request $request): array
    {
        $contentType = $request->headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || trim($raw) === '') {
                return [];
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                Logger::log('API keys JSON decode failed: ' . $e->getMessage());
                return [];
            }
        }
        return $request->body;
    }

    private function normalizeScopes(array $scopes): array
    {
        $clean = array_values(array_filter(array_map('strval', $scopes)));
        $clean = array_values(array_unique($clean));
        sort($clean);
        return $clean;
    }

    private function encodeScopes(array $scopes): string
    {
        try {
            return json_encode($scopes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::log('API keys scopes encode failed: ' . $e->getMessage());
            return '[]';
        }
    }

    private function decodeScopes($raw): array
    {
        if (is_array($raw)) {
            return $this->normalizeScopes($raw);
        }
        $raw = $raw !== null ? (string)$raw : '';
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[' || $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeScopes($decoded);
            }
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        return $this->normalizeScopes($parts);
    }
}
