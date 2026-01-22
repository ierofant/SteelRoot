<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi;

use Core\Logger;
use Modules\Api\OpenApi\Attributes\ApiParam;
use Modules\Api\OpenApi\Attributes\ApiResponse;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiSecurity;
use Modules\Api\OpenApi\Attributes\ApiTag;
use ReflectionClass;
use ReflectionMethod;

class OpenApiGenerator
{
    private string $root;
    private array $config;
    private static ?array $routeCache = null;

    public function __construct(?array $config = null)
    {
        $this->root = defined('APP_ROOT') ? APP_ROOT : getcwd();
        $this->config = $config ?? $this->loadConfig();
    }

    public function generate(): array
    {
        $routes = $this->routes();
        $tags = $this->collectTags($routes);

        $paths = [];
        foreach ($routes as $route) {
            $path = $route['path'];
            $method = strtolower($route['method']);
            $op = [
                'responses' => $route['responses'],
            ];
            if (!empty($route['tags'])) {
                $op['tags'] = $route['tags'];
            }
            if (!empty($route['parameters'])) {
                $op['parameters'] = $route['parameters'];
            }
            if (($route['auth'] ?? true) === true) {
                $op['security'] = [[
                    'bearerAuth' => $route['scopes'] ?? [],
                ]];
            }
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            $paths[$path][$method] = $op;
        }

        ksort($paths);
        foreach ($paths as &$methods) {
            ksort($methods);
        }
        unset($methods);

        $info = $this->config['openapi'] ?? [];
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => (string)($info['title'] ?? 'API'),
                'version' => (string)($info['version'] ?? '1.0.0'),
                'description' => (string)($info['description'] ?? ''),
            ],
            'paths' => $paths,
            'tags' => $tags,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Bearer',
                    ],
                ],
            ],
        ];
    }

    public function routes(): array
    {
        if (self::$routeCache !== null) {
            return self::$routeCache;
        }

        $routes = [];
        $classes = $this->controllerClasses();
        foreach ($classes as $class) {
            try {
                $ref = new ReflectionClass($class);
            } catch (\Throwable $e) {
                Logger::log('OpenAPI reflect failed: ' . $e->getMessage());
                continue;
            }
            if ($ref->isAbstract()) {
                continue;
            }
            $classTags = $this->collectAttrValues($ref, ApiTag::class, 'name');
            $classScopes = $this->collectAttrValues($ref, ApiSecurity::class, 'scopes');
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $routeAttrs = $method->getAttributes(ApiRoute::class);
                if (!$routeAttrs) {
                    continue;
                }
                foreach ($routeAttrs as $attr) {
                    $route = $attr->newInstance();
                    $methodTags = $this->collectAttrValues($method, ApiTag::class, 'name');
                    $methodScopes = $this->collectAttrValues($method, ApiSecurity::class, 'scopes');
                    $tags = array_values(array_unique(array_merge($classTags, $methodTags)));
                    $scopes = array_values(array_unique(array_merge($classScopes, $methodScopes)));

                    $params = [];
                    foreach ($method->getAttributes(ApiParam::class) as $paramAttr) {
                        $param = $paramAttr->newInstance();
                        $schema = $this->normalizeSchema($param->schema ?? 'string');
                        $params[] = [
                            'name' => $param->name,
                            'in' => $param->in,
                            'required' => $param->required,
                            'schema' => $schema,
                        ];
                    }
                    usort($params, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));

                    $responses = [];
                    foreach ($method->getAttributes(ApiResponse::class) as $respAttr) {
                        $resp = $respAttr->newInstance();
                        $responses[(string)$resp->code] = ['description' => $resp->description];
                    }
                    if (empty($responses)) {
                        $responses['200'] = ['description' => 'OK'];
                    } else {
                        ksort($responses);
                    }

                    $routes[] = [
                        'method' => $route->method,
                        'path' => $this->normalizePath($route->path),
                        'auth' => $route->auth,
                        'scopes' => $scopes,
                        'tags' => $tags,
                        'parameters' => $params,
                        'responses' => $responses,
                    ];
                }
            }
        }

        usort($routes, function ($a, $b) {
            $path = strcmp($a['path'], $b['path']);
            if ($path !== 0) {
                return $path;
            }
            return strcmp($a['method'], $b['method']);
        });

        self::$routeCache = $routes;
        return $routes;
    }

    private function collectTags(array $routes): array
    {
        $tags = [];
        foreach ($routes as $route) {
            foreach ($route['tags'] ?? [] as $tag) {
                $tags[$tag] = ['name' => $tag];
            }
        }
        ksort($tags);
        return array_values($tags);
    }

    private function collectAttrValues($ref, string $attrClass, string $prop): array
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

    private function normalizeSchema($schema): array
    {
        if (is_array($schema)) {
            return $schema ?: ['type' => 'string'];
        }
        $type = $schema !== null ? (string)$schema : 'string';
        return ['type' => $type === '' ? 'string' : $type];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : $path;
    }

    private function loadConfig(): array
    {
        try {
            $config = include $this->root . '/modules/Api/config.php';
            return is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            Logger::log('OpenAPI config load failed: ' . $e->getMessage());
            return [];
        }
    }

    private function controllerClasses(): array
    {
        $files = $this->controllerFiles();
        $classes = [];
        $seen = get_declared_classes();
        foreach ($files as $file) {
            $before = $seen;
            try {
                require_once $file;
            } catch (\Throwable $e) {
                Logger::log('OpenAPI include failed: ' . $file . ' ' . $e->getMessage());
                continue;
            }
            $after = get_declared_classes();
            $new = array_diff($after, $before);
            foreach ($new as $class) {
                $classes[] = $class;
            }
            $seen = $after;
        }
        sort($classes);
        return $classes;
    }

    private function controllerFiles(): array
    {
        $files = [];
        $moduleRoot = $this->root . '/modules';
        if (is_dir($moduleRoot)) {
            foreach (glob($moduleRoot . '/*', GLOB_ONLYDIR) ?: [] as $modDir) {
                foreach (['Controllers', 'controllers'] as $dirName) {
                    $path = $modDir . '/' . $dirName;
                    $files = array_merge($files, $this->collectPhpFiles($path));
                }
            }
        }
        foreach (['/app/Controllers', '/app/controllers'] as $appDir) {
            $files = array_merge($files, $this->collectPhpFiles($this->root . $appDir));
        }
        $files = array_values(array_unique(array_filter($files, 'is_file')));
        sort($files);
        return $files;
    }

    private function collectPhpFiles(string $dir): array
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
}
