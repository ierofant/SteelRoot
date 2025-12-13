<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;
use App\Services\SearchIndexService;

class SearchSettingsController
{
    private Container $container;
    private SettingsService $settings;
    private SearchIndexService $indexService;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);
        $this->indexService = $container->get(SearchIndexService::class);
    }

    public function index(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('admin/search_settings', [
            'title' => 'Search settings',
            'csrf' => Csrf::token('search_settings'),
            'settings' => $this->settings->all(),
            'message' => !empty($request->query['saved']) ? 'Сохранено' : null,
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('search_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $rlSearch = max(1, (int)($request->body['rl_search'] ?? 30));
        $rlAuto = max(1, (int)($request->body['rl_autocomplete'] ?? 60));
        $searchTtl = max(0, (int)($request->body['search_cache_ttl'] ?? 10));
        $searchMax = max(1, (int)($request->body['search_max_results'] ?? 20));
        $searchArticles = isset($request->body['search_include_articles']) ? '1' : '0';
        $searchGallery = isset($request->body['search_include_gallery']) ? '1' : '0';
        $searchTags = isset($request->body['search_include_tags']) ? '1' : '0';
        $this->settings->bulkSet([
            'rl_search' => (string)$rlSearch,
            'rl_autocomplete' => (string)$rlAuto,
            'search_cache_ttl' => (string)$searchTtl,
            'search_max_results' => (string)$searchMax,
            'search_include_articles' => $searchArticles,
            'search_include_gallery' => $searchGallery,
            'search_include_tags' => $searchTags,
        ]);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/search?saved=1']);
    }

    public function rebuild(Request $request): Response
    {
        if (!Csrf::check('search_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        try {
            $this->indexService->rebuildAll();
            $cache = $this->container->get('cache');
            $cache->clear();
            $msg = 'Search index rebuilt';
        } catch (\Throwable $e) {
            $msg = 'Rebuild failed: ' . $e->getMessage();
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/search?saved=1&msg=' . rawurlencode($msg)]);
    }
}
