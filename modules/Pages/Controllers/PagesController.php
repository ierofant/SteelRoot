<?php
namespace Modules\Pages\Controllers;

use App\Services\SettingsService;
use App\Services\TagService;
use Core\Asset;
use Core\Cache;
use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Slot;
use Modules\Comments\Services\CommentService;
use Modules\Comments\Services\EntityCommentPolicyService;

class PagesController
{
    private Container $container;
    private Database $db;
    private \App\Services\EmbedFormService $embedForms;
    private TagService $tags;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->embedForms = new \App\Services\EmbedFormService(
            $this->db,
            $container->get(\App\Services\SettingsService::class)
        );
        $this->tags = new TagService($this->db);
    }

    public function show(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $locale = $this->container->get('lang')->current();
        /** @var SettingsService $settings */
        $settings = $this->container->get(SettingsService::class);
        $cacheEnabled = $settings->get('cache_pages', '0') === '1'
            && empty($_SESSION['user_id'])
            && empty($_SESSION['admin_auth']);
        $cacheTtl = max(1, (int)$settings->get('cache_pages_ttl', '60')) * 60;
        $cacheKey = 'page_' . $slug . '_' . $locale;
        $cacheHeaders = ['X-Page-Cache' => 'BYPASS'];
        /** @var Cache $cache */
        $cache = $this->container->get('cache');

        if ($cacheEnabled && ($cached = $cache->get($cacheKey))) {
            return new Response($cached, 200, ['X-Page-Cache' => 'HIT']);
        }
        if ($cacheEnabled) {
            $cacheHeaders['X-Page-Cache'] = 'MISS';
        }

        $page = $this->db->fetch("SELECT * FROM pages WHERE slug = ? AND visible = 1", [$slug]);
        if (!$page) {
            return new Response('Not found', 404);
        }
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $contentKey = $locale === 'ru' ? 'content_ru' : 'content_en';
        $metaTitleKey = $locale === 'ru' ? 'meta_title_ru' : 'meta_title_en';
        $metaDescriptionKey = $locale === 'ru' ? 'meta_description_ru' : 'meta_description_en';

        $title = $page[$titleKey] ?: $page[$titleKey === 'title_ru' ? 'title_en' : 'title_ru'];
        $metaTitle = $page[$metaTitleKey] ?: $title;
        $metaDescription = $page[$metaDescriptionKey] ?: '';

        $contentKey = $contentKey ?? ($locale === 'ru' ? 'content_ru' : 'content_en');
        $page[$contentKey] = $this->renderEmbeds($page[$contentKey] ?? '', $request, $locale);
        Slot::register('head_end', static fn(): string => Asset::styleTag('/assets/css/pages.css') . "\n");
        $tags = $this->tags->forEntity('page', (int)$page['id']);

        $html = $this->container->get('renderer')->render(
            'frontend/show',
            [
                '_layout' => true,
                'title' => $title,
                'page' => $page,
                'locale' => $locale,
                'tags' => $tags,
                'commentsHtml' => $this->renderComments((int)$page['id']),
                'breadcrumbs' => [
                    ['label' => $title],
                ],
            ],
            [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'canonical' => $this->canonical($request),
            ]
        );

        if ($cacheEnabled) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }

        return new Response($html, 200, $cacheHeaders);
    }

    private function canonical(Request $request): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        $uri = $request->path ?? '/';
        return $base . $uri;
    }

    private function renderEmbeds(string $content, Request $request, string $locale): string
    {
        $state = [];
        if (($request->method ?? '') === 'POST' && !empty($request->body['_embed_form'])) {
            $slug = $request->body['_embed_form'];
            $state[$slug] = $this->embedForms->handle($slug, $locale, $request->body);
        }
        return preg_replace_callback('/\{\{\s*form:([a-z0-9\-]+)\s*\}\}/i', function ($m) use ($state, $locale) {
            $slug = $m[1];
            $formState = $state[$slug] ?? [];
            return $this->embedForms->render($slug, $locale, $formState);
        }, $content);
    }

    private function renderComments(int $pageId): string
    {
        try {
            $policy = $this->container->get(EntityCommentPolicyService::class)->load('page', $pageId);
            if (($policy['mode'] ?? 'default') === 'disabled') {
                return '';
            }
            return $this->container->get(CommentService::class)->renderForEntity('page', $pageId);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
