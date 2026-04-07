<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $articleCommentsMode = $db->fetch("SHOW COLUMNS FROM articles LIKE 'comments_mode'");
        if (!$articleCommentsMode) {
            $db->execute("ALTER TABLE articles ADD COLUMN comments_mode VARCHAR(16) NOT NULL DEFAULT 'default'");
        }

        $table = $db->fetch("SHOW TABLES LIKE 'comment_entity_group_map'");
        if (!$table) {
            $db->execute("
                CREATE TABLE IF NOT EXISTS comment_entity_group_map (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    entity_type VARCHAR(32) NOT NULL,
                    entity_id INT NOT NULL,
                    group_id INT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_comment_entity_group (entity_type, entity_id, group_id),
                    KEY idx_comment_entity (entity_type, entity_id),
                    KEY idx_comment_group (group_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(\Core\Database $db): void
    {
        $table = $db->fetch("SHOW TABLES LIKE 'comment_entity_group_map'");
        if ($table) {
            $db->execute("DROP TABLE IF EXISTS comment_entity_group_map");
        }

        $articleCommentsMode = $db->fetch("SHOW COLUMNS FROM articles LIKE 'comments_mode'");
        if ($articleCommentsMode) {
            $db->execute("ALTER TABLE articles DROP COLUMN comments_mode");
        }
    }
};
