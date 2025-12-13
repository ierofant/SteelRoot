<?php
use Core\Database;

$app = include __DIR__ . '/../../app/config/app.php';
$dbCfg = include __DIR__ . '/../../app/config/database.php';
$base = rtrim($app['url'] ?? 'http://localhost', '/');
$paths = [$base . '/gallery'];

try {
    $db = new Database($dbCfg);
    $hasSlug = (bool)$db->fetch("SHOW COLUMNS FROM gallery_items LIKE 'slug'");
    $items = $db->fetchAll($hasSlug ? "SELECT id, slug FROM gallery_items" : "SELECT id FROM gallery_items");
    foreach ($items as $item) {
        if ($hasSlug && !empty($item['slug'])) {
            $paths[] = $base . '/gallery/photo/' . urlencode($item['slug']);
        } else {
            $paths[] = $base . '/gallery/view?id=' . (int)$item['id'];
        }
    }
} catch (\Throwable $e) {
    // ignore DB failure for sitemap
}

return $paths;
