<?php
declare(strict_types=1);

namespace Modules\Gallery\Services;

final class GalleryImageVariantService
{
    public const DEFAULT_MEDIUM_WIDTH = 1600;
    public const DEFAULT_THUMB_WIDTH = 640;
    public const DEFAULT_FORMAT = 'webp';

    private int $mediumWidth;
    private int $thumbWidth;
    private string $format;

    public function __construct(array $settings = [])
    {
        $this->mediumWidth = max(320, (int)($settings['medium_width'] ?? self::DEFAULT_MEDIUM_WIDTH));
        $this->thumbWidth = max(160, min($this->mediumWidth, (int)($settings['thumb_width'] ?? self::DEFAULT_THUMB_WIDTH)));
        $format = strtolower(trim((string)($settings['format'] ?? self::DEFAULT_FORMAT)));
        $this->format = in_array($format, ['source', 'webp'], true) ? $format : self::DEFAULT_FORMAT;
        if ($this->format === 'webp' && (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp'))) {
            $this->format = 'source';
        }
    }

    public function regenerateFromPublicPath(string $originalPublicPath, bool $deleteExistingVariants = false): ?array
    {
        $originalPublicPath = trim($originalPublicPath);
        if ($originalPublicPath === '' || !str_starts_with($originalPublicPath, '/')) {
            return null;
        }

        $originalAbsolutePath = APP_ROOT . $originalPublicPath;
        if (!is_file($originalAbsolutePath)) {
            return null;
        }

        $paths = $this->variantPaths($originalPublicPath);
        if ($deleteExistingVariants) {
            foreach ($this->legacyVariantAbsolutePaths($originalPublicPath) as $legacyAbsolutePath) {
                $this->deleteIfExists($legacyAbsolutePath);
            }
            $this->deleteIfExists($paths['medium_absolute']);
            $this->deleteIfExists($paths['thumb_absolute']);
        }

        $info = @getimagesize($originalAbsolutePath);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }
        $sourceWidth = (int)$info[0];
        $sourceHeight = (int)$info[1];
        $sourceSize = (int)@filesize($originalAbsolutePath);
        $sourceExtension = strtolower((string)pathinfo($originalAbsolutePath, PATHINFO_EXTENSION));

        $mediumPublicPath = $originalPublicPath;
        $mediumAbsolutePath = $originalAbsolutePath;
        if ($this->shouldCreateVariant($sourceWidth, $this->mediumWidth, $sourceExtension)) {
            $mediumMeta = $this->renderVariant($originalAbsolutePath, $paths['medium_absolute'], $this->mediumWidth);
            if ($this->isUsefulVariant($mediumMeta, $sourceWidth, $sourceHeight, $sourceSize, $sourceExtension)) {
                $mediumPublicPath = $paths['medium_public'];
                $mediumAbsolutePath = $paths['medium_absolute'];
            } else {
                $this->deleteIfExists($paths['medium_absolute']);
            }
        }

        $thumbPublicPath = $mediumPublicPath;
        if ($this->thumbWidth !== $this->mediumWidth && $this->shouldCreateVariant($sourceWidth, $this->thumbWidth, $sourceExtension)) {
            $thumbMeta = $this->renderVariant($originalAbsolutePath, $paths['thumb_absolute'], $this->thumbWidth);
            $baselineAbsolutePath = $mediumAbsolutePath;
            if ($mediumAbsolutePath === $originalAbsolutePath && $mediumPublicPath !== $originalPublicPath) {
                $baselineAbsolutePath = APP_ROOT . $mediumPublicPath;
            }
            if ($this->isDistinctVariant($thumbMeta, $baselineAbsolutePath, $mediumPublicPath === $originalPublicPath ? $originalPublicPath : $mediumPublicPath)) {
                $thumbPublicPath = $paths['thumb_public'];
            } else {
                $this->deleteIfExists($paths['thumb_absolute']);
            }
        }

        return [
            'path' => $originalPublicPath,
            'path_medium' => $mediumPublicPath,
            'path_thumb' => $thumbPublicPath,
        ];
    }

