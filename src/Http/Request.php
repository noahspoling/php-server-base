<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, string>               $query
     * @param array<string, string>               $post
     * @param array<string, string>               $headers Lowercase dashed names.
     * @param array<string, string>               $params  Route placeholders.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $headers = [],
        public readonly array $params = [],
    ) {
    }

    /**
     * @param array<string, mixed>|null $server Defaults to $_SERVER; injectable for tests.
     * @param array<string, string>|null $query
     * @param array<string, string>|null $post
     */
    public static function fromGlobals(?array $server = null, ?array $query = null, ?array $post = null): self
    {
        $server ??= $_SERVER;

        return new self(
            method: (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            path: self::pathFrom((string) ($server['REQUEST_URI'] ?? '/')),
            query: $query ?? $_GET,
            post: $post ?? $_POST,
            headers: self::headersFrom($server),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isHtmx(): bool
    {
        return $this->header('hx-request') === 'true';
    }

    public function isBoosted(): bool
    {
        return $this->header('hx-boosted') === 'true';
    }

    public function isHistoryRestore(): bool
    {
        return $this->header('hx-history-restore-request') === 'true';
    }

    /**
     * @param array<string, string> $params
     */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->post, $this->headers, $params);
    }

    private static function pathFrom(string $uri): string
    {
        $path = strtok($uri, '?');

        return $path === false ? '/' : $path;
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private static function headersFrom(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = (string) $value;
        }

        return $headers;
    }
}
