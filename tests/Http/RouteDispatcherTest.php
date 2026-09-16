<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Container\Container;
use App\Http\Middleware\RouteDispatcher;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteNotFound;
use App\Http\Router;
use App\View\Assets;
use App\View\Renderer;
use App\View\View;
use PHPUnit\Framework\TestCase;

final class RouteDispatcherTest extends TestCase
{
    private string $root;
    private string $viewDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dispatcher-test-' . bin2hex(random_bytes(6));
        $this->viewDir = $this->root . '/views';

        mkdir($this->viewDir, 0777, true);
        mkdir($this->root . '/static', 0777, true);

        file_put_contents($this->viewDir . '/layout.php', '<!doctype html><body><?= $content ?></body>');
        file_put_contents($this->viewDir . '/greeting.php', '<p>Hello <?= e($name) ?></p>');
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function test_it_resolves_the_controller_and_renders_the_view_it_returns(): void
    {
        $response = $this->dispatch(
            ['GET /' => [StubController::class, 'page']],
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
        );

        self::assertStringContainsString('<p>Hello Ada</p>', $response->body);
        self::assertStringContainsString('<!doctype html>', $response->body);
    }

    public function test_a_controller_may_return_a_response_directly_and_it_passes_through(): void
    {
        $response = $this->dispatch(
            ['GET /go' => [StubController::class, 'redirectAway']],
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/go']),
        );

        self::assertSame(302, $response->status);
        self::assertSame('/elsewhere', $response->header('Location'));
    }

    public function test_route_params_reach_the_controller_on_the_request(): void
    {
        $response = $this->dispatch(
            ['GET /users/{id}' => [StubController::class, 'showParams']],
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']),
        );

        self::assertSame('{"id":"42"}', $response->body);
    }

    public function test_an_htmx_request_gets_the_fragment_without_the_layout(): void
    {
        $response = $this->dispatch(
            ['GET /' => [StubController::class, 'page']],
            Request::fromGlobals([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HX_REQUEST' => 'true',
            ]),
        );

        self::assertSame('<p>Hello Ada</p>', $response->body);
    }

    public function test_an_unmatched_route_throws_route_not_found(): void
    {
        $this->expectException(RouteNotFound::class);
        $this->expectExceptionMessage('GET /nope');

        $this->dispatch(
            ['GET /' => [StubController::class, 'page']],
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nope']),
        );
    }

    public function test_it_is_terminal_and_never_calls_the_rest_of_the_pipeline(): void
    {
        $router = new Router(['GET /' => [StubController::class, 'page']]);
        $dispatcher = new RouteDispatcher($router, new Container([]), $this->renderer());

        $nextWasCalled = false;
        $next = static function (Request $request) use (&$nextWasCalled): Response {
            $nextWasCalled = true;

            return Response::html('next');
        };

        $response = $dispatcher->process(
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            $next,
        );

        self::assertFalse($nextWasCalled, 'RouteDispatcher must not call $next.');
        self::assertStringContainsString('Hello Ada', $response->body);
    }

    /**
     * @param array<string, array{string, string}> $routes
     */
    private function dispatch(array $routes, Request $request): Response
    {
        $dispatcher = new RouteDispatcher(new Router($routes), new Container([]), $this->renderer());

        return $dispatcher->process($request, static fn (Request $r): Response => Response::html('next'));
    }

    private function renderer(): Renderer
    {
        return new Renderer($this->viewDir, new Assets($this->root . '/static'));
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

final class StubController
{
    public function page(Request $request): View
    {
        return new View('greeting', ['name' => 'Ada']);
    }

    public function redirectAway(Request $request): Response
    {
        return Response::redirect('/elsewhere');
    }

    public function showParams(Request $request): Response
    {
        return Response::html((string) json_encode($request->params));
    }
}
