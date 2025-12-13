<?php
use Core\Database;

$app = include __DIR__ . '/../../app/config/app.php';
$dbCfg = include __DIR__ . '/../../app/config/database.php';
$base = rtrim($app['url'] ?? 'http://localhost', '/');
$entries = [$base . '/articles'];

try {
    $db = new Database($dbCfg);
    $articles = $db->fetchAll("SELECT slug FROM articles");
    foreach ($articles as $article) {
        $entries[] = $base . '/articles/' . rawurlencode($article['slug']);
    }
} catch (\Throwable $e) {
    // ignore db failures in sitemap provider
}
return $entries;
