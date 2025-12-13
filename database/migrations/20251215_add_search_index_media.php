<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE search_index ADD COLUMN path_thumb VARCHAR(255) NULL DEFAULT NULL, ADD COLUMN path_medium VARCHAR(255) NULL DEFAULT NULL");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE search_index DROP COLUMN path_thumb, DROP COLUMN path_medium");
    }
};
