<?php
namespace Modules\Popups;

use Core\Container;
use Core\ModuleSettings;
use Core\Router;

class Module
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register(Container $container, Router $router): void
    {
        $db = $container->get(\Core\Database::class);
        $ms = $container->get(ModuleSettings::class);
        PopupsHelper::setSettings($ms);
        $defaults = require $this->path . '/config.php';
        $ms->loadDefaults('popups', $defaults);
        // expose module settings back to legacy globals for existing popup script
        $map = $ms->all('popups');
        $globals = &$GLOBALS['settingsAll'];
        $globals['popup_enabled'] = !empty($map['adult_enabled']) ? '1' : '0';
        $globals['popup_delay'] = isset($map['adult_delay']) ? (int)ceil(((int)$map['adult_delay']) / 1000) : 5;
        $globals['popup_title'] = $map['adult_text_ru'] ?? '';
        $globals['popup_content'] = $map['adult_text_en'] ?? '';
        $globals['popup_cta_text'] = $globals['popup_cta_text'] ?? '';
        $globals['popup_cta_url'] = $globals['popup_cta_url'] ?? '';
    }
}
