<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $group = $db->fetch("SELECT id FROM user_groups WHERE slug = 'editor' LIMIT 1");
        if (!$group) {
            return;
        }
        $db->execute("
            INSERT IGNORE INTO user_group_permissions (group_id, permission_key, created_at)
            VALUES (?, 'admin.articles.own', NOW())
        ", [(int)$group['id']]);
    }

    public function down(\Core\Database $db): void
    {
        $group = $db->fetch("SELECT id FROM user_groups WHERE slug = 'editor' LIMIT 1");
        if (!$group) {
            return;
        }
        $db->execute("
            DELETE FROM user_group_permissions
            WHERE group_id = ? AND permission_key = 'admin.articles.own'
        ", [(int)$group['id']]);
    }
};
