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
            "SELECT n.slug,
                    n.title_en,
                    n.title_ru,
                    n.created_at,
                    n.preview_en,
                    n.preview_ru,
                    n.image_url,
                    n.views,
                    n.likes,
                    n.author_id,
                    u.name AS author_name,
                    u.username AS author_username,
                    u.avatar AS author_avatar
               FROM news n
               LEFT JOIN users u ON u.id = n.author_id
              ORDER BY n.created_at DESC
              LIMIT {$limit}"
        );
    },

    'view' => __DIR__ . '/views/blocks/home_news.php',
];
