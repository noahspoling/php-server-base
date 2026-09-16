<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html_defaults_to_200_and_a_utf8_html_content_type(): void
    {
        $response = Response::html('<p>hi</p>');

        self::assertSame('<p>hi</p>', $response->body);
        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
    }

    public function test_html_accepts_an_explicit_status(): void
    {
        self::assertSame(404, Response::html('missing', 404)->status);
    }

    public function test_redirect_sets_the_location_header_and_a_302(): void
    {
        $response = Response::redirect('/users');

        self::assertSame(302, $response->status);
        self::assertSame('/users', $response->header('Location'));
    }

    public function test_redirect_accepts_an_explicit_status(): void
    {
        self::assertSame(303, Response::redirect('/users', 303)->status);
    }

    public function test_header_lookup_is_case_insensitive(): void
    {
        $response = Response::html('hi');

        self::assertSame('text/html; charset=utf-8', $response->header('content-type'));
        self::assertSame('text/html; charset=utf-8', $response->header('CONTENT-TYPE'));
    }

    public function test_with_header_returns_a_new_response_and_leaves_the_original_alone(): void
    {
        $response = Response::html('hi');

        $varied = $response->withHeader('Vary', 'HX-Request');

        self::assertNull($response->header('Vary'));
        self::assertSame('HX-Request', $varied->header('Vary'));
        self::assertNotSame($response, $varied);
    }

    public function test_with_header_replaces_a_header_regardless_of_the_case_used(): void
    {
        $response = Response::html('hi')->withHeader('content-TYPE', 'text/plain');

        self::assertSame('text/plain', $response->header('Content-Type'));
        self::assertCount(1, $response->headers);
    }

    public function test_send_writes_the_body(): void
    {
        ob_start();
        Response::html('<p>sent</p>')->send();

        self::assertSame('<p>sent</p>', ob_get_clean());
    }
}
