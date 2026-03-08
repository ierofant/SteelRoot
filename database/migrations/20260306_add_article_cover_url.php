<?php
return [
    'up' => function (\Core\Database $db): void {
        $row = $db->fetch("SHOW COLUMNS FROM articles LIKE 'cover_url'");
        if (!$row) {
            $db->execute("ALTER TABLE articles ADD COLUMN cover_url VARCHAR(512) NULL DEFAULT NULL AFTER image_url");
        }
    },
    'down' => function (\Core\Database $db): void {
        $row = $db->fetch("SHOW COLUMNS FROM articles LIKE 'cover_url'");
        if ($row) {
            $db->execute("ALTER TABLE articles DROP COLUMN cover_url");
        }
    },
];
