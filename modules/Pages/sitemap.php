<?php
use Core\Database;

$app = include __DIR__ . '/../../app/config/app.php';
$dbCfg = include __DIR__ . '/../../app/config/database.php';
$base = rtrim($app['url'] ?? 'http://localhost', '/');
$entries = [];

try {
    $db = new Database($dbCfg);
    $pages = $db->fetchAll("SELECT slug FROM pages WHERE visible = 1");
    foreach ($pages as $page) {
        $entries[] = $base . '/' . rawurlencode($page['slug']);
    }
} catch (\Throwable $e) {
    // ignore db failures in sitemap provider
}
return $entries;
