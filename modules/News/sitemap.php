<?php
return [
    'key' => 'news', 'label' => 'News', 'default' => true, 'priority' => '0.9', 'changefreq' => 'daily',
    'provider' => function (string $base, ?\Core\Database $db): array {
        $entries = [['loc' => $base . '/news', 'priority' => '0.7', 'changefreq' => 'daily']];
        if (!$db) return $entries;
        try {
            foreach ($db->fetchAll("SELECT slug, updated_at FROM news ORDER BY id") as $r) {
                $e = ['loc' => $base . '/news/' . rawurlencode($r['slug'])];
                if (!empty($r['updated_at'])) $e['lastmod'] = date('Y-m-d', strtotime($r['updated_at']));
                $entries[] = $e;
            }
        } catch (\Throwable $e) {}
        return $entries;
    },
];
