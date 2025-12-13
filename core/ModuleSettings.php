<?php
namespace Core;

/**
 * Lightweight settings helper for module-scoped configuration stored in the shared `settings` table.
 */
class ModuleSettings
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(string $module, string $key, $default = null)
    {
        $fullKey = $this->prefix($module, $key);
        $row = $this->db->fetch("SELECT `value` FROM settings WHERE `name` = ?", [$fullKey]);
        if (!$row) {
            return $default;
        }
        return $this->decode($row['value']);
    }

    public function set(string $module, string $key, $value): void
    {
        $fullKey = $this->prefix($module, $key);
        $encoded = $this->encode($value);
        $this->db->execute("INSERT INTO settings (`name`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$fullKey, $encoded]);
    }

    public function loadDefaults(string $module, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            $fullKey = $this->prefix($module, $key);
            $row = $this->db->fetch("SELECT 1 FROM settings WHERE `name` = ?", [$fullKey]);
            if (!$row) {
                $this->set($module, $key, $value);
            }
        }
    }

    public function all(string $module): array
    {
        $like = $this->prefix($module, '') . '%';
        $rows = $this->db->fetchAll("SELECT `name`, `value` FROM settings WHERE `name` LIKE ?", [$like]);
        $out = [];
        foreach ($rows as $row) {
            $short = substr($row['name'], strlen($this->prefix($module, '')));
            $out[$short] = $this->decode($row['value']);
        }
        return $out;
    }

    private function prefix(string $module, string $key): string
    {
        return trim(strtolower($module), '_') . '_' . $key;
    }

    private function encode($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string)$value;
    }

    private function decode($value)
    {
        $val = (string)$value;
        $json = json_decode($val, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }
        if ($val === '1' || $val === '0') {
            return $val === '1';
        }
        return $value;
    }
}
