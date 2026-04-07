<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $exists = $db->fetch("SHOW COLUMNS FROM pages LIKE 'comments_mode'");
        if (!$exists) {
            $db->execute("ALTER TABLE pages ADD COLUMN comments_mode VARCHAR(16) NOT NULL DEFAULT 'default'");
        }
    }

    public function down(\Core\Database $db): void
    {
        $exists = $db->fetch("SHOW COLUMNS FROM pages LIKE 'comments_mode'");
        if ($exists) {
            $db->execute("ALTER TABLE pages DROP COLUMN comments_mode");
        }
    }
};
