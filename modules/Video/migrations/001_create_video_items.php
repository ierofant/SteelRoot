<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS video_items (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                slug        VARCHAR(255) NOT NULL UNIQUE,
                title_en    VARCHAR(255) NOT NULL DEFAULT '',
                title_ru    VARCHAR(255) NOT NULL DEFAULT '',
                description_en TEXT,
                description_ru TEXT,
                video_url   VARCHAR(1000) NOT NULL,
                video_type  ENUM('youtube','vimeo','mp4','embed') NOT NULL DEFAULT 'youtube',
                video_id    VARCHAR(255) NOT NULL DEFAULT '',
                thumbnail_url VARCHAR(1000) DEFAULT NULL,
                duration    VARCHAR(20)  DEFAULT NULL,
                views       INT UNSIGNED NOT NULL DEFAULT 0,
                likes       INT UNSIGNED NOT NULL DEFAULT 0,
                enabled     TINYINT(1)   NOT NULL DEFAULT 1,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_enabled_created (enabled, created_at),
                FULLTEXT INDEX ft_video (title_en, title_ru, description_en, description_ru)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS video_items");
    }
};
