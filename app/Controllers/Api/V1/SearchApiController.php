<?php
namespace App\Controllers\Api\V1;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\RateLimiter;

class SearchApiController
{
    private Database $db;
    private $config;

    public function __construct(Container $container)
    {
        $this->db = $container->get(Database::class);
        $this->config = $container->get('config');
    }

    public function search(Request $request): Response
    {
        $cfg = $this->config['limits']['rl_api_search'] ?? ['max' => 60, 'window' => 60];
        $limiter = new RateLimiter('api_search', $cfg['max'], $cfg['window'], true);
        if ($limiter->tooManyAttempts()) {
            return Response::json(['error' => 'Too many requests'], 429);
        }
        $q = trim($request->query['q'] ?? '');
        if ($q === '') {
            return Response::json([]);
        }
        $limiter->hit();
        $like = '%' . $q . '%';
        $articles = $this->db->fetchAll("
            SELECT slug, title_en, title_ru FROM articles
            WHERE MATCH(title_en, title_ru, body_en, body_ru) AGAINST(:q IN BOOLEAN MODE)
               OR title_en LIKE :like OR title_ru LIKE :like
            LIMIT 20
        ", [':q' => $q . '*', ':like' => $like]);
        $gallery = $this->db->fetchAll("
            SELECT id, title_en, title_ru, path_thumb FROM gallery_items
            WHERE MATCH(title_en, title_ru, description_en, description_ru) AGAINST(:q IN BOOLEAN MODE)
               OR title_en LIKE :like OR title_ru LIKE :like
            LIMIT 20
        ", [':q' => $q . '*', ':like' => $like]);
        return Response::json(['articles' => $articles, 'gallery' => $gallery]);
    }

    public function autocomplete(Request $request): Response
    {
        $cfg = $this->config['limits']['rl_api_autocomplete'] ?? ['max' => 120, 'window' => 60];
        $limiter = new RateLimiter('api_autocomplete', $cfg['max'], $cfg['window'], true);
        if ($limiter->tooManyAttempts()) {
            return Response::json(['error' => 'Too many requests'], 429);
        }
        $term = trim($request->query['term'] ?? '');
        if ($term === '') {
            return Response::json([]);
        }
        $limiter->hit();
        $like = $term . '%';
        $rows = $this->db->fetchAll("SELECT name, slug FROM tags WHERE name LIKE ? OR slug LIKE ? ORDER BY name ASC LIMIT 10", [$like, $like]);
        return Response::json($rows);
    }
}
