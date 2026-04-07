<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'gallery_items' AND COLUMN_NAME = 'author_id'");
        if (!$exists) {
            $db->execute("ALTER TABLE gallery_items ADD COLUMN author_id INT NULL DEFAULT NULL AFTER id");
            $db->execute("ALTER TABLE gallery_items ADD INDEX idx_gallery_author (author_id)");
        }
    }

    public function down(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'gallery_items' AND COLUMN_NAME = 'author_id'");
        if ($exists) {
            $db->execute("ALTER TABLE gallery_items DROP COLUMN author_id");
        }
    }
};
