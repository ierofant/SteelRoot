<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS comments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                parent_id INT UNSIGNED DEFAULT NULL,
                root_id INT UNSIGNED DEFAULT NULL,
                depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
                user_id INT UNSIGNED DEFAULT NULL,
                guest_name VARCHAR(120) DEFAULT NULL,
                guest_email VARCHAR(190) DEFAULT NULL,
                body TEXT NOT NULL,
                status ENUM('pending','approved','rejected','spam','deleted') NOT NULL DEFAULT 'pending',
                ip VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                approved_at DATETIME DEFAULT NULL,
                moderated_by INT UNSIGNED DEFAULT NULL,
                moderated_at DATETIME DEFAULT NULL,
                INDEX idx_comments_entity_status_created (entity_type, entity_id, status, created_at),
                INDEX idx_comments_parent (parent_id),
                INDEX idx_comments_root (root_id),
                INDEX idx_comments_user (user_id),
                INDEX idx_comments_status (status),
                INDEX idx_comments_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS comments");
    }
};
