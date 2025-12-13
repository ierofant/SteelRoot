<?php
return new class {
    public function up(\Core\Database $db): void
    {
        // Add category column if missing
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'category'");
        if (!$exists) {
            $db->execute("ALTER TABLE articles ADD COLUMN category VARCHAR(255) NULL AFTER slug");
        }
        $existsImg = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'image_url'");
        if (!$existsImg) {
            $db->execute("ALTER TABLE articles ADD COLUMN image_url VARCHAR(512) NULL AFTER category");
        }
    }

    public function down(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'category'");
        if ($exists) {
            $db->execute("ALTER TABLE articles DROP COLUMN category");
        }
        $existsImg = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'image_url'");
        if ($existsImg) {
            $db->execute("ALTER TABLE articles DROP COLUMN image_url");
        }
    }
};
