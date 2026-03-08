<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM video_categories LIKE 'image_url'");
        if (empty($columns)) {
            $db->execute("ALTER TABLE video_categories ADD COLUMN image_url VARCHAR(1000) DEFAULT NULL AFTER name_ru");
        }
    }

    public function down(\Core\Database $db): void
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM video_categories LIKE 'image_url'");
        if (!empty($columns)) {
            $db->execute("ALTER TABLE video_categories DROP COLUMN image_url");
        }
    }
};
