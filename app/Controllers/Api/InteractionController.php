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
    private array $updatedAtCache = [];

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
        $column = match ($type) {
            'article' => 'articles',
            'video'   => 'video_items',
            default   => 'gallery_items',
        };
        $useSlug = $type === 'article';
        $fingerprint = $this->fp($request);
        $rl = new RateLimiter('view_' . $fingerprint . '_' . $type . '_' . $id, 3, 300, true);
        $already = false;
        if (!$rl->tooManyAttempts()) {
            $rl->hit();
            $viewSql = $this->tableHasUpdatedAt($column)
                ? "UPDATE {$column} SET views = views + 1, updated_at = updated_at WHERE "
                : "UPDATE {$column} SET views = views + 1 WHERE ";
            $this->db->execute($viewSql . ($useSlug ? 'slug = ?' : 'id = ?'), [$useSlug ? ($request->body['slug'] ?? '') : $id]);
        } else {
            $already = true;
        }
        $row = $this->db->fetch("SELECT views FROM {$column} WHERE " . ($useSlug ? 'slug = ?' : 'id = ?'), [$useSlug ? ($request->body['slug'] ?? '') : $id]);
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
        $rl = new RateLimiter('like_' . $fingerprint . '_' . $type . '_' . $id, 5, 60, true);
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
        $table = match ($type) {
            'article' => 'articles',
            'video'   => 'video_items',
            default   => 'gallery_items',
        };
        $likeSql = $this->tableHasUpdatedAt($table)
            ? "UPDATE {$table} SET likes = (SELECT COUNT(*) FROM likes WHERE entity_type = ? AND entity_id = ?), updated_at = updated_at WHERE id = ?"
            : "UPDATE {$table} SET likes = (SELECT COUNT(*) FROM likes WHERE entity_type = ? AND entity_id = ?) WHERE id = ?";
        $this->db->execute($likeSql, [$type, $id, $id]);
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
        return in_array($type, ['article', 'gallery', 'video'], true);
    }

    private function tableHasUpdatedAt(string $table): bool
    {
        if (!array_key_exists($table, $this->updatedAtCache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", ['updated_at']);
            $this->updatedAtCache[$table] = (bool)$row;
        }
        return $this->updatedAtCache[$table];
    }
}
