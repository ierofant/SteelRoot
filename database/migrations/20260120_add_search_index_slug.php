<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE search_index ADD COLUMN slug VARCHAR(255) DEFAULT ''");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE search_index DROP COLUMN slug");
    }
};
