<?php
namespace Modules\Menu\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Request;
use Core\Response;
use Modules\Menu\Services\MenuService;

class MenuController
{
    private MenuService $menu;
    private string $adminPrefix;

    public function __construct(Container $container)
    {
        $db = $container->get(Database::class);
        $this->menu = new MenuService($db);
        $cfg = $container->get('config');
        $this->adminPrefix = $cfg['admin_prefix'] ?? '/admin';
    }

    public function index(Request $request): Response
    {
        $items = $this->menu->all();
        $flash = $request->query['msg'] ?? null;
        $csrf = Csrf::token('menu_admin');
        $content = $this->render('admin/index', [
            'items' => $items,
            'csrf' => $csrf,
            'flash' => $flash,
            'adminPrefix' => $this->adminPrefix,
        ]);
        return new Response($content);
    }

    public function create(Request $request): Response
    {
        $csrf = Csrf::token('menu_admin');
        $content = $this->render('admin/form', [
            'csrf' => $csrf,
            'item' => [
                'enabled' => 1,
                'admin_only' => 0,
                'position' => 0,
            ],
            'action' => $this->adminPrefix . '/menu/create',
            'title' => __('menu.create'),
            'adminPrefix' => $this->adminPrefix,
        ]);
        return new Response($content);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->menu->find($id);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $csrf = Csrf::token('menu_admin');
        $content = $this->render('admin/form', [
            'csrf' => $csrf,
            'item' => $item,
            'action' => $this->adminPrefix . '/menu/edit/' . $id,
            'title' => __('menu.edit'),
            'adminPrefix' => $this->adminPrefix,
        ]);
        return new Response($content);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        return $this->persist($request, $id);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('menu_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->menu->delete($id);
        return new Response('', 302, ['Location' => $this->adminPrefix . '/menu?msg=deleted']);
    }

    public function toggle(Request $request): Response
    {
        if (!Csrf::check('menu_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->menu->toggle($id);
        return new Response('', 302, ['Location' => $this->adminPrefix . '/menu?msg=toggled']);
    }

    public function reorder(Request $request): Response
    {
        if (!Csrf::check('menu_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $positions = $request->body['positions'] ?? [];
        if (is_array($positions)) {
            $this->menu->reorder($positions);
        }
        return new Response('', 302, ['Location' => $this->adminPrefix . '/menu?msg=reordered']);
    }

    private function persist(Request $request, ?int $id): Response
    {
        if (!Csrf::check('menu_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $existing = $id ? $this->menu->find($id) : null;
        $data = [
            'label_ru' => trim($request->body['label_ru'] ?? ''),
            'label_en' => trim($request->body['label_en'] ?? ''),
            'url' => trim($request->body['url'] ?? ''),
            'enabled' => isset($request->body['enabled']) ? 1 : 0,
            'admin_only' => isset($request->body['admin_only']) ? 1 : 0,
            'title_ru' => trim($request->body['title_ru'] ?? ''),
            'title_en' => trim($request->body['title_en'] ?? ''),
            'description_ru' => trim($request->body['description_ru'] ?? ''),
            'description_en' => trim($request->body['description_en'] ?? ''),
            'position' => (int)($request->body['position'] ?? 0),
            'canonical_url' => trim($request->body['canonical_url'] ?? ''),
            'image_url' => trim($request->body['image_url'] ?? ''),
        ];
        if ($data['label_ru'] === '' || $data['label_en'] === '') {
            return new Response('Labels required', 422);
        }
        if ($data['url'] === '' || $data['url'][0] !== '/') {
            return new Response('URL must start with /', 422);
        }
        if ($data['position'] === 0) {
            $data['position'] = $this->nextPosition();
        }
        $upload = $this->handleUpload($request);
        if ($upload['error']) {
            return new Response($upload['error'], 400);
        }
        if ($upload['path'] !== null) {
            $data['image_url'] = $upload['path'];
        } elseif ($data['image_url'] === '' && $existing && !empty($existing['image_url'])) {
            $data['image_url'] = '';
        }
        $this->menu->save($data, $id);
        $msg = $id === null ? 'created' : 'updated';
        return new Response('', 302, ['Location' => $this->adminPrefix . '/menu?msg=' . $msg]);
    }

    private function nextPosition(): int
    {
        $row = $this->menu->all();
        if (empty($row)) {
            return 1;
        }
        $max = 0;
        foreach ($row as $item) {
            $max = max($max, (int)($item['position'] ?? 0));
        }
        return $max + 1;
    }

    private function render(string $view, array $data): string
    {
        ob_start();
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../views/' . $view . '.php';
        $content = ob_get_clean();
        $title = $data['title'] ?? 'Menu';
        $showSidebar = true;
        ob_start();
        include APP_ROOT . '/modules/Admin/views/layout.php';
        return ob_get_clean();
    }

    private function handleUpload(Request $request): array
    {
        $file = $request->files['image_file'] ?? ($_FILES['image_file'] ?? null);
        if (!$file || empty($file['tmp_name'])) {
            return ['path' => null, 'error' => null];
        }
        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Upload failed'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['path' => null, 'error' => 'Invalid upload'];
        }
        $max = 2 * 1024 * 1024;
        if (!empty($file['size']) && $file['size'] > $max) {
            return ['path' => null, 'error' => 'File too large'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($map[$mime])) {
            return ['path' => null, 'error' => 'Unsupported image type'];
        }
        $dir = APP_ROOT . '/storage/uploads/menu';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = 'menu_' . uniqid('', true) . '.' . $map[$mime];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['path' => null, 'error' => 'Cannot save file'];
        }
        $webPath = '/storage/uploads/menu/' . $name;
        return ['path' => $webPath, 'error' => null];
    }
}
