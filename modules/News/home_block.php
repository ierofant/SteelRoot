<?php

/**
 * Home page block provider for News module.
 */
return [
    'settings_key'  => 'home_show_news',
    'order_key'     => 'home_order_news',
    'default_order' => 3,

    'provider' => function (\Core\Database $db, array $settings): array {
        $limit = max(1, min(30, (int)($settings['home_news_limit'] ?? 6)));
        return $db->fetchAll(
            "SELECT slug, title_en, title_ru, created_at, preview_en, preview_ru, image_url, views, likes
               FROM news
              ORDER BY created_at DESC
              LIMIT {$limit}"
        );
    },

    'view' => __DIR__ . '/views/blocks/home_news.php',
];
