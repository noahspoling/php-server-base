<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Middleware\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteNotFound;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    public function test_a_successful_response_passes_through_untouched(): void
    {
        $response = $this->handle(static fn (Request $r): Response => Response::html('fine'));

        self::assertSame(200, $response->status);
        self::assertSame('fine', $response->body);
    }

    public function test_an_unmatched_route_becomes_a_404(): void
    {
        $response = $this->handle(static fn (Request $r): Response => throw new RouteNotFound('No route for GET /nope'));

        self::assertSame(404, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
    }

    public function test_any_other_throwable_becomes_a_500(): void
    {
        $response = $this->handle(static fn (Request $r): Response => throw new RuntimeException('the database fell over'));

        self::assertSame(500, $response->status);
    }

    public function test_it_does_not_leak_the_exception_message_when_debug_is_off(): void
    {
        $response = $this->handle(
            static fn (Request $r): Response => throw new RuntimeException('SQLSTATE: password=hunter2'),
            debug: false,
        );

        self::assertStringNotContainsString('hunter2', $response->body);
        self::assertStringNotContainsString('SQLSTATE', $response->body);
    }

    public function test_it_shows_the_exception_when_debug_is_on(): void
    {
        $response = $this->handle(
            static fn (Request $r): Response => throw new RuntimeException('the database fell over'),
            debug: true,
        );

        self::assertStringContainsString('the database fell over', $response->body);
        self::assertStringContainsString('RuntimeException', $response->body);
    }

    public function test_it_escapes_the_exception_message_in_debug_output(): void
    {
        $response = $this->handle(
            static fn (Request $r): Response => throw new RuntimeException('<script>alert(1)</script>'),
            debug: true,
        );

        self::assertStringNotContainsString('<script>', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function test_it_logs_the_throwable_even_when_the_page_stays_generic(): void
    {
        $logged = [];

        $this->handle(
            static fn (Request $r): Response => throw new RuntimeException('the database fell over'),
            debug: false,
            log: static function (string $message) use (&$logged): void {
                $logged[] = $message;
            },
        );

        self::assertCount(1, $logged);
        self::assertStringContainsString('the database fell over', $logged[0]);
    }

    private function handle(callable $next, bool $debug = false, ?callable $log = null): Response
    {
        $handler = new ErrorHandler($debug, $log ?? static function (string $message): void {
        });

        return $handler->process(Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']), $next);
    }
}
