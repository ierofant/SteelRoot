<?php
namespace App\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class AttachmentController
{
    private Database $db;
    private SettingsService $settings;
    private string $uploadPath;

    public function __construct(Container $container)
    {
        $this->db = $container->get(Database::class);
        $this->settings = $container->get(SettingsService::class);
        $this->uploadPath = APP_ROOT . '/storage/uploads/articles';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
    }

    public function upload(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return Response::json(['error' => 'Method not allowed'], 405);
        }
        if (!Csrf::check('article_upload', $request->body['_token'] ?? null)) {
            return Response::json(['error' => 'Invalid CSRF'], 400);
        }
        if (empty($_SESSION['admin_auth'])) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        if (empty($_FILES['file']['tmp_name'])) {
            return Response::json(['error' => 'No file'], 400);
        }
        $cfgLimits = $this->settings->all();
        $maxSize = (int)($cfgLimits['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($_FILES['file']['size'] > $maxSize) {
            return Response::json(['error' => 'File too large'], 400);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return Response::json(['error' => 'Unsupported type'], 400);
        }
        $ext = $allowed[$mime];
        $safeName = uniqid('a_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $safeName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            return Response::json(['error' => 'Upload failed'], 500);
        }
        [$w, $h] = @getimagesize($target) ?: [0, 0];
        $maxW = (int)($cfgLimits['upload_max_width'] ?? 8000);
        $maxH = (int)($cfgLimits['upload_max_height'] ?? 8000);
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return Response::json(['error' => 'Image too large in dimensions'], 400);
        }
        $url = '/storage/uploads/articles/' . $safeName;
        return Response::json(['success' => true, 'url' => $url]);
    }
}
