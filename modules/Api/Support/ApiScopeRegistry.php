<?php
declare(strict_types=1);

namespace Modules\Api\Support;

use Core\Logger;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiSecurity;
use ReflectionClass;
use ReflectionMethod;

class ApiScopeRegistry
{
    public static function build(): array
    {
        $routes = [];
        $files = self::controllerFiles();
        $seen = get_declared_classes();
        foreach ($files as $file) {
            $before = $seen;
            try {
                require_once $file;
            } catch (\Throwable $e) {
                Logger::log('ApiScopeRegistry include failed: ' . $file . ' ' . $e->getMessage());
                continue;
            }
            $after = get_declared_classes();
            $new = array_diff($after, $before);
            foreach ($new as $class) {
                self::collectRoutesFromClass($class, $routes);
            }
            $seen = $after;
        }
        usort($routes, function ($a, $b) {
            $path = strcmp($a['path'], $b['path']);
            if ($path !== 0) {
                return $path;
            }
            return strcmp($a['method'], $b['method']);
        });
        return $routes;
    }

    private static function collectRoutesFromClass(string $class, array &$routes): void
    {
        try {
            $ref = new ReflectionClass($class);
        } catch (\Throwable $e) {
            Logger::log('ApiScopeRegistry reflect failed: ' . $e->getMessage());
            return;
        }
        if ($ref->isAbstract()) {
            return;
        }
        $classScopes = self::collectAttrValues($ref, ApiSecurity::class, 'scopes');
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttrs = $method->getAttributes(ApiRoute::class);
            if (!$routeAttrs) {
                continue;
            }
            $methodScopes = self::collectAttrValues($method, ApiSecurity::class, 'scopes');
            $scopes = array_values(array_unique(array_merge($classScopes, $methodScopes)));
            foreach ($routeAttrs as $attr) {
                $route = $attr->newInstance();
                $path = self::normalizePath($route->path);
                $routes[] = [
                    'method' => $route->method,
                    'path' => $path,
                    'scopes' => $scopes,
                    'regex' => self::buildRegex($path),
                ];
            }
        }
    }

    private static function collectAttrValues($ref, string $attrClass, string $prop): array
    {
        $values = [];
        foreach ($ref->getAttributes($attrClass) as $attr) {
            $instance = $attr->newInstance();
            $value = $instance->{$prop} ?? null;
            if (is_array($value)) {
                foreach ($value as $item) {
                    $values[] = (string)$item;
                }
            } elseif ($value !== null) {
                $values[] = (string)$value;
            }
        }
        return array_values(array_filter($values));
    }

    private static function controllerFiles(): array
    {
        $root = defined('APP_ROOT') ? APP_ROOT : getcwd();
        $dir = $root . '/modules/Api/Controllers';
        return self::collectPhpFiles($dir);
    }

    private static function collectPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    private static function buildRegex(string $path): ?string
    {
        if (!str_contains($path, '{')) {
            return null;
        }
        $pattern = preg_replace('#\{([^}/]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}
