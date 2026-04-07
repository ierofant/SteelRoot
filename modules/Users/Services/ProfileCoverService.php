<?php
namespace Modules\Users\Services;

class ProfileCoverService
{
    private string $dir;
    private int $maxSize = 8 * 1024 * 1024;
    private int $targetWidth = 1600;
    private int $targetHeight = 640;

    public function __construct()
    {
        $this->dir = APP_ROOT . '/storage/uploads/users/covers';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function processUploadedImage(string $tmpFilePath, int $userId): string
    {
        $check = $this->validate($tmpFilePath);
        if ($check !== true) {
            return '';
        }

        $info = @getimagesize($tmpFilePath);
        if (!$info || empty($info['mime'])) {
            return '';
        }

        $src = $this->createImage($tmpFilePath, (string)$info['mime']);
        if (!$src) {
            return '';
        }

        $naturalW = imagesx($src);
        $naturalH = imagesy($src);
        if ($naturalW < 320 || $naturalH < 160) {
            imagedestroy($src);
            return '';
        }

        $targetRatio = $this->targetWidth / $this->targetHeight;
        $sourceRatio = $naturalW / max(1, $naturalH);

        if ($sourceRatio > $targetRatio) {
            $cropH = $naturalH;
            $cropW = (int)round($naturalH * $targetRatio);
            $cropX = (int)round(($naturalW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $naturalW;
            $cropH = (int)round($naturalW / $targetRatio);
            $cropX = 0;
            $cropY = (int)round(($naturalH - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($this->targetWidth, $this->targetHeight);
        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            $cropX,
            $cropY,
            $this->targetWidth,
            $this->targetHeight,
            $cropW,
            $cropH
        );

        $filename = rtrim($this->dir, '/') . '/' . (int)$userId . '.jpg';
        imagejpeg($dst, $filename, 86);
        imagedestroy($src);
        imagedestroy($dst);

        return '/storage/uploads/users/covers/' . (int)$userId . '.jpg';
    }

    public function validate(string $tmpFilePath)
    {
        if ($tmpFilePath === '' || !file_exists($tmpFilePath)) {
            return 'File not found';
        }
        if (filesize($tmpFilePath) > $this->maxSize) {
            return 'File too large';
        }

        $info = @getimagesize($tmpFilePath);
        if (!$info || empty($info['mime'])) {
            return 'Invalid image';
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($info['mime'], $allowed, true)) {
            return 'Unsupported image type';
        }

        return true;
    }

    private function createImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }
}
