<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items ADD COLUMN path_medium VARCHAR(255) NULL AFTER path");
        $db->execute("ALTER TABLE gallery_items ADD COLUMN path_thumb VARCHAR(255) NULL AFTER path_medium");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items DROP COLUMN path_thumb");
        $db->execute("ALTER TABLE gallery_items DROP COLUMN path_medium");
    }
};
