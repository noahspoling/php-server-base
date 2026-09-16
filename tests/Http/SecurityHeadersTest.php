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

    public function test_the_default_policy_allows_no_eval(): void
    {
        $csp = (string) $this->handle(Response::html('page'))->header('Content-Security-Policy');

        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_allowing_eval_adds_it_to_script_src_for_htmx_hx_on(): void
    {
        $csp = (string) $this->handle(Response::html('page'), allowEval: true)->header('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
    }

    public function test_allowing_eval_does_not_also_allow_inline_scripts(): void
    {
        $csp = (string) $this->handle(Response::html('page'), allowEval: true)->header('Content-Security-Policy');

        self::assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_allowing_eval_leaves_the_rest_of_the_policy_intact(): void
    {
        $csp = (string) $this->handle(Response::html('page'), allowEval: true)->header('Content-Security-Policy');

        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_a_header_the_application_already_set_is_not_overwritten(): void
    {
        $response = $this->handle(
            Response::html('page')->withHeader('Content-Security-Policy', "default-src 'none'"),
        );

        self::assertSame("default-src 'none'", $response->header('Content-Security-Policy'));
    }

    public function test_no_style_or_font_directive_is_emitted_when_nothing_external_is_allowed(): void
    {
        $csp = (string) $this->handle(Response::html('page'))->header('Content-Security-Policy');

        self::assertStringNotContainsString('style-src', $csp);
        self::assertStringNotContainsString('font-src', $csp);
    }

    public function test_extra_style_sources_are_added_alongside_self(): void
    {
        $csp = (string) $this->handle(
            Response::html('page'),
            styleSrc: ['https://fonts.googleapis.com'],
        )->header('Content-Security-Policy');

        self::assertStringContainsString("style-src 'self' https://fonts.googleapis.com", $csp);
    }

    public function test_extra_font_sources_are_added_alongside_self(): void
    {
        $csp = (string) $this->handle(
            Response::html('page'),
            fontSrc: ['https://fonts.gstatic.com'],
        )->header('Content-Security-Policy');

        self::assertStringContainsString("font-src 'self' https://fonts.gstatic.com", $csp);
    }

    public function test_several_hosts_are_listed_in_order(): void
    {
        $csp = (string) $this->handle(
            Response::html('page'),
            styleSrc: ['https://fonts.googleapis.com', 'https://cdnjs.cloudflare.com'],
        )->header('Content-Security-Policy');

        self::assertStringContainsString(
            "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            $csp,
        );
    }

    /**
     * @param list<string> $styleSrc
     * @param list<string> $fontSrc
     */
    private function handle(
        Response $response,
        bool $allowEval = false,
        array $styleSrc = [],
        array $fontSrc = [],
    ): Response {
        return (new SecurityHeaders($allowEval, $styleSrc, $fontSrc))->process(
            Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            static fn (Request $request): Response => $response,
        );
    }
}
