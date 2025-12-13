<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\ModuleManager;
use Core\ModuleMigrationRunner;
use Core\Request;
use Core\Response;

class ModulesController
{
    private Container $container;
    private ModuleManager $manager;
    private string $csrfContext = 'admin_modules';

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->manager = $container->get(ModuleManager::class);
    }

    public function index(Request $request): Response
    {
        $this->manager->discover();
        $modules = $this->manager->list();
        $statuses = $this->migrationStatuses($modules);
        $html = $this->container->get('renderer')->render('admin/modules', [
            'title' => 'Modules',
            'modules' => $modules,
            'csrf' => Csrf::token($this->csrfContext),
            'flash' => $this->flash(),
            'statuses' => $statuses,
        ]);
        return new Response($html);
    }

    public function enable(Request $request): Response
    {
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $this->manager->discover();
        $this->manager->enable($slug);
        $this->manager->registerEnabled();
        $this->flash("Module {$slug} enabled");
        return $this->redirect();
    }

    public function disable(Request $request): Response
    {
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $this->manager->discover();
        $this->manager->disable($slug);
        $this->flash("Module {$slug} disabled");
        return $this->redirect();
    }

    public function migrate(Request $request): Response
    {
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $this->manager->discover();
        $log = $this->manager->migrate($slug);
        $this->flash("Migrations for {$slug}: " . implode('; ', $log));
        return $this->redirect();
    }

    public function rollback(Request $request): Response
    {
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $steps = (int)($request->body['steps'] ?? 1);
        $this->manager->discover();
        $log = $this->manager->rollback($slug, max(1, $steps));
        $this->flash("Rollback for {$slug}: " . implode('; ', $log));
        return $this->redirect();
    }

    public function upload(Request $request): Response
    {
        if ($request->method === 'GET') {
            $html = $this->container->get('renderer')->render('admin/modules_upload', [
                'title' => 'Upload Module',
                'csrf' => Csrf::token($this->csrfContext),
                'flash' => $this->flash(),
            ]);
            return new Response($html);
        }
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        if (empty($request->files['archive']['tmp_name'])) {
            $this->flash('No archive uploaded');
            return $this->redirectUpload();
        }
        $tmp = $request->files['archive']['tmp_name'];
        $result = $this->manager->installFromZip($tmp);
        $this->flash($result['message'] ?? 'Done');
        return $this->redirect();
    }

    public function deleteConfirm(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $this->manager->discover();
        $module = $this->manager->get($slug);
        if (!$module) {
            return new Response('Module not found', 404);
        }
        $html = $this->container->get('renderer')->render('admin/modules_delete', [
            'title' => 'Delete Module',
            'module' => $module,
            'csrf' => Csrf::token($this->csrfContext),
        ]);
        return new Response($html);
    }

    public function delete(Request $request): Response
    {
        if (!$this->checkCsrf($request)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $this->manager->discover();
        $ok = $this->manager->remove($slug);
        $this->flash($ok ? "Module {$slug} removed" : "Module {$slug} not found");
        return $this->redirect();
    }

    private function migrationStatuses(array $modules): array
    {
        $statuses = [];
        foreach ($modules as $slug => $module) {
            $path = $module['migrations_path'] ?? null;
            if (!$path) {
                $statuses[$slug] = ['n/a'];
                continue;
            }
            try {
                $db = $this->container->get(Database::class);
                $runner = new ModuleMigrationRunner($path, $db, $slug);
                $statuses[$slug] = $runner->status();
            } catch (\Throwable $e) {
                $statuses[$slug] = ['not available'];
            }
        }
        return $statuses;
    }

    private function flash(?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['admin_modules_flash'] = $message;
            return null;
        }
        if (!empty($_SESSION['admin_modules_flash'])) {
            $msg = $_SESSION['admin_modules_flash'];
            unset($_SESSION['admin_modules_flash']);
            return $msg;
        }
        return null;
    }

    private function checkCsrf(Request $request): bool
    {
        return Csrf::check($this->csrfContext, $request->body['_token'] ?? null);
    }

    private function redirect(): Response
    {
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/modules']);
    }

    private function redirectUpload(): Response
    {
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/modules/upload']);
    }
}
