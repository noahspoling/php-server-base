<?php

declare(strict_types=1);

namespace App\View;

final class Assets
{
    /** @var array<string, string> */
    private array $hashes = [];

    /** Canonical static directory, with a trailing separator. */
    private readonly string $root;

    /**
     * @param array{css?: list<string>, js?: list<string>} $manifest
     */
    public function __construct(
        string $dir,
        private readonly array $manifest = [],
        private readonly string $prefix = '/static',
    ) {
        $root = realpath($dir);

        if ($root === false) {
            throw new AssetException("Static directory does not exist: {$dir}");
        }

        $this->root = $root . DIRECTORY_SEPARATOR;
    }

    /**
     * Renders the whole static section declared in the manifest: stylesheets
     * first, then deferred scripts, each in declaration order.
     */
    public function head(): string
    {
        $tags = [];

        foreach ($this->manifest['css'] ?? [] as $path) {
            $tags[] = '<link rel="stylesheet" href="' . $this->url($path) . '">';
        }

        foreach ($this->manifest['js'] ?? [] as $path) {
            $tags[] = '<script src="' . $this->url($path) . '" defer></script>';
        }

        return implode("\n", $tags);
    }

    public function url(string $path): string
    {
        $dot = strrpos($path, '.');

        return $this->prefix . '/' . substr($path, 0, $dot) . '.' . $this->hash($path) . substr($path, $dot);
    }

    private function hash(string $path): string
    {
        return $this->hashes[$path] ??= $this->computeHash($path);
    }

    private function computeHash(string $path): string
    {
        $real = realpath($this->root . $path);

        if ($real === false || !str_starts_with($real, $this->root)) {
            throw new AssetException("Unknown asset: {$path}");
        }

        return substr(hash_file('xxh128', $real), 0, 8);
    }
}
