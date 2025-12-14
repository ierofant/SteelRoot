<?php
namespace Modules\Pages\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;

class PagesController
{
    private Container $container;
    private Database $db;
    private \App\Services\EmbedFormService $embedForms;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->embedForms = new \App\Services\EmbedFormService(
            $this->db,
            $container->get(\App\Services\SettingsService::class)
        );
    }

    public function show(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $locale = $this->container->get('lang')->current();
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

        $html = $this->container->get('renderer')->render('frontend/show', [
            'title' => $title,
            'page' => $page,
            'locale' => $locale,
            'meta' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'canonical' => $this->canonical($request),
            ],
        ]);
        return new Response($html);
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
}
