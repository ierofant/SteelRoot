<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM video_items LIKE 'category_id'");
        if (empty($columns)) {
            $db->execute("ALTER TABLE video_items ADD COLUMN category_id INT NULL AFTER duration");
            $db->execute("ALTER TABLE video_items ADD INDEX idx_video_category (category_id)");
        }
    }

    public function down(\Core\Database $db): void
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM video_items LIKE 'category_id'");
        if (!empty($columns)) {
            $db->execute("ALTER TABLE video_items DROP COLUMN category_id");
        }
    }
};
