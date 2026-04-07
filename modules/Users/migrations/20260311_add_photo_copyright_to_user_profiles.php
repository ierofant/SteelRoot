<?php
declare(strict_types=1);

return new class {
    public function up(\Core\Database $db): void
    {
        $columns = [
            'photo_copyright_enabled' => "ALTER TABLE user_profiles ADD COLUMN photo_copyright_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER cover_image",
            'photo_copyright_text' => "ALTER TABLE user_profiles ADD COLUMN photo_copyright_text VARCHAR(120) NULL AFTER photo_copyright_enabled",
            'photo_copyright_font' => "ALTER TABLE user_profiles ADD COLUMN photo_copyright_font VARCHAR(32) NULL AFTER photo_copyright_text",
            'photo_copyright_color' => "ALTER TABLE user_profiles ADD COLUMN photo_copyright_color VARCHAR(7) NULL AFTER photo_copyright_font",
        ];

        foreach ($columns as $column => $sql) {
            $exists = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE ?", [$column]);
            if (!$exists) {
                $db->execute($sql);
            }
        }
    }

    public function down(\Core\Database $db): void
    {
        foreach (['photo_copyright_color', 'photo_copyright_font', 'photo_copyright_text', 'photo_copyright_enabled'] as $column) {
            $exists = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE ?", [$column]);
            if ($exists) {
                $db->execute("ALTER TABLE user_profiles DROP COLUMN {$column}");
            }
        }
    }
};
