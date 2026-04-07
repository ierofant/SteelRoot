<?php
return [
    'key'         => 'pages',
    'label'       => 'Pages',
    'description' => 'Static content pages (visible only)',
    'default'     => true,
    'priority'    => '0.7',
    'changefreq'  => 'monthly',
    'provider'    => function (string $base, ?\Core\Database $db): array {
        if (!$db) {
            return [];
        }
        $entries = [];
        try {
            $rows = $db->fetchAll("SELECT slug, updated_at FROM pages WHERE visible = 1 ORDER BY id");
            foreach ($rows as $r) {
                $entry = ['loc' => $base . '/' . rawurlencode($r['slug'])];
                if (!empty($r['updated_at'])) {
                    $entry['lastmod'] = date('Y-m-d', strtotime($r['updated_at']));
                }
                $entries[] = $entry;
            }
        } catch (\Throwable $e) {}
        return $entries;
    },
];
