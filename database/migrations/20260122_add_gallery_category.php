<?php
use Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items ADD COLUMN category VARCHAR(120) NULL AFTER slug");
        $db->execute("CREATE INDEX IF NOT EXISTS idx_gallery_category ON gallery_items (category)");
    }

    public function down(Database $db): void
    {
        // Not all DBs support IF EXISTS; wrap in try
        try {
            $db->execute("ALTER TABLE gallery_items DROP COLUMN category");
        } catch (\Throwable $e) {}
    }
};
