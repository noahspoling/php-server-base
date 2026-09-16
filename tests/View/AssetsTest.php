<?php

declare(strict_types=1);

namespace App\Tests\View;

use App\View\AssetException;
use App\View\Assets;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/assets-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->dir);
    }

    public function test_url_embeds_an_eight_hex_checksum_before_the_extension(): void
    {
        $this->writeAsset('base.css', 'body { color: red }');

        $url = $this->assets()->url('base.css');

        self::assertMatchesRegularExpression('#^/static/base\.[0-9a-f]{8}\.css$#', $url);
    }

    public function test_url_keeps_subdirectories_ahead_of_the_checksum(): void
    {
        mkdir($this->dir . '/img');
        $this->writeAsset('img/logo.svg', '<svg></svg>');

        $url = $this->assets()->url('img/logo.svg');

        self::assertMatchesRegularExpression('#^/static/img/logo\.[0-9a-f]{8}\.svg$#', $url);
    }

    public function test_different_content_produces_a_different_checksum(): void
    {
        $this->writeAsset('a.css', 'one');
        $this->writeAsset('b.css', 'two');

        $assets = $this->assets();

        self::assertNotSame(
            $this->checksumOf($assets->url('a.css')),
            $this->checksumOf($assets->url('b.css')),
        );
    }

    public function test_url_is_memoized_so_it_stays_stable_within_one_request(): void
    {
        $this->writeAsset('base.css', 'before');
        $assets = $this->assets();
        $first = $assets->url('base.css');

        $this->writeAsset('base.css', 'after');

        self::assertSame($first, $assets->url('base.css'));
    }

    public function test_a_later_request_picks_up_changed_content(): void
    {
        $this->writeAsset('base.css', 'before');
        $before = $this->assets()->url('base.css');

        $this->writeAsset('base.css', 'after');

        self::assertNotSame($before, $this->assets()->url('base.css'));
    }

    public function test_an_unknown_asset_throws_rather_than_emitting_a_dead_url(): void
    {
        $this->expectException(AssetException::class);
        $this->expectExceptionMessage('Unknown asset: missing.css');

        $this->assets()->url('missing.css');
    }

    public function test_a_path_escaping_the_static_directory_throws(): void
    {
        file_put_contents(dirname($this->dir) . '/outside.css', 'secret');

        try {
            $this->expectException(AssetException::class);

            $this->assets()->url('../outside.css');
        } finally {
            unlink(dirname($this->dir) . '/outside.css');
        }
    }

    public function test_head_emits_the_manifest_as_one_block_stylesheets_before_deferred_scripts(): void
    {
        $this->writeAsset('base.css', 'body{}');
        $this->writeAsset('htmx.min.js', 'htmx');
        $this->writeAsset('_hyperscript.min.js', 'hyperscript');

        $head = $this->assets([
            'css' => ['base.css'],
            'js' => ['htmx.min.js', '_hyperscript.min.js'],
        ])->head();

        self::assertMatchesRegularExpression(
            '#^<link rel="stylesheet" href="/static/base\.[0-9a-f]{8}\.css">\n'
            . '<script src="/static/htmx\.min\.[0-9a-f]{8}\.js" defer></script>\n'
            . '<script src="/static/_hyperscript\.min\.[0-9a-f]{8}\.js" defer></script>$#',
            $head,
        );
    }

    public function test_head_is_empty_when_the_manifest_is_empty(): void
    {
        self::assertSame('', $this->assets()->head());
    }

    private function assets(array $manifest = []): Assets
    {
        return new Assets($this->dir, $manifest, '/static');
    }

    private function writeAsset(string $name, string $contents): void
    {
        file_put_contents($this->dir . '/' . $name, $contents);
    }

    private function checksumOf(string $url): string
    {
        $parts = explode('.', $url);

        return $parts[count($parts) - 2];
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