    public function deleteDerivedFiles(?string $mediumPublicPath, ?string $thumbPublicPath, string $originalPublicPath): void
    {
        foreach ($this->legacyVariantAbsolutePaths($originalPublicPath) as $legacyAbsolutePath) {
            $this->deleteIfExists($legacyAbsolutePath);
        }

        foreach ([$mediumPublicPath, $thumbPublicPath] as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '' || $candidate === $originalPublicPath || !str_starts_with($candidate, '/')) {
                continue;
            }
            $this->deleteIfExists(APP_ROOT . $candidate);
        }
    }

    private function variantPaths(string $originalPublicPath): array
    {
        $medium = $this->targetVariantKey($originalPublicPath, 'medium');
        $thumb = $this->targetVariantKey($originalPublicPath, 'thumb');

        return [
            'medium_public' => $medium['public'],
            'thumb_public' => $thumb['public'],
            'medium_absolute' => $medium['absolute'],
            'thumb_absolute' => $thumb['absolute'],
        ];
    }

    /**
     * @return array{public:string,absolute:string}
     */
    private function targetVariantKey(string $originalPublicPath, string $variant): array
    {
        $info = pathinfo($originalPublicPath);
        $dirname = ($info['dirname'] ?? '/') === '/' ? '' : (string)($info['dirname'] ?? '');
        $filename = (string)($info['filename'] ?? '');
        $sourceExtension = strtolower((string)($info['extension'] ?? 'jpg'));
        $extension = $this->format === 'webp' ? 'webp' : $sourceExtension;
        $suffix = $variant === 'thumb' ? 't' : 'm';
        $derivedDir = ($dirname !== '' ? $dirname : '') . '/_derived';
        $publicPath = $derivedDir . '/' . $filename . '_' . $suffix . '.' . $extension;

        return [
            'public' => $publicPath,
            'absolute' => APP_ROOT . $publicPath,
        ];
    }

    /**
     * @return string[]
     */
    private function legacyVariantAbsolutePaths(string $originalPublicPath): array
    {
        $info = pathinfo($originalPublicPath);
        $dirname = ($info['dirname'] ?? '/') === '/' ? '' : (string)($info['dirname'] ?? '');
        $filename = (string)($info['filename'] ?? '');
        $extension = (string)($info['extension'] ?? '');

        $baseDir = $dirname !== '' ? $dirname : '';

        return [
            APP_ROOT . $baseDir . '/' . $filename . '_m.' . $extension,
            APP_ROOT . $baseDir . '/' . $filename . '_t.' . $extension,
        ];
    }

    private function deleteIfExists(string $absolutePath): void
    {
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function shouldCreateVariant(int $sourceWidth, int $targetWidth, string $sourceExtension): bool
    {
        if ($sourceWidth > $targetWidth) {
            return true;
        }

        return $this->format === 'webp' && $sourceExtension !== 'webp';
    }

    /**
     * @param array{width:int,height:int,size:int,extension:string}|null $variantMeta
     */
    private function isUsefulVariant(?array $variantMeta, int $sourceWidth, int $sourceHeight, int $sourceSize, string $sourceExtension): bool
    {
        if ($variantMeta === null) {
            return false;
        }

        if ($variantMeta['width'] < $sourceWidth || $variantMeta['height'] < $sourceHeight) {
            return true;
        }

        if ($variantMeta['extension'] !== $sourceExtension && $variantMeta['size'] > 0 && $variantMeta['size'] < $sourceSize) {
            return true;
        }

        return $variantMeta['size'] > 0 && $variantMeta['size'] < $sourceSize;
    }

    /**
     * @param array{width:int,height:int,size:int,extension:string}|null $variantMeta
     */
    private function isDistinctVariant(?array $variantMeta, string $baselineAbsolutePath, string $baselinePublicPath): bool
    {
        if ($variantMeta === null) {
            return false;
        }

        $baselineInfo = @getimagesize($baselineAbsolutePath);
        $baselineSize = (int)@filesize($baselineAbsolutePath);
        $baselineExtension = strtolower((string)pathinfo($baselinePublicPath, PATHINFO_EXTENSION));
        if (!is_array($baselineInfo) || empty($baselineInfo[0]) || empty($baselineInfo[1])) {
            return true;
        }

        if ($variantMeta['width'] < (int)$baselineInfo[0] || $variantMeta['height'] < (int)$baselineInfo[1]) {
            return true;
        }

        if ($variantMeta['extension'] !== $baselineExtension && $variantMeta['size'] > 0 && $variantMeta['size'] < $baselineSize) {
            return true;
        }

        return $variantMeta['size'] > 0 && $variantMeta['size'] < $baselineSize;
    }

    /**
     * @return array{width:int,height:int,size:int,extension:string}|null
     */
    private function renderVariant(string $src, string $dest, int $maxWidth): ?array
    {
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $info = @getimagesize($src);
        if (!$info) {
            return null;
        }

        [$w, $h, $type] = $info;
        $newW = $w <= $maxWidth ? $w : $maxWidth;
        $newH = (int)round($h * ($newW / $w));
        $dst = imagecreatetruecolor($newW, $newH);
        $written = false;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImg = @imagecreatefromjpeg($src);
                if (!$srcImg) {
                    imagedestroy($dst);
                    return null;
                }
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                if ($this->format === 'webp' && function_exists('imagewebp')) {
                    $written = imagewebp($dst, $dest, 82);
                } else {
                    $written = imagejpeg($dst, $dest, 82);
                }
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_PNG:
                $srcImg = @imagecreatefrompng($src);
                if (!$srcImg) {
                    imagedestroy($dst);
                    return null;
                }
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                if ($this->format === 'webp' && function_exists('imagewebp')) {
                    $written = imagewebp($dst, $dest, 82);
                } else {
                    $written = imagepng($dst, $dest, 6);
                }
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) {
                    imagedestroy($dst);
                    return null;
                }
                $srcImg = @imagecreatefromwebp($src);
                if (!$srcImg) {
                    imagedestroy($dst);
                    return null;
                }
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                if ($this->format === 'webp' && function_exists('imagewebp')) {
                    $written = imagewebp($dst, $dest, 82);
                } else {
                    $written = imagewebp($dst, $dest, 82);
                }
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_GIF:
                $srcImg = @imagecreatefromgif($src);
                if (!$srcImg) {
                    imagedestroy($dst);
                    return null;
                }
                imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                if ($this->format === 'webp' && function_exists('imagewebp')) {
                    $written = imagewebp($dst, $dest, 82);
                } else {
                    $written = imagegif($dst, $dest);
                }
                imagedestroy($srcImg);
                break;
            default:
                imagedestroy($dst);
                return null;
        }

        imagedestroy($dst);

        if (!$written || !is_file($dest)) {
            $this->deleteIfExists($dest);
            return null;
        }

        return [
            'width' => $newW,
            'height' => $newH,
            'size' => (int)@filesize($dest),
            'extension' => strtolower((string)pathinfo($dest, PATHINFO_EXTENSION)),
        ];
    }
}
