<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            ALTER TABLE user_profiles
            MODIFY show_comments TINYINT(1) NOT NULL DEFAULT 0
        ");

        $db->execute("
            UPDATE user_profiles
            SET show_comments = 0
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("
            ALTER TABLE user_profiles
            MODIFY show_comments TINYINT(1) NOT NULL DEFAULT 1
        ");
    }
};
