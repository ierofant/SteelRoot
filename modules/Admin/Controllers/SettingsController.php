<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Csrf;
use Core\Request;
use Core\Response;
use App\Services\SettingsService;

class SettingsController
{
    private Container $container;
    private Database $db;
    private SettingsService $settingsService;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->settingsService = new SettingsService($this->db);
    }

    public function index(Request $request): Response
    {
        $settings = $this->loadSettings();
        $flash = $_SESSION['admin_settings_flash'] ?? null;
        unset($_SESSION['admin_settings_flash']);
        $html = $this->container->get('renderer')->render('admin/settings', [
            'title' => 'Settings',
            'settings' => $settings,
            'csrf' => Csrf::token('admin_settings'),
            'menuItems' => $this->menuItems($settings),
            'flash' => $flash,
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('admin_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $siteName = trim($request->body['site_name'] ?? 'SteelRoot');
        if ($siteName === '' || strlen($siteName) > 255) {
            return new Response('Invalid site name', 422);
        }
        $theme = $request->body['theme'] ?? 'light';
        $customThemeUrl = trim($request->body['theme_custom_url'] ?? '');
        if (!in_array($theme, ['light','dark','custom'], true)) {
            return new Response('Invalid theme', 422);
        }
        if ($theme === 'custom' && $customThemeUrl === '') {
            return new Response('Custom theme URL required', 422);
        }
        $siteUrl = trim($request->body['site_url'] ?? '');
        $contactEmail = trim($request->body['contact_email'] ?? '');
        $uploadSize = max(1, (int)($request->body['upload_max_mb'] ?? 5)) * 1024 * 1024;
        $uploadMaxW = max(100, (int)($request->body['upload_max_w'] ?? 8000));
        $uploadMaxH = max(100, (int)($request->body['upload_max_h'] ?? 8000));
        $localeMode = $request->body['locale_mode'] ?? 'multi';
        if (!in_array($localeMode, ['ru','en','multi'], true)) {
            return new Response('Invalid locale mode', 422);
        }
        $galleryMode = $request->body['gallery_open_mode'] ?? 'lightbox';
        if (!in_array($galleryMode, ['lightbox','page'], true)) {
            return new Response('Invalid gallery mode', 422);
        }
        $captchaProvider = $request->body['captcha_provider'] ?? 'none';
        $captchaSiteKey = trim($request->body['captcha_site_key'] ?? '');
        $captchaSecretKey = trim($request->body['captcha_secret_key'] ?? '');
        $captchaLogin = isset($request->body['captcha_login_enabled']) ? '1' : '0';
        $sessionPath = trim($request->body['session_path'] ?? '');
        if ($sessionPath === '') {
            $sessionPath = APP_ROOT . '/storage/tmp/sessions';
        }
        $sessionDriver = $request->body['session_driver'] ?? 'files';
        if (!in_array($sessionDriver, ['files', 'redis', 'memcached'], true)) {
            return new Response('Invalid session driver', 422);
        }
        $redisHost = trim($request->body['session_redis_host'] ?? '127.0.0.1');
        $redisPort = (int)($request->body['session_redis_port'] ?? 6379);
        $redisDb = (int)($request->body['session_redis_db'] ?? 0);
        $memcachedServers = trim($request->body['session_memcached_servers'] ?? '');
        $mailDriver = $request->body['mail_driver'] ?? 'smtp';
        if (!in_array($mailDriver, ['smtp','sendmail','php'], true)) {
            return new Response('Invalid mail driver', 422);
        }
        $mailHost = trim($request->body['mail_host'] ?? '');
        $mailPort = (int)($request->body['mail_port'] ?? 587);
        $mailUser = trim($request->body['mail_user'] ?? '');
        $mailPass = trim($request->body['mail_pass'] ?? '');
        $mailSecure = trim($request->body['mail_secure'] ?? 'tls');
        if (!in_array($mailSecure, ['none','ssl','tls'], true)) {
            return new Response('Invalid mail secure', 422);
        }
        $mailFrom = trim($request->body['mail_from'] ?? '');
        $mailFromName = trim($request->body['mail_from_name'] ?? '');
        $mailTemplate = $request->body['mail_template'] ?? '';
        $mailTestTo = trim($request->body['mail_test_to'] ?? '');
        $footerCopy = isset($request->body['footer_copy_enabled']) ? '1' : '0';
        $breadcrumbHome = trim($request->body['breadcrumb_home'] ?? 'Home');
        $breadcrumbsEnabled = isset($request->body['breadcrumbs_enabled']) ? '1' : '0';
        $breadcrumbsCustom = trim($request->body['breadcrumbs_custom'] ?? '');
        if ($breadcrumbsCustom !== '') {
            $decoded = json_decode($breadcrumbsCustom, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return new Response('Invalid JSON for breadcrumbs_custom', 422);
            }
        }
        $adminKey = trim($request->body['admin_guard_key'] ?? '');
        $adminIpRegex = trim($request->body['admin_ip_regex'] ?? '');
        $adminMaxAttempts = max(1, (int)($request->body['admin_max_attempts'] ?? 5));
        $adminLockMinutes = max(1, (int)($request->body['admin_lock_minutes'] ?? 5));
        $menuSchemaRaw = $request->body['menu_schema'] ?? '';
        if (trim($menuSchemaRaw) === '') {
            $menuDecoded = [];
        } else {
            $menuDecoded = json_decode($menuSchemaRaw, true);
            if ($menuDecoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return new Response('Invalid menu schema', 422);
            }
            if (!is_array($menuDecoded)) {
                return new Response('Invalid menu schema', 422);
            }
        }
        $menuNormalized = [];
        foreach ($menuDecoded as $item) {
            $url = trim($item['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $menuNormalized[] = [
                'label_ru' => trim($item['label_ru'] ?? ''),
                'label_en' => trim($item['label_en'] ?? ''),
                'url' => $url,
                'enabled' => !empty($item['enabled']),
                'requires_admin' => !empty($item['requires_admin']),
            ];
        }
        $this->settingsService->bulkSet([
            'site_name' => $siteName,
            'theme' => $theme,
            'theme_custom_url' => $customThemeUrl,
            'site_url' => $siteUrl,
            'contact_email' => $contactEmail,
            'upload_max_bytes' => (string)$uploadSize,
            'upload_max_width' => (string)$uploadMaxW,
            'upload_max_height' => (string)$uploadMaxH,
            'locale_mode' => $localeMode,
            'gallery_open_mode' => $galleryMode,
            'captcha_provider' => $captchaProvider,
            'captcha_site_key' => $captchaSiteKey,
            'captcha_secret_key' => $captchaSecretKey,
            'captcha_login_enabled' => $captchaLogin,
            'session_path' => $sessionPath,
            'session_driver' => $sessionDriver,
            'session_redis_host' => $redisHost,
            'session_redis_port' => (string)$redisPort,
            'session_redis_db' => (string)$redisDb,
            'session_memcached_servers' => $memcachedServers,
            'mail_driver' => $mailDriver,
            'mail_host' => $mailHost,
            'mail_port' => (string)$mailPort,
            'mail_user' => $mailUser,
            'mail_pass' => $mailPass,
            'mail_secure' => $mailSecure,
            'mail_from' => $mailFrom,
            'mail_from_name' => $mailFromName,
            'mail_template' => $mailTemplate,
            'footer_copy_enabled' => $footerCopy,
            'breadcrumb_home' => $breadcrumbHome,
            'breadcrumbs_custom' => $breadcrumbsCustom,
            'breadcrumbs_enabled' => $breadcrumbsEnabled,
            'admin_guard_key' => $adminKey,
            'admin_ip_regex' => $adminIpRegex,
            'admin_max_attempts' => (string)$adminMaxAttempts,
            'admin_lock_minutes' => (string)$adminLockMinutes,
            'menu_schema' => json_encode($menuNormalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
        if ($mailTestTo !== '') {
            try {
                $mailer = new \App\Services\TestMailer($this->container);
                $mailer->sendTest($mailTestTo, $mailTemplate ?: 'Test email from SteelRoot');
                $_SESSION['admin_settings_flash'] = 'Test email sent to ' . $mailTestTo;
            } catch (\Throwable $e) {
                $_SESSION['admin_settings_flash'] = 'Test email failed: ' . $e->getMessage();
            }
        } else {
            $_SESSION['admin_settings_flash'] = 'Settings saved';
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/settings']);
    }

    private function loadSettings(): array
    {
        return $this->settingsService->all();
    }

    private function menuItems(array $settings): array
    {
        $raw = $settings['menu_schema'] ?? '';
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
        return [
            ['label_ru' => 'Главная', 'label_en' => 'Home', 'url' => '/', 'enabled' => true, 'requires_admin' => false],
            ['label_ru' => 'Контакты', 'label_en' => 'Contact', 'url' => '/contact', 'enabled' => true, 'requires_admin' => false],
            ['label_ru' => 'Статьи', 'label_en' => 'Articles', 'url' => '/articles', 'enabled' => true, 'requires_admin' => false],
            ['label_ru' => 'Галерея', 'label_en' => 'Gallery', 'url' => '/gallery', 'enabled' => true, 'requires_admin' => false],
            ['label_ru' => 'Поиск', 'label_en' => 'Search', 'url' => '/search', 'enabled' => true, 'requires_admin' => false],
            ['label_ru' => 'Админ', 'label_en' => 'Admin', 'url' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin'), 'enabled' => true, 'requires_admin' => true],
        ];
    }
}
