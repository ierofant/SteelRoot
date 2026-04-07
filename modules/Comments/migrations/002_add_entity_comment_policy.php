<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $articlesCommentsMode = $db->fetch("SHOW COLUMNS FROM articles LIKE 'comments_mode'");
        if (!$articlesCommentsMode) {
            $db->execute("ALTER TABLE articles ADD COLUMN comments_mode VARCHAR(16) NOT NULL DEFAULT 'default'");
        }

        $newsTable = $db->fetch("SHOW TABLES LIKE 'news'");
        if ($newsTable) {
            $newsCommentsMode = $db->fetch("SHOW COLUMNS FROM news LIKE 'comments_mode'");
            if (!$newsCommentsMode) {
                $db->execute("ALTER TABLE news ADD COLUMN comments_mode VARCHAR(16) NOT NULL DEFAULT 'default'");
            }
        }

        $groupMapTable = $db->fetch("SHOW TABLES LIKE 'comment_entity_group_map'");
        if (!$groupMapTable) {
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
        $groupMapTable = $db->fetch("SHOW TABLES LIKE 'comment_entity_group_map'");
        if ($groupMapTable) {
            $db->execute("DROP TABLE IF EXISTS comment_entity_group_map");
        }

        $newsTable = $db->fetch("SHOW TABLES LIKE 'news'");
        if ($newsTable) {
            $newsCommentsMode = $db->fetch("SHOW COLUMNS FROM news LIKE 'comments_mode'");
            if ($newsCommentsMode) {
                $db->execute("ALTER TABLE news DROP COLUMN comments_mode");
            }
        }

        $articlesCommentsMode = $db->fetch("SHOW COLUMNS FROM articles LIKE 'comments_mode'");
        if ($articlesCommentsMode) {
            $db->execute("ALTER TABLE articles DROP COLUMN comments_mode");
        }
    }
};
