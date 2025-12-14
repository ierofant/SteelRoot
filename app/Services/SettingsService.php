<?php
namespace App\Services;

use Core\Database;

class SettingsService
{
    private Database $db;
    private static array $cache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        if (!empty(self::$cache)) {
            return self::$cache;
        }
        $rows = $this->db->fetchAll("SELECT name, value FROM settings");
        foreach ($rows as $row) {
            self::$cache[$row['name']] = $row['value'];
        }
        return self::$cache;
    }

    public function get(string $name, $default = null)
    {
        $all = $this->all();
        return $all[$name] ?? $default;
    }

    public function set(string $name, string $value): void
    {
        $this->db->execute("
            INSERT INTO settings (name, value) VALUES (:name, :value)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ", [':name' => $name, ':value' => $value]);
        self::$cache = [];
    }

    public function bulkSet(array $pairs): void
    {
        foreach ($pairs as $name => $value) {
            $this->set($name, (string)$value);
        }
    }
}
