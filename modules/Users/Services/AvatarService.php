<?php
namespace Modules\Users\Services;

class AvatarService
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function save(array $file, ?string $previous = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!empty($file['size']) && $file['size'] > 2 * 1024 * 1024) {
            return null;
        }
        $tmp = $file['tmp_name'] ?? '';
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $img = $this->createImage($tmp, (int)$info[2]);
        if (!$img) {
            return null;
        }
        $size = min(imagesx($img), imagesy($img));
        $avatar = imagecreatetruecolor(256, 256);
        $bg = imagecolorallocate($avatar, 18, 18, 24);
        imagefill($avatar, 0, 0, $bg);
        $x = (imagesx($img) - $size) / 2;
        $y = (imagesy($img) - $size) / 2;
        imagecopyresampled($avatar, $img, 0, 0, (int)$x, (int)$y, 256, 256, $size, $size);
        $name = 'avatar_' . uniqid() . '.png';
        $path = $this->dir . '/' . $name;
        imagepng($avatar, $path, 6);
        imagedestroy($img);
        imagedestroy($avatar);
        if ($previous && str_starts_with($previous, '/storage/uploads/users/')) {
            $prevPath = APP_ROOT . $previous;
            if (file_exists($prevPath)) {
                @unlink($prevPath);
            }
        }
        return '/storage/uploads/users/' . $name;
    }

    private function createImage(string $file, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => null,
        };
    }
}
