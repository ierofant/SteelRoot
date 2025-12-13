<?php
namespace Modules\Users\Services;

class AvatarProcessor
{
    private string $dir;
    private int $maxSize = 5 * 1024 * 1024;

    public function __construct()
    {
        $this->dir = APP_ROOT . '/storage/uploads/avatars';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    /**
     * @param string $tmpFilePath path to uploaded tmp file
     * @param int    $userId      user identifier for filename
     * @return string final relative path (/storage/uploads/avatars/{userId}.jpg) or empty string on failure
     */
    public function processUploadedImage($tmpFilePath, $userId): string
    {
        return $this->processImage($tmpFilePath, (int)$userId, null);
    }

    public function processWithCrop(string $tmpPath, int $userId, int $cropX, int $cropY, int $cropW, int $cropH, float $scale): string
    {
        return $this->processImage($tmpPath, $userId, [
            'x' => $cropX,
            'y' => $cropY,
            'w' => $cropW,
            'h' => $cropH,
            'scale' => $scale,
        ]);
    }

    /**
     * Core processing: validation, crop (optional), resize, save.
     */
    private function processImage(string $tmpPath, int $userId, ?array $crop): string
    {
        $check = $this->validate($tmpPath);
        if ($check !== true) {
            return '';
        }
        $info = @getimagesize($tmpPath);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return '';
        }
        $mime = $info['mime'] ?? '';
        $src = $this->createImage($tmpPath, $mime);
        if (!$src) {
            return '';
        }
        $naturalW = imagesx($src);
        $naturalH = imagesy($src);

        $scale = $crop['scale'] ?? 1.0;
        $scale = $scale > 0 ? (float)$scale : 1.0;
        $cropW = isset($crop['w']) ? (int)$crop['w'] : $naturalW;
        $cropH = isset($crop['h']) ? (int)$crop['h'] : $naturalH;
        $cropX = isset($crop['x']) ? (int)$crop['x'] : 0;
        $cropY = isset($crop['y']) ? (int)$crop['y'] : 0;

        if ($cropW <= 0 || $cropH <= 0) {
            $cropW = $naturalW;
            $cropH = $naturalH;
        }

        // If crop covers whole image, force center square
        if ($crop === null) {
            $size = min($naturalW, $naturalH);
            $cropX = (int)(($naturalW - $size) / 2);
            $cropY = (int)(($naturalH - $size) / 2);
            $cropW = $size;
            $cropH = $size;
            $scale = 1.0;
        }

        $scaledW = (int)min($naturalW, max(1, round($cropW / $scale)));
        $scaledH = (int)min($naturalH, max(1, round($cropH / $scale)));
        $scaledX = (int)max(0, min($naturalW - $scaledW, round($cropX / $scale)));
        $scaledY = (int)max(0, min($naturalH - $scaledH, round($cropY / $scale)));

        $dst = imagecreatetruecolor(256, 256);
        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            $scaledX,
            $scaledY,
            256,
            256,
            $scaledW,
            $scaledH
        );
        $filename = rtrim($this->dir, '/') . '/' . (int)$userId . '.jpg';
        imagejpeg($dst, $filename, 85);
        imagedestroy($src);
        imagedestroy($dst);
        return '/storage/uploads/avatars/' . (int)$userId . '.jpg';
    }

    /**
     * Validate uploaded file.
     *
     * @param string $tmpFilePath
     * @return bool|string true if ok, otherwise error message
     */
    public function validate($tmpFilePath)
    {
        if (!$tmpFilePath || !file_exists($tmpFilePath)) {
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
