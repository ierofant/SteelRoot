<?php
return [
    'key'         => 'articles',
    'label'       => 'Articles',
    'description' => 'Article listing and individual article pages',
    'default'     => true,
    'priority'    => '0.8',
    'changefreq'  => 'weekly',
    'provider'    => function (string $base, ?\Core\Database $db): array {
        $entries = [
            ['loc' => $base . '/articles', 'priority' => '0.6', 'changefreq' => 'daily'],
        ];
        if (!$db) {
            return $entries;
        }
        try {
            $cats = $db->fetchAll("SELECT slug FROM article_categories WHERE enabled = 1 ORDER BY position ASC, id ASC");
            foreach ($cats as $c) {
                $entries[] = ['loc' => $base . '/articles/category/' . rawurlencode($c['slug']), 'changefreq' => 'weekly'];
            }
        } catch (\Throwable $e) {}
        try {
            $rows = $db->fetchAll("SELECT slug, updated_at FROM articles ORDER BY id");
            foreach ($rows as $r) {
                $entry = ['loc' => $base . '/articles/' . rawurlencode($r['slug'])];
                if (!empty($r['updated_at'])) {
                    $entry['lastmod'] = date('Y-m-d', strtotime($r['updated_at']));
                }
                $entries[] = $entry;
            }
        } catch (\Throwable $e) {}
        return $entries;
    },
];
