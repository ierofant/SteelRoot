<?php
namespace Modules\Templates\Controllers;

use App\Services\SettingsService;
use Core\Config;
use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use ZipArchive;

class TemplatesController
{
    private Container $container;
    private SettingsService $settings;
    private Config $config;
    private string $templatesRoot;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);
        $this->config = $container->get('config');
        $this->templatesRoot = APP_ROOT . '/resources/views/templates';
    }

    public function index(Request $request): Response
    {
        $theme = $this->settings->get('theme', 'light');
        $current = $this->currentTemplateName($theme);
        $templates = $this->availableTemplates();
        $message = $request->query['msg'] ?? null;
        $error = $request->query['error'] ?? null;
        $saved = !empty($request->query['saved']);
        $adminPrefix = $this->config['admin_prefix'] ?? '/admin';
        $html = $this->container->get('renderer')->render('templates/admin/index', [
            'title' => 'Templates',
            'theme' => $theme,
            'currentTemplate' => $current,
            'templates' => $templates,
            'csrf' => Csrf::token('templates_admin'),
            'saved' => $saved,
            'message' => $message,
            'error' => $error,
            'adminPrefix' => $adminPrefix,
        ]);
        return new Response($html);
    }

    public function select(Request $request): Response
    {
        if (!Csrf::check('templates_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $theme = $this->settings->get('theme', 'light');
        $name = $this->normalizeTemplateName((string)($request->body['template'] ?? ''));
        if ($name === '') {
            return $this->redirectWithError('Invalid template name');
        }
        if ($name !== 'default') {
            $available = array_column($this->availableTemplates(), 'name');
            if (!in_array($name, $available, true)) {
                return $this->redirectWithError('Template not found');
            }
        }
        $this->setCurrentTemplate($theme, $name);
        return $this->redirectWithSaved();
    }

    public function upload(Request $request): Response
    {
        if (!Csrf::check('templates_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $file = $_FILES['template_zip'] ?? null;
        if (!$file || empty($file['tmp_name'])) {
            return $this->redirectWithError('Upload failed');
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $this->redirectWithError('Only .zip archives are supported');
        }
        $tmpZip = sys_get_temp_dir() . '/template_' . uniqid('', true) . '.zip';
        if (!@move_uploaded_file($file['tmp_name'], $tmpZip)) {
            return $this->redirectWithError('Upload failed');
        }
        $result = $this->installFromZip($tmpZip);
        @unlink($tmpZip);
        if (!$result['ok']) {
            return $this->redirectWithError($result['message']);
        }
        if (!empty($request->body['activate'])) {
            $theme = $this->settings->get('theme', 'light');
            $this->setCurrentTemplate($theme, $result['template']);
        }
        return $this->redirectWithSaved(['msg' => 'Template uploaded']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('templates_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $theme = $this->settings->get('theme', 'light');
        $name = $this->normalizeTemplateName((string)($request->body['template'] ?? ''));
        if ($name === '' || $name === 'default') {
            return $this->redirectWithError('Invalid template name');
        }
        if ($name === $this->currentTemplateName($theme)) {
            $this->setCurrentTemplate($theme, 'default');
        }
        $target = $this->templatesRoot . '/' . $name;
        if (!is_dir($target)) {
            return $this->redirectWithError('Template not found');
        }
        $this->deleteDir($target);
        return $this->redirectWithSaved(['msg' => 'Template deleted']);
    }

    private function availableTemplates(): array
    {
        $items = [
            ['name' => 'default', 'path' => null, 'valid' => true],
        ];
        if (!is_dir($this->templatesRoot)) {
            return $items;
        }
        $dirs = glob($this->templatesRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $name = basename($dir);
            $layout = $dir . '/layout.php';
            $items[] = [
                'name' => $name,
                'path' => $dir,
                'valid' => is_file($layout),
            ];
        }
        return $items;
    }

    private function setCurrentTemplate(string $theme, string $name): void
    {
        $key = $this->settingsKey($theme);
        $value = $name === 'default' ? '' : $name;
        $this->settings->set($key, $value);
        $this->config->set('app.template', $name);
    }

    private function currentTemplateName(string $theme): string
    {
        $name = trim((string)$this->settings->get($this->settingsKey($theme), ''));
        return $name !== '' ? $name : 'default';
    }

    private function settingsKey(string $theme): string
    {
        $clean = preg_replace('/[^a-z0-9_\\-]/i', '', $theme);
        if ($clean === '') {
            $clean = 'default';
        }
        return 'template_active_' . strtolower($clean);
    }

    private function normalizeTemplateName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_\\-]/', '', $name);
        return $name;
    }

    private function installFromZip(string $zipPath): array
    {
        if (!is_dir($this->templatesRoot)) {
            @mkdir($this->templatesRoot, 0775, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'message' => 'Cannot open archive'];
        }
        $top = $this->detectTopFolder($zip);
        if ($top === '') {
            $zip->close();
            return ['ok' => false, 'message' => 'Archive must contain a single root folder'];
        }
        $safeName = $this->normalizeTemplateName($top);
        if ($safeName === '' || $safeName === 'default') {
            $zip->close();
            return ['ok' => false, 'message' => 'Invalid template folder name'];
        }
        $target = $this->templatesRoot . '/' . $safeName;
        if (is_dir($target)) {
            $zip->close();
            return ['ok' => false, 'message' => 'Template already exists'];
        }
        $tmpDir = sys_get_temp_dir() . '/tpl_' . uniqid('', true);
        @mkdir($tmpDir, 0775, true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === null || $name === '' || str_starts_with($name, '__MACOSX')) {
                continue;
            }
            if (!$this->isSafeEntry($name)) {
                $zip->close();
                $this->deleteDir($tmpDir);
                return ['ok' => false, 'message' => 'Unsafe archive contents'];
            }
            if (!$this->isAllowedEntry($name)) {
                $zip->close();
                $this->deleteDir($tmpDir);
                return ['ok' => false, 'message' => 'Archive contains unsupported files'];
            }
        }
        $zip->extractTo($tmpDir);
        $zip->close();
        $source = $tmpDir . '/' . $top;
        if (!is_dir($source) || !is_file($source . '/layout.php')) {
            $this->deleteDir($tmpDir);
            return ['ok' => false, 'message' => 'Template must include layout.php'];
        }
        if (!$this->moveDir($source, $target)) {
            $this->deleteDir($tmpDir);
            return ['ok' => false, 'message' => 'Cannot install template'];
        }
        $this->deleteDir($tmpDir);
        return ['ok' => true, 'template' => $safeName, 'message' => ''];
    }

    private function detectTopFolder(ZipArchive $zip): string
    {
        $top = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === null || $name === '' || str_starts_with($name, '__MACOSX')) {
                continue;
            }
            $parts = explode('/', $name);
            $root = $parts[0] ?? '';
            if ($root === '') {
                continue;
            }
            if ($top === null) {
                $top = $root;
            } elseif ($top !== $root) {
                return '';
            }
        }
        return $top ?? '';
    }

    private function isSafeEntry(string $name): bool
    {
        if (str_contains($name, "\0")) {
            return false;
        }
        if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }
        if (str_contains($name, '..')) {
            return false;
        }
        return true;
    }

    private function isAllowedEntry(string $name): bool
    {
        if (str_ends_with($name, '/')) {
            return true;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') {
            return true;
        }
        return in_array($ext, [
            'php', 'css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'gif',
            'ico', 'json', 'txt', 'md', 'map', 'woff', 'woff2', 'ttf', 'eot',
        ], true);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function moveDir(string $source, string $target): bool
    {
        if (!is_dir($source)) {
            return false;
        }
        if (!is_dir($target)) {
            @mkdir($target, 0775, true);
        }
        $items = scandir($source);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . '/' . $item;
            $to = $target . '/' . $item;
            if (is_dir($from)) {
                if (!$this->moveDir($from, $to)) {
                    return false;
                }
            } else {
                if (!@copy($from, $to)) {
                    return false;
                }
            }
        }
        $this->deleteDir($source);
        return true;
    }

    private function redirectWithSaved(array $extra = []): Response
    {
        $prefix = $this->config['admin_prefix'] ?? '/admin';
        $query = array_merge(['saved' => 1], $extra);
        $url = $prefix . '/templates?' . http_build_query($query);
        return new Response('', 302, ['Location' => $url]);
    }

    private function redirectWithError(string $message): Response
    {
        $prefix = $this->config['admin_prefix'] ?? '/admin';
        $url = $prefix . '/templates?error=' . urlencode($message);
        return new Response('', 302, ['Location' => $url]);
    }
}
