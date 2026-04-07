<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                display_name VARCHAR(160) NULL,
                bio TEXT NULL,
                specialization VARCHAR(255) NULL,
                styles VARCHAR(255) NULL,
                city VARCHAR(120) NULL,
                studio_name VARCHAR(160) NULL,
                experience_years SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                price_from DECIMAL(10,2) NULL,
                booking_status VARCHAR(32) NOT NULL DEFAULT 'open',
                contacts_text TEXT NULL,
                external_links_json TEXT NULL,
                cover_image VARCHAR(255) NULL,
                visibility_mode VARCHAR(32) NOT NULL DEFAULT 'public',
                show_contacts TINYINT(1) NOT NULL DEFAULT 1,
                show_favorites TINYINT(1) NOT NULL DEFAULT 1,
                show_comments TINYINT(1) NOT NULL DEFAULT 1,
                show_ratings TINYINT(1) NOT NULL DEFAULT 1,
                show_works TINYINT(1) NOT NULL DEFAULT 1,
                comments_moderation TINYINT(1) NOT NULL DEFAULT 0,
                is_master TINYINT(1) NOT NULL DEFAULT 0,
                is_verified TINYINT(1) NOT NULL DEFAULT 0,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                plan_slug VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_user_profiles_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_profiles_master (is_master),
                INDEX idx_user_profiles_verified (is_verified),
                INDEX idx_user_profiles_featured (is_featured),
                INDEX idx_user_profiles_visibility (visibility_mode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            INSERT INTO user_profiles (user_id, display_name, visibility_mode, created_at, updated_at)
            SELECT u.id, u.name, IFNULL(u.profile_visibility, 'public'), NOW(), NOW()
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE up.user_id IS NULL
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_profiles");
    }
};
