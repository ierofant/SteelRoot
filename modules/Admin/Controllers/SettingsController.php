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
        $theme = $request->body['theme'] ?? 'dark';
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
        $captchaGoogleMode = trim((string)($request->body['captcha_google_mode'] ?? 'v2'));
        if (!in_array($captchaGoogleMode, ['v2', 'v3'], true)) {
            return new Response('Invalid Google captcha mode', 422);
        }
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
        $redisPassword = (string)($request->body['session_redis_password'] ?? '');
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
        $mailRegistrationSubject = trim((string)($request->body['mail_registration_subject'] ?? ''));
        $mailRegistrationBody = (string)($request->body['mail_registration_body'] ?? '');
        $mailResetSubject = trim((string)($request->body['mail_reset_subject'] ?? ''));
        $mailResetBody = (string)($request->body['mail_reset_body'] ?? '');
        $mailNotificationSubject = trim((string)($request->body['mail_notification_subject'] ?? ''));
        $mailNotificationBody = (string)($request->body['mail_notification_body'] ?? '');
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
        $urlQueryPolicyEnabled = isset($request->body['url_query_policy_enabled']) ? '1' : '0';
        $urlQueryPolicyMode = trim((string)($request->body['url_query_policy_mode'] ?? 'redirect'));
        if (!in_array($urlQueryPolicyMode, ['redirect', '404', 'ignore'], true)) {
            return new Response('Invalid URL query policy mode', 422);
        }
        $currentSettings = $this->settingsService->all();
        $redisPasswordValue = $redisPassword !== ''
            ? $redisPassword
            : (string)($currentSettings['session_redis_password'] ?? '');
        $urlQueryPolicyMap = [
            'url_query_allow_search' => trim((string)($request->body['url_query_allow_search'] ?? '')),
            'url_query_allow_forum_search' => trim((string)($request->body['url_query_allow_forum_search'] ?? '')),
            'url_query_allow_forum_topic' => trim((string)($request->body['url_query_allow_forum_topic'] ?? '')),
            'url_query_allow_tags_index' => trim((string)($request->body['url_query_allow_tags_index'] ?? '')),
            'url_query_allow_tags_entity' => trim((string)($request->body['url_query_allow_tags_entity'] ?? '')),
            'url_query_allow_tags_gallery' => trim((string)($request->body['url_query_allow_tags_gallery'] ?? '')),
            'url_query_allow_gallery_list' => trim((string)($request->body['url_query_allow_gallery_list'] ?? '')),
            'url_query_allow_articles_list' => trim((string)($request->body['url_query_allow_articles_list'] ?? '')),
            'url_query_allow_profile' => trim((string)($request->body['url_query_allow_profile'] ?? '')),
            'url_query_allow_profile_tab' => trim((string)($request->body['url_query_allow_profile_tab'] ?? '')),
            'url_query_allow_piercing_try_on' => trim((string)($request->body['url_query_allow_piercing_try_on'] ?? '')),
            'url_query_allow_gallery_view' => trim((string)($request->body['url_query_allow_gallery_view'] ?? '')),
            'url_query_allow_gallery_photo' => trim((string)($request->body['url_query_allow_gallery_photo'] ?? '')),
            'url_query_allow_comments_fragment' => trim((string)($request->body['url_query_allow_comments_fragment'] ?? '')),
            'url_query_allow_reset_password' => trim((string)($request->body['url_query_allow_reset_password'] ?? '')),
        ];
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
            'captcha_google_mode' => $captchaGoogleMode,
            'captcha_site_key' => $captchaSiteKey,
            'captcha_secret_key' => $captchaSecretKey,
            'captcha_login_enabled' => $captchaLogin,
            'session_path' => $sessionPath,
            'session_driver' => $sessionDriver,
            'session_redis_host' => $redisHost,
            'session_redis_port' => (string)$redisPort,
            'session_redis_db' => (string)$redisDb,
            'session_redis_password' => $redisPasswordValue,
            'session_memcached_servers' => $memcachedServers,
            'mail_driver' => $mailDriver,
            'mail_host' => $mailHost,
            'mail_port' => (string)$mailPort,
            'mail_user' => $mailUser,
            'mail_pass' => $mailPass,
            'mail_secure' => $mailSecure,
            'mail_from' => $mailFrom,
            'mail_from_name' => $mailFromName,
            'mail_registration_subject' => $mailRegistrationSubject,
            'mail_registration_body' => $mailRegistrationBody,
            'mail_reset_subject' => $mailResetSubject,
            'mail_reset_body' => $mailResetBody,
            'mail_notification_subject' => $mailNotificationSubject,
            'mail_notification_body' => $mailNotificationBody,
            'footer_copy_enabled' => $footerCopy,
            'breadcrumb_home' => $breadcrumbHome,
            'breadcrumbs_custom' => $breadcrumbsCustom,
            'breadcrumbs_enabled' => $breadcrumbsEnabled,
            'admin_guard_key' => $adminKey,
            'admin_ip_regex' => $adminIpRegex,
            'admin_max_attempts' => (string)$adminMaxAttempts,
            'admin_lock_minutes' => (string)$adminLockMinutes,
            'url_query_policy_enabled' => $urlQueryPolicyEnabled,
            'url_query_policy_mode' => $urlQueryPolicyMode,
            'url_query_allow_search' => $urlQueryPolicyMap['url_query_allow_search'],
            'url_query_allow_forum_search' => $urlQueryPolicyMap['url_query_allow_forum_search'],
            'url_query_allow_forum_topic' => $urlQueryPolicyMap['url_query_allow_forum_topic'],
            'url_query_allow_tags_index' => $urlQueryPolicyMap['url_query_allow_tags_index'],
            'url_query_allow_tags_entity' => $urlQueryPolicyMap['url_query_allow_tags_entity'],
            'url_query_allow_tags_gallery' => $urlQueryPolicyMap['url_query_allow_tags_gallery'],
            'url_query_allow_gallery_list' => $urlQueryPolicyMap['url_query_allow_gallery_list'],
            'url_query_allow_articles_list' => $urlQueryPolicyMap['url_query_allow_articles_list'],
            'url_query_allow_profile' => $urlQueryPolicyMap['url_query_allow_profile'],
            'url_query_allow_profile_tab' => $urlQueryPolicyMap['url_query_allow_profile_tab'],
            'url_query_allow_piercing_try_on' => $urlQueryPolicyMap['url_query_allow_piercing_try_on'],
            'url_query_allow_gallery_view' => $urlQueryPolicyMap['url_query_allow_gallery_view'],
            'url_query_allow_gallery_photo' => $urlQueryPolicyMap['url_query_allow_gallery_photo'],
            'url_query_allow_reset_password' => $urlQueryPolicyMap['url_query_allow_reset_password'],
            'url_query_allow_comments_fragment' => $urlQueryPolicyMap['url_query_allow_comments_fragment'],
        ]);
        if ($mailTestTo !== '') {
            if (!filter_var($mailTestTo, FILTER_VALIDATE_EMAIL)) {
                return new Response('Invalid test email', 422);
            }
            try {
                $mailer = new \App\Services\TestMailer($this->container);
                $mailer->sendTest($mailTestTo);
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
}
