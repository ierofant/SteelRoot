<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'author_id'");
        if (!$exists) {
            $db->execute("ALTER TABLE articles ADD COLUMN author_id INT NULL DEFAULT NULL AFTER id");
            $db->execute("ALTER TABLE articles ADD INDEX idx_articles_author (author_id)");
        }
    }

    public function down(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = 'author_id'");
        if ($exists) {
            $db->execute("ALTER TABLE articles DROP COLUMN author_id");
        }
    }
};
