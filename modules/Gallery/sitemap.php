<?php
return [
    'key'         => 'gallery',
    'label'       => 'Gallery',
    'description' => 'Gallery listing and individual photo pages',
    'default'     => true,
    'priority'    => '0.6',
    'changefreq'  => 'monthly',
    'provider'    => function (string $base, ?\Core\Database $db): array {
        $entries = [
            ['loc' => $base . '/gallery', 'priority' => '0.5', 'changefreq' => 'weekly'],
        ];
        if (!$db) {
            return $entries;
        }
        try {
            $cats = $db->fetchAll("SELECT slug FROM gallery_categories WHERE enabled = 1 ORDER BY position ASC, id ASC");
            foreach ($cats as $c) {
                $entries[] = ['loc' => $base . '/gallery/category/' . rawurlencode($c['slug']), 'changefreq' => 'weekly'];
            }
        } catch (\Throwable $e) {}
        try {
            $hasSlug = (bool)$db->fetch("SHOW COLUMNS FROM gallery_items LIKE 'slug'");
            $rows    = $db->fetchAll($hasSlug
                ? "SELECT id, slug FROM gallery_items ORDER BY id"
                : "SELECT id FROM gallery_items ORDER BY id");
            foreach ($rows as $r) {
                if ($hasSlug && !empty($r['slug'])) {
                    $entries[] = ['loc' => $base . '/gallery/photo/' . rawurlencode($r['slug'])];
                } else {
                    $entries[] = ['loc' => $base . '/gallery/view?id=' . (int)$r['id']];
                }
            }
        } catch (\Throwable $e) {}
        return $entries;
    },
];
