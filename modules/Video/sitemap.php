<?php
return [
    'key'        => 'video',
    'label'      => 'Video',
    'description'=> 'Video listing and individual video pages',
    'default'    => true,
    'priority'   => '0.6',
    'changefreq' => 'weekly',
    'provider'   => function (string $base, ?\Core\Database $db): array {
        $entries = [
            ['loc' => $base . '/videos', 'priority' => '0.5', 'changefreq' => 'weekly'],
        ];
        if (!$db) {
            return $entries;
        }
        try {
            $cats = $db->fetchAll("SELECT slug, updated_at FROM video_categories WHERE enabled = 1 ORDER BY id");
            foreach ($cats as $c) {
                if (empty($c['slug'])) {
                    continue;
                }
                $entry = [
                    'loc' => $base . '/videos/category/' . rawurlencode((string)$c['slug']),
                    'priority' => '0.5',
                    'changefreq' => 'weekly',
                ];
                if (!empty($c['updated_at'])) {
                    $entry['lastmod'] = date('Y-m-d', strtotime($c['updated_at']));
                }
                $entries[] = $entry;
            }

            $rows = $db->fetchAll(
                "SELECT v.slug, v.updated_at, c.slug AS category_slug
                 FROM video_items v
                 INNER JOIN video_categories c ON c.id = v.category_id
                 WHERE v.enabled = 1
                 ORDER BY v.id"
            );
            foreach ($rows as $r) {
                $path = '/videos/' . rawurlencode((string)$r['category_slug']) . '/' . rawurlencode((string)$r['slug']);
                $entry = ['loc' => $base . $path];
                if (!empty($r['updated_at'])) {
                    $entry['lastmod'] = date('Y-m-d', strtotime($r['updated_at']));
                }
                $entries[] = $entry;
            }
        } catch (\Throwable $e) {}
        return $entries;
    },
];
