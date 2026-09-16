<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /**
     * @param array<string, array{string, string}> $routes Keyed by "METHOD /path".
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function match(string $method, string $path): ?Route
    {
        foreach ($this->routes as $key => [$controller, $action]) {
            [$routeMethod, $routePath] = preg_split('/\s+/', trim($key), 2);

            if ($routeMethod !== $method) {
                continue;
            }

            $params = $this->matchPath($routePath, $path);

            if ($params === null) {
                continue;
            }

            return new Route($controller, $action, $params);
        }

        return null;
    }

    /**
     * @return array<string, string>|null Captured params, or null when the path does not match.
     */
    private function matchPath(string $routePath, string $path): ?array
    {
        if (preg_match($this->compile($routePath), $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Turns "/users/{id}" into an anchored pattern with a named group per
     * placeholder. Literal segments are quoted, so a route containing regex
     * metacharacters still matches literally. Placeholders stop at "/", so
     * they never span a segment boundary.
     */
    private function compile(string $routePath): string
    {
        $pattern = '';

        $parts = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $routePath,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        foreach ($parts as $part) {
            $pattern .= preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $name) === 1
                ? '(?P<' . $name[1] . '>[^/]+)'
                : preg_quote($part, '#');
        }

        return '#^' . $pattern . '$#';
    }
}
