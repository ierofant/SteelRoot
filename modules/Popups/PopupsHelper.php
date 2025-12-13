<?php
namespace Modules\Popups;

use Core\ModuleSettings;

class PopupsHelper
{
    private static ?ModuleSettings $settings = null;

    public static function setSettings(ModuleSettings $settings): void
    {
        self::$settings = $settings;
    }

    private static function ms(): ModuleSettings
    {
        if (!self::$settings) {
            throw new \RuntimeException('ModuleSettings not set for PopupsHelper');
        }
        return self::$settings;
    }

    public static function cookieDataAttributes(): array
    {
        $ms = self::ms();
        $enabled = (bool)$ms->get('popups', 'cookie_enabled', false);
        $text = $ms->get('popups', 'cookie_text_ru', '');
        $button = $ms->get('popups', 'cookie_button_text', 'OK');
        $position = $ms->get('popups', 'cookie_position', 'bottom-right');
        $store = $ms->get('popups', 'cookie_store', 'local');
        $key = $ms->get('popups', 'cookie_key', 'cookie_policy_accepted');
        return [
            'enabled' => $enabled,
            'text' => $text,
            'button' => $button,
            'position' => $position,
            'store' => $store,
            'key' => $key,
        ];
    }
}
