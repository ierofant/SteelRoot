<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class AttachmentAdminController
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

    public function index(Request $request): Response
    {
        $files = glob(APP_ROOT . '/storage/uploads/articles/*');
        $items = array_map(function ($file) {
            return [
                'name' => basename($file),
                'url' => '/storage/uploads/articles/' . basename($file),
                'size' => filesize($file),
            ];
        }, $files ?: []);
        $msg = null;
        if (!empty($request->query['msg'])) {
            $msg = $request->query['msg'] === 'uploaded' ? 'Файл загружен' : null;
        }
        $html = $this->render($items, Csrf::token('attachments_admin'), $msg);
        return new Response($html);
    }

    public function upload(Request $request): Response
    {
        if (!Csrf::check('attachments_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        if (empty($_FILES['file']['tmp_name'])) {
            return new Response('No file', 400);
        }
        $cfg = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($_FILES['file']['size'] > $maxSize) {
            return new Response('Too large', 400);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return new Response('Unsupported type', 400);
        }
        $ext = $allowed[$mime];
        $name = uniqid('a_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $name;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            return new Response('Upload failed', 500);
        }
        $maxW = (int)($cfg['upload_max_width'] ?? 8000);
        $maxH = (int)($cfg['upload_max_height'] ?? 8000);
        [$w,$h] = @getimagesize($target) ?: [0,0];
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return new Response('Image too large in dimensions', 400);
        }
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/attachments?msg=uploaded']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('attachments_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $name = basename($request->params['name'] ?? ($request->body['name'] ?? ''));
        if ($name === '') {
            return new Response('Missing file name', 400);
        }
        $path = APP_ROOT . '/storage/uploads/articles/' . $name;
        if (!is_file($path)) {
            return new Response('Not found', 404);
        }
        @unlink($path);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/attachments']);
    }

    private function render(array $items, string $csrf, ?string $message = null): string
    {
        ob_start(); ?>
        <div class="card form-dark">
            <div class="card-header">
                <div>
                    <p class="eyebrow"><?= __('attachments.title') ?></p>
                    <h3><?= __('attachments.subtitle') ?></h3>
                </div>
            </div>
            <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="table-wrap">
                <table class="table data attachments-table">
                    <thead>
                        <tr><th><?= __('attachments.table.preview') ?></th><th><?= __('attachments.table.name') ?></th><th><?= __('attachments.table.size') ?></th><th><?= __('attachments.table.link') ?></th><th><?= __('attachments.table.actions') ?></th></tr>
                    </thead>
                    <tbody>
            <tr>
                <td colspan="5">
                    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/attachments/upload') ?>" class="stack attachments-upload-row">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="file" name="file" required>
                        <button type="submit" class="btn primary small"><?= __('attachments.upload') ?></button>
                        <span class="muted"><?= __('attachments.upload_hint') ?></span>
                    </form>
                </td>
            </tr>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="width:90px;">
                        <img src="<?= htmlspecialchars($item['url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width:80px;height:60px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.08);">
                    </td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= number_format($item['size']/1024, 1) ?> KB</td>
                    <td><a href="<?= htmlspecialchars($item['url']) ?>" target="_blank"><?= __('attachments.open') ?></a></td>
                    <td class="actions">
                        <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/attachments/delete') ?>" onsubmit="return confirm('<?= __('attachments.confirm_delete') ?>');" class="inline-form">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="name" value="<?= htmlspecialchars($item['name']) ?>">
                            <button type="submit" class="btn danger small"><?= __('attachments.delete') ?></button>
                        </form>
                        <button type="button" class="btn ghost small" onclick="insertAttachment('<?= htmlspecialchars($item['url']) ?>')"><?= __('attachments.insert') ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="5" class="muted"><?= __('attachments.empty') ?></td></tr>
            <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        function insertAttachment(url){
            if (window.opener && typeof window.opener.insertAttachmentUrl === 'function') {
                window.opener.insertAttachmentUrl(url);
                window.close();
            } else {
                prompt('Copy attachment URL:', url);
            }
        }
        </script>
        <?php
        $content = ob_get_clean();
        $title = __('attachments.page_title');
        $showSidebar = true;
        return $this->wrap($content, $title);
    }

    private function wrap(string $content, string $title): string
    {
        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }
}
