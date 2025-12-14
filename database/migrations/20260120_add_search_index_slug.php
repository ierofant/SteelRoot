<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'search_index' AND COLUMN_NAME = 'slug'");
        if (!$exists) {
            $db->execute("ALTER TABLE search_index ADD COLUMN slug VARCHAR(255) DEFAULT ''");
        }
    }

    public function down(\Core\Database $db): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'search_index' AND COLUMN_NAME = 'slug'");
        if ($exists) {
            $db->execute("ALTER TABLE search_index DROP COLUMN slug");
        }
    }
};
