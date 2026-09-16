<?php

declare(strict_types=1);

namespace App\Tests\View;

use App\Http\Request;
use App\View\Assets;
use App\View\Renderer;
use App\View\View;
use App\View\ViewException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RendererTest extends TestCase
{
    private string $root;
    private string $viewDir;
    private string $staticDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/renderer-test-' . bin2hex(random_bytes(6));
        $this->viewDir = $this->root . '/views';
        $this->staticDir = $this->root . '/static';

        mkdir($this->viewDir, 0777, true);
        mkdir($this->staticDir, 0777, true);

        file_put_contents($this->staticDir . '/base.css', 'body{}');

        $this->writeView('layout', '<!doctype html><title><?= e($title) ?></title>'
            . '<?= $assets->head() ?><body><?= $content ?></body>');
        $this->writeView('greeting', '<p>Hello <?= e($name) ?></p>');
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function test_a_plain_request_is_wrapped_in_the_layout(): void
    {
        $response = $this->render(new View('greeting', ['name' => 'Ada']), $this->request());

        self::assertStringContainsString('<!doctype html>', $response->body);
        self::assertStringContainsString('<p>Hello Ada</p>', $response->body);
    }

    public function test_an_htmx_request_returns_only_the_fragment(): void
    {
        $response = $this->render(
            new View('greeting', ['name' => 'Ada']),
            $this->request(['HTTP_HX_REQUEST' => 'true']),
        );

        self::assertSame('<p>Hello Ada</p>', $response->body);
    }

    public function test_a_boosted_request_is_wrapped_despite_carrying_the_htmx_header(): void
    {
        $response = $this->render(
            new View('greeting', ['name' => 'Ada']),
            $this->request(['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_BOOSTED' => 'true']),
        );

        self::assertStringContainsString('<!doctype html>', $response->body);
    }

    public function test_a_history_restore_request_is_wrapped_despite_carrying_the_htmx_header(): void
    {
        $response = $this->render(
            new View('greeting', ['name' => 'Ada']),
            $this->request(['HTTP_HX_REQUEST' => 'true', 'HTTP_HX_HISTORY_RESTORE_REQUEST' => 'true']),
        );

        self::assertStringContainsString('<!doctype html>', $response->body);
    }

    public function test_the_response_varies_on_every_signal_that_changes_the_shape(): void
    {
        $vary = $this->render(new View('greeting', ['name' => 'Ada']), $this->request())->header('Vary');

        self::assertNotNull($vary);
        self::assertStringContainsString('HX-Request', $vary);
        self::assertStringContainsString('HX-Boosted', $vary);
        self::assertStringContainsString('HX-History-Restore-Request', $vary);
    }

    public function test_the_response_is_html(): void
    {
        $response = $this->render(new View('greeting', ['name' => 'Ada']), $this->request());

        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
    }

    public function test_the_layout_renders_the_checksummed_static_section(): void
    {
        $response = $this->render(new View('greeting', ['name' => 'Ada']), $this->request());

        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/static/base\.[0-9a-f]{8}\.css">#', $response->body);
    }

    public function test_the_title_comes_from_view_data(): void
    {
        $response = $this->render(new View('greeting', ['name' => 'Ada', 'title' => 'People']), $this->request());

        self::assertStringContainsString('<title>People</title>', $response->body);
    }

    public function test_an_unknown_template_throws(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('missing');

        $this->render(new View('missing'), $this->request());
    }

    public function test_a_template_outside_the_view_directory_throws(): void
    {
        file_put_contents($this->root . '/outside.php', 'secret');

        $this->expectException(ViewException::class);

        $this->render(new View('../outside'), $this->request());
    }

    public function test_the_output_buffer_is_discarded_when_a_template_throws(): void
    {
        $this->writeView('boom', 'partial output<?php throw new \RuntimeException("template exploded");');

        $levelBefore = ob_get_level();

        try {
            $this->render(new View('boom'), $this->request());
            self::fail('Expected the template exception to propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('template exploded', $e->getMessage());
        }

        self::assertSame($levelBefore, ob_get_level(), 'Renderer left an output buffer open.');
    }

    private function render(View $view, Request $request): \App\Http\Response
    {
        return $this->renderer()->render($view, $request);
    }

    private function renderer(): Renderer
    {
        return new Renderer($this->viewDir, new Assets($this->staticDir, ['css' => ['base.css']]));
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): Request
    {
        return Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'] + $headers);
    }

    private function writeView(string $name, string $contents): void
    {
        file_put_contents($this->viewDir . '/' . $name . '.php', $contents);
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
