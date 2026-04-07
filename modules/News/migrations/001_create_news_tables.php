<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS news_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL,
                name_en VARCHAR(255) NOT NULL DEFAULT '',
                name_ru VARCHAR(255) NOT NULL DEFAULT '',
                image_url VARCHAR(1024) NULL,
                position INT NOT NULL DEFAULT 0,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_news_categories_slug (slug),
                KEY idx_news_categories_position (position),
                KEY idx_news_categories_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS news (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL,
                category_id INT UNSIGNED NULL,
                author_id INT NULL,
                title_en VARCHAR(255) NOT NULL DEFAULT '',
                title_ru VARCHAR(255) NOT NULL DEFAULT '',
                preview_en TEXT NULL,
                preview_ru TEXT NULL,
                description_en TEXT NULL,
                description_ru TEXT NULL,
                body_en MEDIUMTEXT NULL,
                body_ru MEDIUMTEXT NULL,
                image_url VARCHAR(1024) NULL,
                cover_url VARCHAR(1024) NULL,
                views INT NOT NULL DEFAULT 0,
                likes INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_news_slug (slug),
                KEY idx_news_category_id (category_id),
                KEY idx_news_author_id (author_id),
                KEY idx_news_created_at (created_at),
                KEY idx_news_views (views),
                KEY idx_news_likes (likes)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS news");
        $db->execute("DROP TABLE IF EXISTS news_categories");
    }
};
