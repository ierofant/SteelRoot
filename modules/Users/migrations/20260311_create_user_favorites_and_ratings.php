<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                entity_type VARCHAR(64) NOT NULL,
                entity_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_favorite (user_id, entity_type, entity_id),
                INDEX idx_user_favorite_lookup (entity_type, entity_id),
                CONSTRAINT fk_user_favorites_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS user_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                target_user_id INT NOT NULL,
                author_user_id INT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                review TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                ip_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_ratings_target (target_user_id, status),
                INDEX idx_user_ratings_author (author_user_id),
                CONSTRAINT fk_user_ratings_target
                    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_ratings_author
                    FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_ratings");
        $db->execute("DROP TABLE IF EXISTS user_favorites");
    }
};
