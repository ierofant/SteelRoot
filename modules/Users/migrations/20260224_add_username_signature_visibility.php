<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE users ADD COLUMN username VARCHAR(64) NULL AFTER name");
        $db->execute("ALTER TABLE users ADD COLUMN profile_visibility ENUM('public','private') NOT NULL DEFAULT 'public' AFTER status");
        $db->execute("ALTER TABLE users ADD COLUMN signature VARCHAR(300) NULL AFTER avatar");

        $this->backfillUsernames($db);

        $db->execute("ALTER TABLE users MODIFY username VARCHAR(64) NOT NULL");
        $db->execute("CREATE UNIQUE INDEX idx_users_username ON users (username)");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE users DROP INDEX idx_users_username");
        $db->execute("ALTER TABLE users DROP COLUMN signature");
        $db->execute("ALTER TABLE users DROP COLUMN profile_visibility");
        $db->execute("ALTER TABLE users DROP COLUMN username");
    }

    private function backfillUsernames(\Core\Database $db): void
    {
        $existing = $db->fetchAll("SELECT id, name, email FROM users");
        $used = [];
        foreach ($existing as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $base = $this->slugify((string)($row['name'] ?? ''));
            if ($base === '') {
                $email = (string)($row['email'] ?? '');
                $local = $email !== '' ? strstr($email, '@', true) : '';
                $base = $this->slugify($local ?: ('user' . $id));
            }
            if ($base !== '' && ctype_digit($base)) {
                $base = 'u' . $base;
            }
            if ($base === '') {
                $base = 'user' . $id;
            }
            $username = $base;
            $suffix = 1;
            while (in_array($username, $used, true)) {
                $username = $base . $suffix;
                $suffix++;
            }
            $used[] = $username;
            $db->execute("UPDATE users SET username = :u WHERE id = :id", [
                ':u' => $username,
                ':id' => $id,
            ]);
        }
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\\.\\-]+/', '-', $value);
        $value = trim($value, '-_.');
        if (strlen($value) > 48) {
            $value = substr($value, 0, 48);
        }
        return $value;
    }
};
