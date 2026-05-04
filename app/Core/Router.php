<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, array|callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array|callable $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            $regex = $this->compile($route['pattern'], $params);
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $args = [];
            foreach ($params as $param) {
                $args[] = $matches[$param] ?? null;
            }

            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler[0] = new $handler[0]();
            }

            echo call_user_func_array($handler, $args);
            return;
        }

        http_response_code(404);
        echo (new \App\Controllers\SiteController())->notFound();
    }

    private function compile(string $pattern, array &$params): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)}/', static function (array $match) use (&$params): string {
            $params[] = $match[1];
            return '(?P<' . $match[1] . '>[^/]+)';
        }, $pattern);

        return '#^' . $regex . '$#';
    }
}
