<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class FileManagerController
{
    private Container $container;
    private string $basePath;
    private string $baseUrl;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings  = $container->get(SettingsService::class);
        $this->basePath  = APP_ROOT . '/storage/uploads';
        $this->baseUrl   = '/storage/uploads';
    }

    public function index(Request $request): Response
    {
        $dir  = $this->resolveDir($request->query['dir'] ?? '');
        $ap   = $this->prefix();

        if ($dir === null) {
            return new Response('Forbidden', 403);
        }

        $fmFlash = $_SESSION['file_manager_flash'] ?? null;
        unset($_SESSION['file_manager_flash']);

        [$folders, $files] = $this->scanDir($dir);
        $breadcrumbs = $this->breadcrumbs($dir);

        $html = $this->container->get('renderer')->render('admin/files', [
            'title'       => 'File Manager',
            'folders'     => $folders,
            'files'       => $files,
            'breadcrumbs' => $breadcrumbs,
            'currentDir'  => $dir,
            'csrf'        => Csrf::token('file_manager'),
            'fmFlash'     => $fmFlash,
            'ap'          => $ap,
        ]);
        return new Response($html);
    }

    public function mkdir(Request $request): Response
    {
        if (!Csrf::check('file_manager', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $dir  = $this->resolveDir($request->body['dir'] ?? '');
        $name = $this->sanitizeName($request->body['name'] ?? '');

        if ($dir === null || $name === '') {
            return new Response('Invalid request', 400);
        }

        $target = $dir . '/' . $name;
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        $_SESSION['file_manager_flash'] = ['type' => 'success', 'text' => "Folder «{$name}» created."];
        return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
    }

    public function upload(Request $request): Response
    {
        if (!Csrf::check('file_manager', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $dir = $this->resolveDir($request->body['dir'] ?? '');
        if ($dir === null) {
            return new Response('Forbidden', 403);
        }

        $file = $_FILES['file'] ?? null;
        if (empty($file['tmp_name'])) {
            return new Response('No file', 400);
        }

        $cfg     = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 10 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            $_SESSION['file_manager_flash'] = ['type' => 'danger', 'text' => 'File too large.'];
            return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
        }

        $finfo   = new \finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'text/plain' => 'txt',
        ];

        if (!isset($allowed[$mime])) {
            $_SESSION['file_manager_flash'] = ['type' => 'danger', 'text' => 'File type not allowed.'];
            return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
        }

        $ext    = $allowed[$mime];
        $base   = pathinfo($file['name'], PATHINFO_FILENAME);
        $base   = $this->sanitizeName($base) ?: uniqid('file_', true);
        $name   = $base . '.' . $ext;
        $target = $dir . '/' . $name;

        // avoid overwrite
        if (file_exists($target)) {
            $name   = $base . '_' . time() . '.' . $ext;
            $target = $dir . '/' . $name;
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $_SESSION['file_manager_flash'] = ['type' => 'danger', 'text' => 'Upload failed.'];
            return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
        }

        $_SESSION['file_manager_flash'] = ['type' => 'success', 'text' => "Uploaded: {$name}"];
        return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('file_manager', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $dir    = $this->resolveDir($request->body['dir'] ?? '');
        $target = $this->resolvePath($request->body['path'] ?? '');

        if ($dir === null || $target === null) {
            return new Response('Forbidden', 403);
        }

        if (is_file($target)) {
            @unlink($target);
            $_SESSION['file_manager_flash'] = ['type' => 'success', 'text' => 'File deleted.'];
        } elseif (is_dir($target)) {
            if ($this->isDirEmpty($target)) {
                rmdir($target);
                $_SESSION['file_manager_flash'] = ['type' => 'success', 'text' => 'Folder deleted.'];
            } else {
                $_SESSION['file_manager_flash'] = ['type' => 'danger', 'text' => 'Folder is not empty.'];
            }
        }

        return new Response('', 302, ['Location' => $this->indexUrl($dir)]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Resolve and validate a relative dir path. Returns absolute path or null. */
    private function resolveDir(string $rel): ?string
    {
        $rel  = trim($rel, '/');
        $path = $rel === '' ? $this->basePath : $this->basePath . '/' . $rel;
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            return $rel === '' ? $this->basePath : null;
        }
        if (strncmp($real, $this->basePath, strlen($this->basePath)) !== 0) {
            return null; // path traversal
        }
        return $real;
    }

    /** Resolve and validate an arbitrary path (file or dir). */
    private function resolvePath(string $rel): ?string
    {
        $rel  = trim($rel, '/');
        $path = $rel === '' ? null : $this->basePath . '/' . $rel;
        if ($path === null) {
            return null;
        }
        // Don't require realpath (file may be deleted), validate prefix manually
        $normalized = realpath(dirname($path));
        if ($normalized === false) {
            return null;
        }
        $full = $normalized . '/' . basename($path);
        if (strncmp($full, $this->basePath, strlen($this->basePath)) !== 0) {
            return null;
        }
        return $full;
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', trim($name));
        $name = trim($name, '.');
        return $name === '' ? '' : $name;
    }

    private function scanDir(string $absDir): array
    {
        $folders = [];
        $files   = [];
        $entries = scandir($absDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $absDir . '/' . $entry;
            $rel  = ltrim(substr($full, strlen($this->basePath)), '/');

            if (is_dir($full)) {
                $folders[] = [
                    'name'  => $entry,
                    'rel'   => $rel,
                    'count' => count(array_diff(scandir($full) ?: [], ['.', '..'])),
                ];
            } elseif (is_file($full)) {
                $mime    = mime_content_type($full) ?: '';
                $isImage = strncmp($mime, 'image/', 6) === 0;
                $files[] = [
                    'name'    => $entry,
                    'rel'     => $rel,
                    'url'     => $this->baseUrl . '/' . $rel,
                    'size'    => filesize($full),
                    'isImage' => $isImage,
                    'mime'    => $mime,
                ];
            }
        }

        return [$folders, $files];
    }

    private function breadcrumbs(string $absDir): array
    {
        $rel   = ltrim(substr($absDir, strlen($this->basePath)), '/');
        $crumbs = [['label' => 'uploads', 'rel' => '']];
        if ($rel !== '') {
            $parts   = explode('/', $rel);
            $cumulative = '';
            foreach ($parts as $part) {
                $cumulative .= ($cumulative === '' ? '' : '/') . $part;
                $crumbs[] = ['label' => $part, 'rel' => $cumulative];
            }
        }
        return $crumbs;
    }

    private function indexUrl(string $absDir): string
    {
        $rel = ltrim(substr($absDir, strlen($this->basePath)), '/');
        $ap  = $this->prefix();
        return $ap . '/files' . ($rel !== '' ? '?dir=' . urlencode($rel) : '');
    }

    private function isDirEmpty(string $path): bool
    {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        return count($items) === 0;
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }
}
