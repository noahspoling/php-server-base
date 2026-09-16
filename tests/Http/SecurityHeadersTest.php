<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Middleware\SecurityHeaders;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_it_leaves_the_body_and_status_alone(): void
    {
        $response = $this->handle(Response::html('page', 201));

        self::assertSame('page', $response->body);
        self::assertSame(201, $response->status);
    }

    public function test_it_sets_nosniff(): void
    {
        self::assertSame('nosniff', $this->handle(Response::html('page'))->header('X-Content-Type-Options'));
    }

    public function test_it_sets_a_referrer_policy(): void
    {
        self::assertSame(
            'strict-origin-when-cross-origin',
            $this->handle(Response::html('page'))->header('Referrer-Policy'),
        );
    }

    public function test_the_csp_confines_everything_to_the_same_origin(): void
    {
        $csp = $this->handle(Response::html('page'))->header('Content-Security-Policy');

        self::assertNotNull($csp);
        self::assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_the_csp_allows_no_eval_because_assets_are_vendored(): void
    {
        $csp = (string) $this->handle(Response::html('page'))->header('Content-Security-Policy');

        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_a_header_the_application_already_set_is_not_overwritten(): void
    {
        $response = $this->handle(
            Response::html('page')->withHeader('Content-Security-Policy', "default-src 'none'"),
        );

        self::assertSame("default-src 'none'", $response->header('Content-Security-Policy'));
    }

    private function handle(Response $response): Response
    {
        return (new SecurityHeaders())->process(
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            static fn (Request $request): Response => $response,
        );
    }
}
