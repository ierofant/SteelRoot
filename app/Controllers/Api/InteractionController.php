<?php
namespace App\Controllers\Api;

use Core\Container;
use Core\Database;
use Core\RateLimiter;
use Core\Request;
use Core\Response;

class InteractionController
{
    private Database $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(Database::class);
    }

    public function view(Request $request): Response
    {
        $type = $request->body['type'] ?? '';
        $id = (int)($request->body['id'] ?? 0);
        if (!$this->isAllowedType($type) || $id <= 0) {
            return Response::json(['error' => 'bad_request'], 400);
        }
        $column = $type === 'article' ? 'articles' : 'gallery_items';
        $fingerprint = $this->fp($request);
        $rl = new RateLimiter('view_' . $fingerprint . '_' . $type . '_' . $id, 3, 300);
        $already = false;
        if (!$rl->tooManyAttempts()) {
            $rl->hit();
            $this->db->execute("UPDATE {$column} SET views = views + 1 WHERE " . ($type === 'article' ? 'slug = ?' : 'id = ?'), [$type === 'article' ? ($request->body['slug'] ?? '') : $id]);
        } else {
            $already = true;
        }
        $row = $this->db->fetch("SELECT views FROM {$column} WHERE " . ($type === 'article' ? 'slug = ?' : 'id = ?'), [$type === 'article' ? ($request->body['slug'] ?? '') : $id]);
        return Response::json(['ok' => true, 'already' => $already, 'views' => (int)($row['views'] ?? 0)]);
    }

    public function like(Request $request): Response
    {
        $type = $request->body['type'] ?? '';
        $id = (int)($request->body['id'] ?? 0);
        $fingerprint = $this->fp($request);
        if (!$this->isAllowedType($type) || $id <= 0 || $fingerprint === '') {
            return Response::json(['error' => 'bad_request'], 400);
        }
        $rl = new RateLimiter('like_' . $fingerprint . '_' . $type . '_' . $id, 5, 60);
        if ($rl->tooManyAttempts()) {
            return Response::json(['error' => 'rate_limited'], 429);
        }
        $rl->hit();
        $exists = $this->db->fetch("SELECT id FROM likes WHERE entity_type = ? AND entity_id = ? AND fingerprint = ?", [$type, $id, $fingerprint]);
        $this->db->execute("
            INSERT INTO likes (entity_type, entity_id, fingerprint, created_at)
            VALUES (:t, :id, :fp, NOW())
            ON DUPLICATE KEY UPDATE created_at = created_at
        ", [':t' => $type, ':id' => $id, ':fp' => $fingerprint]);
        $table = $type === 'article' ? 'articles' : 'gallery_items';
        $this->db->execute("UPDATE {$table} SET likes = (SELECT COUNT(*) FROM likes WHERE entity_type = ? AND entity_id = ?) WHERE id = ?", [$type, $id, $id]);
        $row = $this->db->fetch("SELECT likes FROM {$table} WHERE id = ?", [$id]);
        return Response::json([
            'ok' => true,
            'already' => $exists ? true : false,
            'likes' => (int)($row['likes'] ?? 0),
        ]);
    }

    private function fp(Request $request): string
    {
        $ip = $request->server['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $request->headers['user-agent'] ?? '';
        return substr(sha1($ip . '|' . $ua), 0, 40);
    }

    private function isAllowedType(string $type): bool
    {
        return in_array($type, ['article', 'gallery'], true);
    }
}
