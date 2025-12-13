<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;

class DocsController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request): Response
    {
        $tab = $request->query['tab'] ?? 'user';
        $tab = $tab === 'dev' ? 'dev' : 'user';
        $html = $this->container->get('renderer')->render('admin/docs', [
            'title' => __('docs.title'),
            'tab' => $tab,
        ]);
        return new Response($html);
    }

    public function support(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('admin/docs_support', [
            'title' => __('docs.support.title'),
        ]);
        return new Response($html);
    }
}
