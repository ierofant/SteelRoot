<?php

namespace Core;

final class Asset
{
    public static function url(string $publicPath): string
    {
        $publicPath = trim($publicPath);
        if ($publicPath === '' || !str_starts_with($publicPath, '/')) {
            return $publicPath;
        }

        $resolvedPath = self::resolvePublicPath($publicPath);
        $absolutePath = APP_ROOT . $resolvedPath;
        $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';

        $separator = str_contains($resolvedPath, '?') ? '&' : '?';
        return $resolvedPath . $separator . 'v=' . $version;
    }

    public static function styleTag(string $publicPath, array $attrs = []): string
    {
        $attrs = array_merge(['rel' => 'stylesheet', 'href' => self::url($publicPath)], $attrs);
        return '<link' . self::attrs($attrs) . '>';
    }

    public static function scriptTag(string $publicPath, array $attrs = []): string
    {
        $attrs = array_merge(['src' => self::url($publicPath)], $attrs);
        return '<script' . self::attrs($attrs) . '></script>';
    }

    private static function resolvePublicPath(string $publicPath): string
    {
        $info = pathinfo($publicPath);
        $extension = strtolower((string)($info['extension'] ?? ''));
        $dirname = ($info['dirname'] ?? '') === '/' ? '' : (string)($info['dirname'] ?? '');
        $filename = (string)($info['filename'] ?? '');

        if (!in_array($extension, ['css', 'js'], true) || str_ends_with($filename, '.min')) {
            return $publicPath;
        }

        $minPublicPath = ($dirname !== '' ? $dirname : '') . '/' . $filename . '.min.' . $extension;
        $minAbsolutePath = APP_ROOT . $minPublicPath;

        return is_file($minAbsolutePath) ? $minPublicPath : $publicPath;
    }

    private static function attrs(array $attrs): string
    {
        $compiled = '';
        foreach ($attrs as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            if ($value === true) {
                $compiled .= ' ' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
                continue;
            }
            $compiled .= ' ' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . '="' .
                htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $compiled;
    }
}
