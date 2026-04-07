<?php
declare(strict_types=1);

namespace Modules\Users\Services;

final class PhotoCopyrightService
{
    public static function defaultColor(): string
    {
        return '#f8f0eb';
    }

    public function applyToFiles(array $paths, array $options): void
    {
        foreach ($paths as $path) {
            $path = (string)$path;
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $this->applyToImage($path, $options);
        }
    }

    public function applyToImage(string $path, array $options): bool
    {
        $text = trim((string)($options['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1]) || empty($info['mime'])) {
            return false;
        }

        $image = $this->createImage($path, (string)$info['mime']);
        if (!$image) {
            return false;
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        $fontKey = $this->normalizeFontKey((string)($options['font'] ?? 'oswald'));
        $fontFile = $this->fontFile($fontKey);
        $rgb = $this->normalizeColor((string)($options['color'] ?? self::defaultColor()));

        if ($fontFile !== null && function_exists('imagettftext')) {
            $this->drawTtfWatermark($image, $width, $height, $text, $fontFile, $rgb);
        } else {
            $this->drawBuiltinWatermark($image, $width, $height, $text, $rgb);
        }

        $saved = $this->saveImage($image, $path, (string)$info['mime']);
        imagedestroy($image);

        return $saved;
    }

    public static function fontOptions(): array
    {
        return [
            'oswald' => 'Oswald',
            'studio' => 'PT Sans',
            'mono' => 'Fira Sans',
            'pacifico' => 'Pacifico',
            'lobster' => 'Lobster',
            'caveat' => 'Caveat',
            'russo' => 'Russo One',
        ];
    }

    private function drawTtfWatermark($image, int $width, int $height, string $text, string $fontFile, array $rgb): void
    {
        $fontSize = max(16, (int)round(min($width, $height) * 0.035));
        $margin = max(18, (int)round($width * 0.025));
        $angle = 0;
        $bbox = imagettfbbox($fontSize, $angle, $fontFile, $text);
        if (!is_array($bbox)) {
            $this->drawBuiltinWatermark($image, $width, $height, $text);
            return;
        }

        $textWidth = (int)abs($bbox[2] - $bbox[0]);
        $textHeight = (int)abs($bbox[7] - $bbox[1]);
        $x = max($margin, $width - $textWidth - $margin);
        $y = max($margin + $textHeight, $height - $margin);

        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 72);
        $color = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 58);
        imagettftext($image, $fontSize, $angle, $x + 2, $y + 2, $shadow, $fontFile, $text);
        imagettftext($image, $fontSize, $angle, $x, $y, $color, $fontFile, $text);
    }

    private function drawBuiltinWatermark($image, int $width, int $height, string $text, array $rgb): void
    {
        $font = 5;
        $margin = max(14, (int)round($width * 0.02));
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = max($margin, $width - $textWidth - $margin);
        $y = max($margin, $height - $textHeight - $margin);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 72);
        $color = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 58);
        imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
        imagestring($image, $font, $x, $y, $text, $color);
    }

    private function normalizeColor(string $value): array
    {
        $value = trim($value);
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            $value = self::defaultColor();
        }
        $value = ltrim($value, '#');

        return [
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        ];
    }

    private function createImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function saveImage($image, string $path, string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 86),
            'image/png' => imagepng($image, $path, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $path, 86) : false,
            default => false,
        };
    }

    private function normalizeFontKey(string $font): string
    {
        $font = strtolower(trim($font));
        return array_key_exists($font, self::fontOptions()) ? $font : 'oswald';
    }

    private function fontFile(string $font): ?string
    {
        $map = [
            'oswald' => APP_ROOT . '/qr-generator/static/fonts/Oswald-SemiBold.ttf',
            'studio' => APP_ROOT . '/qr-generator/static/fonts/TK3_WkUHHAIjg75cFRf3bXL8LICs1GZc.ttf',
            'mono' => null,
        ];
        $path = $map[$font] ?? null;
        if ($path === null || !is_file($path)) {
            return null;
        }
        return $path;
    }
}
