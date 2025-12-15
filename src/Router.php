<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, placeholders:array<int,string>, handler:callable}>> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        [$regex, $placeholders] = $this->compilePattern($pattern);
        $this->routes[$method][] = [
            'pattern' => $regex,
            'placeholders' => $placeholders,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $args = [];
                foreach ($route['placeholders'] as $index => $name) {
                    $args[$name] = $matches[$index + 1] ?? null;
                }
                echo call_user_func($route['handler'], $args);
                return;
            }
        }

        http_response_code(404);
        echo 'Not Found';
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function compilePattern(string $pattern): array
    {
        $parts = explode('/', trim($pattern, '/'));
        $placeholders = [];

        $regexParts = array_map(function (string $part) use (&$placeholders): string {
            if (preg_match('/^{(.+)}$/', $part, $m)) {
                $placeholders[] = $m[1];
                return '([^/]+)';
            }
            return preg_quote($part, '~');
        }, $parts);

        $regex = '~^/' . implode('/', $regexParts) . '$~';
        return [$regex, $placeholders];
    }
}

