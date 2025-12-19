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
        usort($sections, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        $locKey = $this->container->get('lang')->current() === 'ru' ? 'ru' : 'en';
        $metaTitle = $homeCfg['page_title_' . $locKey] ?? ($locKey === 'ru' ? 'SteelRoot' : 'SteelRoot');
        $metaDescription = $homeCfg['page_description_' . $locKey] ?? ($locKey === 'ru' ? 'Лёгкий старт для вашего сайта.' : 'Easy start for your site.');
        $html = $this->container->get('renderer')->render('home', [
            'title' => $metaTitle,
            'home' => $homeCfg,
            'gallery' => $gallery,
            'articles' => $articles,
            'sections' => $sections,
            'locale' => $this->container->get('lang')->current(),
            'galleryMode' => $homeCfg['gallery_style'] ?? $this->settings->get('gallery_open_mode', 'lightbox'),
            'meta' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'canonical' => $canonical,
                'og' => [
                    'title' => $metaTitle,
                    'description' => $metaDescription,
                    'url' => $canonical,
                ],
            ],
        ]);
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
            'order_gallery' => 1,
            'order_articles' => 2,
            'custom_blocks' => [],
            'custom_blocks_title' => ['ru' => 'Кастомные блоки', 'en' => 'Custom blocks'],
            'custom_block_cta' => ['ru' => 'Подробнее', 'en' => 'Read more'],
            'custom_css' => '',
        ];
        $settings = $this->settings->all();
        $showGallery = ($settings['home_show_gallery'] ?? '1') === '1';
        $showArticles = ($settings['home_show_articles'] ?? '1') === '1';
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
            'order_gallery' => (int)($settings['home_order_gallery'] ?? $defaults['order_gallery']),
            'order_articles' => (int)($settings['home_order_articles'] ?? $defaults['order_articles']),
            'custom_blocks' => $customBlocks,
            'custom_blocks_title_ru' => $settings['home_custom_blocks_title_ru'] ?? $defaults['custom_blocks_title']['ru'],
            'custom_blocks_title_en' => $settings['home_custom_blocks_title_en'] ?? $defaults['custom_blocks_title']['en'],
            'custom_block_cta_ru' => $settings['home_custom_block_cta_ru'] ?? $defaults['custom_block_cta']['ru'],
            'custom_block_cta_en' => $settings['home_custom_block_cta_en'] ?? $defaults['custom_block_cta']['en'],
            'custom_css' => $settings['home_custom_css'] ?? $defaults['custom_css'],
            'page_title_ru' => $settings['home_page_title_ru'] ?? $defaults['page_title']['ru'],
            'page_title_en' => $settings['home_page_title_en'] ?? $defaults['page_title']['en'],
            'page_description_ru' => $settings['home_page_description_ru'] ?? $defaults['page_description']['ru'],
            'page_description_en' => $settings['home_page_description_en'] ?? $defaults['page_description']['en'],
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
}
