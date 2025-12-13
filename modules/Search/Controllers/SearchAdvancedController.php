<?php
namespace Modules\Search\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Search\SearchRegistry;

class SearchAdvancedController
{
    private SearchRegistry $registry;
    private \Core\Renderer $renderer;

    public function __construct(Container $container)
    {
        $this->registry = $container->get(SearchRegistry::class);
        $this->renderer = $container->get('renderer');
    }

    public function index(Request $request): Response
    {
        $providers = $this->registry->getProviders();
        $html = $this->renderer->render('search/filters', [
            'providers' => $providers,
        ]);
        return new Response($html);
    }

    public function apply(Request $request): Response
    {
        $q = trim($request->body['q'] ?? $request->query['q'] ?? '');
        $providerKey = trim($request->body['provider'] ?? $request->query['provider'] ?? '');
        $filters = $request->body['filters'] ?? [];
        $requested = $request->body['providers'] ?? [];
        $requested = is_array($requested) ? array_filter(array_map('strval', $requested)) : [];
        $results = [];
        if ($q !== '') {
            if ($providerKey !== '') {
                $provider = $this->registry->get($providerKey);
                if ($provider) {
                    $results = $provider->search($q, is_array($filters) ? $filters : []);
                }
            } else {
                $keys = $requested ?: array_keys($this->registry->getProviders());
                foreach ($keys as $k) {
                    $p = $this->registry->get($k);
                    if ($p) {
                        $results = array_merge($results, $p->search($q, is_array($filters) ? $filters : []));
                    }
                }
            }
        }
        return Response::json([
            'query' => $q,
            'provider' => $providerKey,
            'providers' => $requested,
            'results' => $results,
        ]);
    }
}
