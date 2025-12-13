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
        // Merge module-based popup settings (Popups module) if present
        try {
            if (class_exists(\Core\ModuleSettings::class)) {
                $ms = new \Core\ModuleSettings($this->db);
                $pop = $ms->all('popups');
                if (!empty($pop)) {
                    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
                    $allowed = is_array($pop['adult_pages'] ?? null) ? $pop['adult_pages'] : [];
                    $allowed = array_map(function ($p) {
                        $p = trim($p);
                        return $p === '' ? '/' : $p;
                    }, $allowed);
                    $enabled = !empty($pop['adult_enabled']) && (empty($allowed) || in_array($path, $allowed, true));
                    self::$cache['popup_enabled'] = $enabled ? '1' : '0';
                    self::$cache['popup_delay'] = isset($pop['adult_delay']) ? (int)ceil(((int)$pop['adult_delay']) / 1000) : 5;
                    self::$cache['popup_title'] = $pop['adult_text_ru'] ?? '';
                    self::$cache['popup_content'] = $pop['adult_text_en'] ?? '';
                    // старые CTA не используются в модульных попапах — обнуляем, чтобы не всплывал legacy
                    self::$cache['popup_cta_text'] = '';
                    self::$cache['popup_cta_url'] = '';

                    // Cookie popup mapping for layout
                    self::$cache['popups_cookie_enabled'] = !empty($pop['cookie_enabled']) ? '1' : '0';
                    self::$cache['popups_cookie_text'] = $pop['cookie_text_ru'] ?? '';
                    self::$cache['popups_cookie_button'] = $pop['cookie_button_text'] ?? 'OK';
                    self::$cache['popups_cookie_position'] = $pop['cookie_position'] ?? 'bottom-right';
                    self::$cache['popups_cookie_store'] = $pop['cookie_store'] ?? 'local';
                    self::$cache['popups_cookie_key'] = $pop['cookie_key'] ?? 'cookie_policy_accepted';
                }
            }
        } catch (\Throwable $e) {
            // ignore merge errors
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
