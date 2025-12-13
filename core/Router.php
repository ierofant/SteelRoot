<?php
namespace Core;

use Closure;

class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private $notFound;

    public function group(string $prefix, array $middleware, Closure $callback): void
    {
        $this->groupStack[] = ['prefix' => rtrim($prefix, '/'), 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groupStack);
    }

    public function add(string $method, string $path, $handler, array $middleware = []): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }
        $fullPath = $this->normalize($prefix . '/' . ltrim($path, '/'));
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'regex' => $this->buildRegex($fullPath),
        ];
    }

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function any(string $path, $handler, array $middleware = []): void
    {
        foreach (['GET','POST','PUT','DELETE'] as $m) {
            $this->add($m, $path, $handler, $middleware);
        }
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    public function dispatch(Request $request, Container $container)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if ($route['path'] === $request->path) {
                return $this->run($route, $request, $container);
            }
            if ($route['regex'] && preg_match($route['regex'], $request->path, $matches)) {
                $request->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->run($route, $request, $container);
            }
        }
        if ($this->notFound) {
            return ($this->notFound)($request);
        }
        return null;
    }

    private function run(array $route, Request $request, Container $container)
    {
        $handler = $route['handler'];
        $middleware = $route['middleware'];

        $runner = array_reduce(
            array_reverse($middleware),
            function ($next, $mw) use ($container) {
                return function (Request $req) use ($mw, $next, $container) {
                    return $mw($req, $next, $container);
                };
            },
            function (Request $req) use ($handler, $container) {
                if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
                    $controller = new $handler[0]($container);
                    return $controller->{$handler[1]}($req);
                }
                if ($handler instanceof Closure) {
                    return $handler($req);
                }
                throw new \RuntimeException('Invalid route handler');
            }
        );

        return $runner($request);
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }

    private function buildRegex(string $path): ?string
    {
        if (!str_contains($path, '{')) {
            return null;
        }
        $pattern = preg_replace('#\{([^}/]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
