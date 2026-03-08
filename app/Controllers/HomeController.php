<?php
namespace App\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class HomeController
{
    private Container $container;
    private SettingsService $settings;
    private Database $db;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);
        $this->db = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        // Cache check
        $cacheEnabled = $this->settings->get('cache_home', '0') === '1';
        $cacheTtl     = max(1, (int)$this->settings->get('cache_home_ttl', '10')) * 60;
        $lang         = $this->container->get('lang')->current();
        $cacheKey     = 'home_' . $lang;
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($cacheEnabled && ($cached = $cache->get($cacheKey))) {
            return new Response($cached);
        }

        $canonical = $this->canonical($request);
        $homeCfg = $this->homeConfig();
        $gallery = $homeCfg['show_gallery'] ? $this->latestGallery((int)$homeCfg['gallery_limit']) : [];
        $articles = $homeCfg['show_articles'] ? $this->latestArticles((int)$homeCfg['articles_limit']) : [];
        $sections = [];
        if ($homeCfg['show_gallery']) {
            $sections[] = ['type' => 'gallery', 'order' => $homeCfg['order_gallery']];
        }
        if ($homeCfg['show_articles']) {
            $sections[] = ['type' => 'articles', 'order' => $homeCfg['order_articles']];
        }
        foreach ($this->loadModuleBlocks($this->settings->all()) as $block) {
            $sections[] = ['type' => '__block', 'order' => $block['order'], '_block' => $block];
        }
        usort($sections, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        $locKey = $this->container->get('lang')->current() === 'ru' ? 'ru' : 'en';
        $metaTitle = $this->resolveLocalizedMeta($homeCfg, 'page_title', $locKey, [
            'ru' => 'SteelRoot',
            'en' => 'SteelRoot',
        ]);
        $metaDescription = $this->resolveLocalizedMeta($homeCfg, 'page_description', $locKey, [
            'ru' => 'Лёгкий старт для вашего сайта.',
            'en' => 'Easy start for your site.',
        ]);
        $dynamicCssUrl = $this->buildHomeDynamicCss($homeCfg);
        $html = $this->container->get('renderer')->render('home', [
            '_layout' => true,
            'title' => $metaTitle,
            'home' => $homeCfg,
            'gallery' => $gallery,
            'articles' => $articles,
            'sections' => $sections,
            'locale' => $this->container->get('lang')->current(),
            'galleryMode' => $homeCfg['gallery_style'] ?? $this->settings->get('gallery_open_mode', 'lightbox'),
        ], [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'styles' => $dynamicCssUrl !== null ? [$dynamicCssUrl] : [],
            'og' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'url' => $canonical,
            ],
        ]);
        if ($cacheEnabled) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }
        return new Response($html);
    }

    public function contact(Request $request): Response
    {
        $canonical = $this->canonical($request);
        $fields = $this->loadFields();
        $hasFile = array_reduce($fields, function ($carry, $f) {
            return $carry || (($f['type'] ?? '') === 'file');
        }, false);
        $errors = [];
        $sent = false;
        if ($request->method === 'POST') {
            if (!Csrf::check('contact_form', $request->body['_token'] ?? null)) {
                $errors[] = 'Неверный CSRF токен';
            } else {
                $captchaOk = $this->verifyCaptcha($request);
                if (!$captchaOk) {
                    $errors[] = 'Капча не пройдена';
                }
                $data = [];
                foreach ($fields as $field) {
                    $name = $field['name'];
                    $value = trim($request->body[$name] ?? '');
                    if (($field['type'] ?? '') === 'file') {
                        $fileInfo = $this->handleFileUpload($request, $name, $field, $errors);
                        if ($fileInfo) {
                            $data[$name] = $fileInfo;
                        }
                        continue;
                    }
                    if (!empty($field['required']) && $value === '') {
                        $errors[] = $field['label'] . ' обязательно для заполнения';
                    }
                    $data[$name] = $value;
                }
                if (empty($errors)) {
                    $this->applyContactGuards($data, $errors);
                }
                if (empty($errors)) {
                    $sent = $this->sendMail($data);
                }
            }
        }
        $html = $this->container->get('renderer')->render(
            'contact',
            [
                '_layout' => true,
                'title' => \__('contact'),
                'fields' => $fields,
                'errors' => $errors,
                'sent' => $sent,
                'hasFile' => $hasFile,
                'csrf' => Csrf::token('contact_form'),
            ],
            [
                'title' => \__('contact'),
                'description' => 'Contact page',
                'canonical' => $canonical,
            ]
        );
        return new Response($html);
    }

    private function canonical(Request $request): string
    {
        $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($request->path ?? '/');
    }

    private function resolveLocalizedMeta(array $cfg, string $prefix, string $locKey, array $defaults): string
    {
        $primary = trim((string)($cfg[$prefix . '_' . $locKey] ?? ''));
        if ($primary !== '') {
            return $primary;
        }
        $fallbackKey = $locKey === 'ru' ? 'en' : 'ru';
        $fallback = trim((string)($cfg[$prefix . '_' . $fallbackKey] ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }
        return $defaults[$locKey] ?? ($defaults['en'] ?? '');
    }

    private function loadFields(): array
    {
        $json = $this->settings->get('contact_form_schema', '');
        if ($json) {
            $arr = json_decode($json, true);
            if (is_array($arr)) {
                return $arr;
            }
        }
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ];
    }

    private function handleFileUpload(Request $request, string $fieldName, array $field, array &$errors): ?string
    {
        $file = $request->files[$fieldName] ?? ($_FILES[$fieldName] ?? null);
        if (!$file || empty($file['tmp_name'])) {
            if (!empty($field['required'])) {
                $errors[] = ($field['label'] ?? $fieldName) . ' обязательно для заполнения';
            }
            return null;
        }
        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Ошибка загрузки файла ' . ($field['label'] ?? $fieldName);
            return null;
        }
        $cfg = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if (!empty($file['size']) && $file['size'] > $maxSize) {
            $errors[] = 'Файл слишком большой';
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        if (!isset($allowed[$mime])) {
            $errors[] = 'Недопустимый тип файла';
            return null;
        }
        $dir = APP_ROOT . '/storage/uploads/forms';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safeName = uniqid('f_', true) . '.' . $allowed[$mime];
        $target = $dir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $errors[] = 'Не удалось сохранить файл';
            return null;
        }
        return '/storage/uploads/forms/' . $safeName;
    }

    private function sendMail(array $data): bool
    {
        $to = $this->settings->get('contact_email', '');
        if ($to === '') {
            return false;
        }
        $subject = 'Сообщение с формы';
        $body = '';
        $base = rtrim($this->settings->get('site_url', ''), '/');
        foreach ($data as $k => $v) {
            if (is_string($v) && str_starts_with($v, '/storage/') && $base !== '') {
                $v = $base . $v;
            }
            $body .= ucfirst($k) . ": " . $v . "\n";
        }
        $headers = 'From: ' . ($data['email'] ?? 'noreply@example.com');
        return @mail($to, $subject, $body, $headers);
    }

    private function verifyCaptcha(Request $request): bool
    {
        /** @var \App\Services\CaptchaService $captcha */
        $captcha = $this->container->get(\App\Services\CaptchaService::class);
        $cfg = $captcha->config();
        if ($cfg['provider'] === 'none') {
            return true;
        }
        return $captcha->verify($request);
    }

    private function homeConfig(): array
    {
        $defaults = [
            'page_title' => ['ru' => 'SteelRoot', 'en' => 'SteelRoot'],
            'page_description' => ['ru' => 'Лёгкий старт для вашего сайта.', 'en' => 'Easy start for your site.'],
            'hero_eyebrow' => ['ru' => 'Главная', 'en' => 'Home'],
            'hero_title' => 'SteelRoot',
            'hero_subtitle' => 'Лёгкий старт для вашего сайта.',
            'hero_cta_text' => 'Связаться',
            'hero_cta_url' => '/contact',
            'hero_badge' => '',
            'hero_background' => '',
            'hero_overlay' => 0.4,
            'hero_align' => 'left',
            'show_secondary_cta' => false,
            'secondary_cta_text' => '',
            'secondary_cta_url' => '',
            'layout_mode' => 'wide',
            'section_padding' => 80,
            'gallery_style' => 'lightbox',
            'show_stats' => true,
            'stats_gallery' => ['ru' => 'Галерея', 'en' => 'Gallery'],
            'stats_articles' => ['ru' => 'Статьи', 'en' => 'Articles'],
            'show_gallery' => true,
            'gallery_limit' => 6,
            'gallery_title' => ['ru' => 'Галерея', 'en' => 'Gallery'],
            'gallery_cta' => ['ru' => 'Смотреть все →', 'en' => 'See all →'],
            'show_articles' => true,
            'articles_limit' => 3,
            'articles_title' => ['ru' => 'Статьи', 'en' => 'Articles'],
            'articles_cta' => ['ru' => 'Все статьи →', 'en' => 'All articles →'],
            'show_news' => true,
            'news_limit' => 6,
            'news_title' => ['ru' => 'Новости', 'en' => 'News'],
            'news_cta' => ['ru' => 'Все новости →', 'en' => 'All news →'],
            'order_gallery' => 1,
            'order_articles' => 2,
            'order_news' => 3,
            'custom_blocks' => [],
            'custom_blocks_title' => ['ru' => 'Кастомные блоки', 'en' => 'Custom blocks'],
            'custom_block_cta' => ['ru' => 'Подробнее', 'en' => 'Read more'],
            'custom_css' => '',
        ];
        $settings = $this->settings->all();
        $showGallery = ($settings['home_show_gallery'] ?? '1') === '1';
        $showArticles = ($settings['home_show_articles'] ?? '1') === '1';
        $showNews = ($settings['home_show_news'] ?? '1') === '1';
        $customBlocks = [];
        if (!empty($settings['home_custom_blocks'])) {
            $decoded = json_decode((string)$settings['home_custom_blocks'], true);
            if (is_array($decoded)) {
                $customBlocks = $decoded;
            }
        }
        return [
            'hero_eyebrow_ru' => $settings['home_hero_eyebrow_ru'] ?? $defaults['hero_eyebrow']['ru'],
            'hero_eyebrow_en' => $settings['home_hero_eyebrow_en'] ?? $defaults['hero_eyebrow']['en'],
            'hero_title' => $settings['home_hero_title'] ?? $defaults['hero_title'],
            'hero_subtitle' => $settings['home_hero_subtitle'] ?? $defaults['hero_subtitle'],
            'hero_cta_text' => $settings['home_hero_cta_text'] ?? $defaults['hero_cta_text'],
            'hero_cta_url' => $settings['home_hero_cta_url'] ?? $defaults['hero_cta_url'],
            'hero_badge' => $settings['home_hero_badge'] ?? $defaults['hero_badge'],
            'hero_background' => $settings['home_hero_background'] ?? $defaults['hero_background'],
            'hero_overlay' => (float)($settings['home_hero_overlay'] ?? $defaults['hero_overlay']),
            'hero_align' => $settings['home_hero_align'] ?? $defaults['hero_align'],
            'show_secondary_cta' => ($settings['home_show_secondary_cta'] ?? '0') === '1',
            'secondary_cta_text' => $settings['home_secondary_cta_text'] ?? $defaults['secondary_cta_text'],
            'secondary_cta_url' => $settings['home_secondary_cta_url'] ?? $defaults['secondary_cta_url'],
            'layout_mode' => $settings['home_layout_mode'] ?? $defaults['layout_mode'],
            'section_padding' => (int)($settings['home_section_padding'] ?? $defaults['section_padding']),
            'gallery_style' => $settings['home_gallery_style'] ?? $defaults['gallery_style'],
            'show_stats' => ($settings['home_show_stats'] ?? '1') === '1',
            'stats_gallery_label_ru' => $settings['home_stats_gallery_label_ru'] ?? $defaults['stats_gallery']['ru'],
            'stats_gallery_label_en' => $settings['home_stats_gallery_label_en'] ?? $defaults['stats_gallery']['en'],
            'stats_articles_label_ru' => $settings['home_stats_articles_label_ru'] ?? $defaults['stats_articles']['ru'],
            'stats_articles_label_en' => $settings['home_stats_articles_label_en'] ?? $defaults['stats_articles']['en'],
            'show_gallery' => $showGallery,
            'gallery_limit' => (int)($settings['home_gallery_limit'] ?? $defaults['gallery_limit']),
            'gallery_title_ru' => $settings['home_gallery_title_ru'] ?? $defaults['gallery_title']['ru'],
            'gallery_title_en' => $settings['home_gallery_title_en'] ?? $defaults['gallery_title']['en'],
            'gallery_cta_ru' => $settings['home_gallery_cta_ru'] ?? $defaults['gallery_cta']['ru'],
            'gallery_cta_en' => $settings['home_gallery_cta_en'] ?? $defaults['gallery_cta']['en'],
            'show_articles' => $showArticles,
            'articles_limit' => (int)($settings['home_articles_limit'] ?? $defaults['articles_limit']),
            'articles_title_ru' => $settings['home_articles_title_ru'] ?? $defaults['articles_title']['ru'],
            'articles_title_en' => $settings['home_articles_title_en'] ?? $defaults['articles_title']['en'],
            'articles_cta_ru' => $settings['home_articles_cta_ru'] ?? $defaults['articles_cta']['ru'],
            'articles_cta_en' => $settings['home_articles_cta_en'] ?? $defaults['articles_cta']['en'],
            'show_news' => $showNews,
            'news_limit' => (int)($settings['home_news_limit'] ?? $defaults['news_limit']),
            'news_title_ru' => $settings['home_news_title_ru'] ?? $defaults['news_title']['ru'],
            'news_title_en' => $settings['home_news_title_en'] ?? $defaults['news_title']['en'],
            'news_cta_ru' => $settings['home_news_cta_ru'] ?? $defaults['news_cta']['ru'],
            'news_cta_en' => $settings['home_news_cta_en'] ?? $defaults['news_cta']['en'],
            'order_gallery' => (int)($settings['home_order_gallery'] ?? $defaults['order_gallery']),
            'order_articles' => (int)($settings['home_order_articles'] ?? $defaults['order_articles']),
            'order_news' => (int)($settings['home_order_news'] ?? $defaults['order_news']),
            'custom_blocks' => $customBlocks,
            'custom_blocks_title_ru' => $settings['home_custom_blocks_title_ru'] ?? $defaults['custom_blocks_title']['ru'],
            'custom_blocks_title_en' => $settings['home_custom_blocks_title_en'] ?? $defaults['custom_blocks_title']['en'],
            'custom_block_cta_ru' => $settings['home_custom_block_cta_ru'] ?? $defaults['custom_block_cta']['ru'],
            'custom_block_cta_en' => $settings['home_custom_block_cta_en'] ?? $defaults['custom_block_cta']['en'],
            'custom_css' => $settings['home_custom_css'] ?? $defaults['custom_css'],
            'page_title_ru' => (($v = trim((string)($settings['home_page_title_ru'] ?? ''))) !== '') ? $v : $defaults['page_title']['ru'],
            'page_title_en' => (($v = trim((string)($settings['home_page_title_en'] ?? ''))) !== '') ? $v : $defaults['page_title']['en'],
            'page_description_ru' => (($v = trim((string)($settings['home_page_description_ru'] ?? ''))) !== '') ? $v : $defaults['page_description']['ru'],
            'page_description_en' => (($v = trim((string)($settings['home_page_description_en'] ?? ''))) !== '') ? $v : $defaults['page_description']['en'],
        ];
    }

    private function latestGallery(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $lim = (int)$limit;
        $cols = "id, title_en, title_ru, path_thumb, path_medium";
        if ($this->galleryHasColumn('slug')) {
            $cols .= ", slug";
        }
        if ($this->galleryHasColumn('views')) {
            $cols .= ", views";
        }
        if ($this->galleryHasColumn('likes')) {
            $cols .= ", likes";
        }
        if ($this->galleryHasColumn('category')) {
            $cols .= ", category";
        }
        return $this->db->fetchAll("SELECT {$cols} FROM gallery_items ORDER BY created_at DESC LIMIT {$lim}");
    }

    private function latestArticles(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $lim = (int)$limit;
        $select = "slug, title_en, title_ru, created_at";
        $hasImg = $this->hasColumn('image_url');
        $hasPreview = $this->hasColumn('preview_en');
        $hasViews = $this->hasColumn('views');
        $hasLikes = $this->hasColumn('likes');
        if ($hasImg) {
            $select .= ", image_url";
        }
        if ($hasPreview) {
            $select .= ", preview_en, preview_ru";
        }
        if ($hasViews) {
            $select .= ", views";
        }
        if ($hasLikes) {
            $select .= ", likes";
        }
        return $this->db->fetchAll("SELECT {$select} FROM articles ORDER BY created_at DESC LIMIT {$lim}");
    }

    // Auto-discovers home blocks from modules/{name}/home_block.php files.
    // Each file returns: settings_key, order_key, default_order, provider (callable), view (path).
    private function loadModuleBlocks(array $settings): array
    {
        $blocks = [];
        $pattern = APP_ROOT . '/modules/*/home_block.php';
        foreach ((array)glob($pattern) as $file) {
            try {
                $def = (static fn($f) => include $f)($file);
                if (!is_array($def)) {
                    continue;
                }
                // Check enabled setting
                $settingsKey = $def['settings_key'] ?? null;
                if ($settingsKey && ($settings[$settingsKey] ?? '0') !== '1') {
                    continue;
                }
                // Determine order
                $orderKey = $def['order_key'] ?? null;
                $order = $orderKey
                    ? (int)($settings[$orderKey] ?? $def['default_order'] ?? 99)
                    : (int)($def['default_order'] ?? 99);
                // Fetch data via provider closure
                $data = [];
                if (isset($def['provider']) && is_callable($def['provider'])) {
                    $data = ($def['provider'])($this->db, $settings);
                }
                if (empty($data)) {
                    continue;
                }
                $blocks[] = [
                    'order' => $order,
                    'view'  => $def['view'] ?? null,
                    'data'  => $data,
                ];
            } catch (\Throwable $e) {
                // ignore broken/unavailable blocks (e.g. table not yet created)
            }
        }
        return $blocks;
    }

    private function hasColumn(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM articles LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
    }

    private function galleryHasColumn(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
    }

    private function applyContactGuards(array $data, array &$errors): void
    {
        $settings = $this->settings->all();
        $black = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($settings['contact_blacklist'] ?? ''))));
        if (!empty($black)) {
            foreach ($data as $value) {
                if (!is_string($value)) {
                    continue;
                }
                foreach ($black as $needle) {
                    if ($needle !== '' && stripos($value, $needle) !== false) {
                        $errors[] = 'Сообщение отклонено (blacklist)';
                        break 2;
                    }
                }
            }
        }
        $regex = trim($settings['contact_block_regex'] ?? '');
        if ($regex !== '') {
            foreach ($data as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $pattern = $regex;
                if (@preg_match($pattern, '') === false) {
                    $pattern = '/' . str_replace('/', '\/', $regex) . '/i';
                }
                if (@preg_match($pattern, $value)) {
                    $errors[] = 'Сообщение отклонено (регулярное выражение)';
                    break;
                }
            }
        }
        $domains = array_filter(array_map('strtolower', array_map('trim', preg_split('/\r\n|\r|\n/', (string)($settings['contact_block_domains'] ?? '')))));
        if (!empty($domains) && !empty($data['email'])) {
            $email = strtolower((string)$data['email']);
            $parts = explode('@', $email);
            $domain = $parts[1] ?? '';
            if ($domain !== '') {
                foreach ($domains as $d) {
                    if ($d !== '' && str_ends_with($domain, $d)) {
                        $errors[] = 'Сообщение отклонено (домен в blacklist)';
                        break;
                    }
                }
            }
        }
    }

    private function buildHomeDynamicCss(array $homeCfg): ?string
    {
        $rules = [];
        $sectionPadding = max(0, min(240, (int)($homeCfg['section_padding'] ?? 80)));
        $rules[] = '.home-section-padding{padding-top:' . $sectionPadding . 'px;padding-bottom:' . $sectionPadding . 'px;}';

        $heroRules = [];
        if (!empty($homeCfg['hero_background'])) {
            $safe = preg_replace('/[^#(),.%\-\s\w]/u', '', (string)$homeCfg['hero_background']);
            if ($safe !== '') {
                $heroRules[] = 'background:' . $safe;
            }
        }
        if (isset($homeCfg['hero_overlay'])) {
            $heroRules[] = '--hero-overlay:' . max(0, min(1, (float)$homeCfg['hero_overlay']));
        }
        if (isset($homeCfg['hero_align'])) {
            $safe = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$homeCfg['hero_align']);
            if ($safe !== '') {
                $heroRules[] = '--hero-align:' . $safe;
            }
        }
        if (!empty($heroRules)) {
            $rules[] = '.hero.enhanced{' . implode(';', $heroRules) . ';}';
        }
        if (!empty($homeCfg['custom_css'])) {
            $rules[] = $homeCfg['custom_css'];
        }

        $content  = implode("\n", $rules) . "\n";
        $filePath = APP_ROOT . '/storage/cache/home-dynamic.css';
        if (!is_file($filePath) || file_get_contents($filePath) !== $content) {
            @file_put_contents($filePath, $content, LOCK_EX);
        }
        $version = @filemtime($filePath) ?: time();
        return '/storage/cache/home-dynamic.css?v=' . $version;
    }
}
