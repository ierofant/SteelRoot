<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_collection_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                collection_id INT NOT NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id INT NOT NULL,
                note VARCHAR(500) NULL,
                position INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_user_collection_items_collection
                    FOREIGN KEY (collection_id) REFERENCES user_collections(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_user_collection_item (collection_id, entity_type, entity_id),
                INDEX idx_user_collection_items_position (collection_id, position, id),
                INDEX idx_user_collection_items_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_collection_items");
    }
};
