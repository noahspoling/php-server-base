<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_it_matches_a_literal_path_and_returns_the_handler(): void
    {
        $route = $this->router(['GET /users' => ['UserController', 'index']])->match('GET', '/users');

        self::assertNotNull($route);
        self::assertSame('UserController', $route->controller);
        self::assertSame('index', $route->action);
        self::assertSame([], $route->params);
    }

    public function test_a_head_request_matches_a_get_route(): void
    {
        $route = $this->router(['GET /users' => ['UserController', 'index']])->match('HEAD', '/users');

        self::assertNotNull($route, 'HEAD must be served wherever GET is.');
        self::assertSame('index', $route->action);
    }

    public function test_a_head_request_does_not_match_a_post_route(): void
    {
        $router = $this->router(['POST /users' => ['UserController', 'store']]);

        self::assertNull($router->match('HEAD', '/users'));
    }

    public function test_an_explicit_head_route_still_wins(): void
    {
        $route = $this->router([
            'HEAD /users' => ['UserController', 'head'],
            'GET /users' => ['UserController', 'index'],
        ])->match('HEAD', '/users');

        self::assertNotNull($route);
        self::assertSame('head', $route->action);
    }

    public function test_it_returns_null_when_no_path_matches(): void
    {
        $router = $this->router(['GET /users' => ['UserController', 'index']]);

        self::assertNull($router->match('GET', '/nope'));
    }

    public function test_it_returns_null_when_the_path_matches_but_the_method_does_not(): void
    {
        $router = $this->router(['GET /users' => ['UserController', 'index']]);

        self::assertNull($router->match('POST', '/users'));
    }

    public function test_it_tolerates_padding_whitespace_in_route_keys(): void
    {
        $route = $this->router(['GET   /users' => ['UserController', 'index']])->match('GET', '/users');

        self::assertNotNull($route);
        self::assertSame('index', $route->action);
    }

    public function test_it_extracts_placeholder_values_as_params(): void
    {
        $route = $this->router(['GET /users/{id}' => ['UserController', 'show']])->match('GET', '/users/42');

        self::assertNotNull($route);
        self::assertSame('show', $route->action);
        self::assertSame(['id' => '42'], $route->params);
    }

    public function test_it_extracts_every_placeholder_in_a_multi_segment_path(): void
    {
        $route = $this->router(['GET /users/{id}/posts/{slug}' => ['PostController', 'show']])
            ->match('GET', '/users/42/posts/hello-world');

        self::assertNotNull($route);
        self::assertSame(['id' => '42', 'slug' => 'hello-world'], $route->params);
    }

    public function test_a_placeholder_does_not_match_across_a_segment_boundary(): void
    {
        $router = $this->router(['GET /users/{id}' => ['UserController', 'show']]);

        self::assertNull($router->match('GET', '/users/42/edit'));
    }

    public function test_a_trailing_slash_is_a_different_route(): void
    {
        $router = $this->router(['GET /users' => ['UserController', 'index']]);

        self::assertNull($router->match('GET', '/users/'));
    }

    public function test_a_literal_route_containing_regex_characters_is_matched_literally(): void
    {
        $router = $this->router(['GET /a.c' => ['DotController', 'index']]);

        self::assertNull($router->match('GET', '/abc'));
        self::assertNotNull($router->match('GET', '/a.c'));
    }

    /**
     * @param array<string, array{string, string}> $routes
     */
    private function router(array $routes): Router
    {
        return new Router($routes);
    }
}
