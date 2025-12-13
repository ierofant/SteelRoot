<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\TagService;

class FileManagerController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private TagService $tags;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
        $this->uploadPath = APP_ROOT . '/storage/uploads/gallery';
    }

    public function index(Request $request): Response
    {
        $q = trim($request->query['q'] ?? '');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $items = $this->db->fetchAll("SELECT * FROM gallery_items WHERE title_en LIKE :like OR title_ru LIKE :like ORDER BY id DESC LIMIT 200", [':like' => $like]);
        } else {
            $items = $this->db->fetchAll("SELECT * FROM gallery_items ORDER BY id DESC LIMIT 200");
        }
        $html = $this->container->get('renderer')->render('admin/files', [
            'title' => 'Files',
            'items' => $items,
            'csrf' => Csrf::token('files'),
            'query' => $q,
        ]);
        return new Response($html);
    }

    public function regenerate(Request $request): Response
    {
        if (!Csrf::check('files', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $full = APP_ROOT . $item['path'];
        if (!is_file($full)) {
            return new Response('Original missing', 404);
        }
        $ext = pathinfo($full, PATHINFO_EXTENSION);
        $base = pathinfo($full, PATHINFO_FILENAME);
        $mediumName = $base . '_m.' . $ext;
        $thumbName = $base . '_t.' . $ext;
        $mediumPath = $this->uploadPath . '/' . $mediumName;
        $thumbPath = $this->uploadPath . '/' . $thumbName;
        $this->resizeCopy($full, $mediumPath, 1200);
        $this->resizeCopy($full, $thumbPath, 360);
        $this->db->execute("
            UPDATE gallery_items SET path_medium = :pm, path_thumb = :pt WHERE id = :id
        ", [
            ':pm' => '/storage/uploads/gallery/' . $mediumName,
            ':pt' => '/storage/uploads/gallery/' . $thumbName,
            ':id' => $id,
        ]);
        return new Response('', 302, ['Location' => $this->prefix() . '/files']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('files', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if ($item) {
            foreach (['path','path_medium','path_thumb'] as $k) {
                if (!empty($item[$k]) && is_file(APP_ROOT . $item[$k])) {
                    @unlink(APP_ROOT . $item[$k]);
                }
            }
            $this->db->execute("DELETE FROM gallery_items WHERE id = ?", [$id]);
            $this->db->execute("DELETE FROM taggables WHERE entity_type = 'gallery' AND entity_id = ?", [$id]);
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/files']);
    }

    private function resizeCopy(string $src, string $dest, int $maxWidth): void
    {
        $info = @getimagesize($src);
        if (!$info) {
            @copy($src, $dest);
            return;
        }
        [$w, $h, $type] = $info;
        if ($w <= $maxWidth) {
            @copy($src, $dest);
            return;
        }
        $newW = $maxWidth;
        $newH = (int)round($h * ($newW / $w));
        $dst = imagecreatetruecolor($newW, $newH);
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImg = imagecreatefromjpeg($src);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagejpeg($dst, $dest, 85);
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_PNG:
                $srcImg = imagecreatefrompng($src);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagepng($dst, $dest, 6);
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImg = imagecreatefromwebp($src);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                    imagewebp($dst, $dest, 85);
                    imagedestroy($srcImg);
                } else {
                    @copy($src, $dest);
                }
                break;
            default:
                @copy($src, $dest);
        }
        imagedestroy($dst);
    }

    private function prefix(): string
    {
        $config = $this->container->get('config');
        return $config['admin_prefix'] ?? '/admin';
    }
}
