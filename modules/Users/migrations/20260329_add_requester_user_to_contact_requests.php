<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $table = $db->fetch("SHOW TABLES LIKE 'contact_requests'");
        if (!$table) {
            return;
        }

        $column = $db->fetch("SHOW COLUMNS FROM contact_requests LIKE 'requester_user_id'");
        if (!$column) {
            $db->execute("
                ALTER TABLE contact_requests
                ADD COLUMN requester_user_id INT NULL AFTER master_id,
                ADD KEY idx_contact_requests_requester (requester_user_id, created_at),
                ADD CONSTRAINT fk_contact_requests_requester
                    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(\Core\Database $db): void
    {
        $table = $db->fetch("SHOW TABLES LIKE 'contact_requests'");
        if (!$table) {
            return;
        }

        $column = $db->fetch("SHOW COLUMNS FROM contact_requests LIKE 'requester_user_id'");
        if ($column) {
            $db->execute("ALTER TABLE contact_requests DROP FOREIGN KEY fk_contact_requests_requester");
            $db->execute("ALTER TABLE contact_requests DROP INDEX idx_contact_requests_requester");
            $db->execute("ALTER TABLE contact_requests DROP COLUMN requester_user_id");
        }
    }
};
