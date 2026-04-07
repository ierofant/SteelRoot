<?php
declare(strict_types=1);

return new class {
    public function up(\Core\Database $db): void
    {
        $masterLikesColumn = $db->fetch("SHOW COLUMNS FROM gallery_items LIKE 'master_likes_count'");
        if (!$masterLikesColumn) {
            $db->execute("
                ALTER TABLE gallery_items
                ADD COLUMN master_likes_count INT NOT NULL DEFAULT 0 AFTER likes
            ");
            $db->execute("
                ALTER TABLE gallery_items
                ADD INDEX idx_gallery_master_likes_count (master_likes_count)
            ");
        }

        $db->execute("
            CREATE TABLE IF NOT EXISTS master_gallery_likes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gallery_item_id INT NOT NULL,
                target_user_id INT NOT NULL,
                master_user_id INT NOT NULL,
                status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                revoked_at DATETIME NULL,
                revoked_by_admin_id INT NULL,
                revoke_reason VARCHAR(255) NULL,
                UNIQUE KEY uniq_master_gallery_like (master_user_id, gallery_item_id),
                INDEX idx_mgl_gallery_status (gallery_item_id, status),
                INDEX idx_mgl_target_status (target_user_id, status),
                INDEX idx_mgl_status_created (status, created_at),
                CONSTRAINT fk_mgl_gallery_item FOREIGN KEY (gallery_item_id) REFERENCES gallery_items(id) ON DELETE CASCADE,
                CONSTRAINT fk_mgl_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_mgl_master_user FOREIGN KEY (master_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            UPDATE gallery_items gi
            SET master_likes_count = (
                SELECT COUNT(*)
                FROM master_gallery_likes mgl
                WHERE mgl.gallery_item_id = gi.id
                  AND mgl.status = 'active'
            )
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS master_gallery_likes");

        $masterLikesColumn = $db->fetch("SHOW COLUMNS FROM gallery_items LIKE 'master_likes_count'");
        if ($masterLikesColumn) {
            $index = $db->fetch("SHOW INDEX FROM gallery_items WHERE Key_name = 'idx_gallery_master_likes_count'");
            if ($index) {
                $db->execute("ALTER TABLE gallery_items DROP INDEX idx_gallery_master_likes_count");
            }
            $db->execute("ALTER TABLE gallery_items DROP COLUMN master_likes_count");
        }
    }
};
