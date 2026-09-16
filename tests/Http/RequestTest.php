<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_it_reads_the_method_and_path_from_the_server_array(): void
    {
        $request = Request::fromGlobals(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users']);

        self::assertSame('POST', $request->method);
        self::assertSame('/users', $request->path);
    }

    public function test_the_path_excludes_the_query_string(): void
    {
        $request = Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users?page=2&sort=name']);

        self::assertSame('/users', $request->path);
    }

    public function test_it_normalises_header_names_to_lowercase_dashed_form(): void
    {
        $request = Request::fromGlobals([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HX_REQUEST' => 'true',
            'HTTP_CONTENT_TYPE' => 'text/html',
        ]);

        self::assertSame('true', $request->header('hx-request'));
        self::assertSame('text/html', $request->header('content-type'));
    }

    public function test_header_lookup_is_case_insensitive_and_defaults_to_null(): void
    {
        $request = Request::fromGlobals([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HX_REQUEST' => 'true',
        ]);

        self::assertSame('true', $request->header('HX-Request'));
        self::assertNull($request->header('x-absent'));
    }

    public function test_is_htmx_is_true_only_for_the_literal_true_value(): void
    {
        self::assertTrue($this->withHeaders(['HTTP_HX_REQUEST' => 'true'])->isHtmx());
        self::assertFalse($this->withHeaders(['HTTP_HX_REQUEST' => 'false'])->isHtmx());
        self::assertFalse($this->withHeaders([])->isHtmx());
    }

    public function test_it_exposes_the_boosted_and_history_restore_signals_separately(): void
    {
        self::assertTrue($this->withHeaders(['HTTP_HX_BOOSTED' => 'true'])->isBoosted());
        self::assertFalse($this->withHeaders([])->isBoosted());

        self::assertTrue($this->withHeaders(['HTTP_HX_HISTORY_RESTORE_REQUEST' => 'true'])->isHistoryRestore());
        self::assertFalse($this->withHeaders([])->isHistoryRestore());
    }

    public function test_with_params_returns_a_new_request_and_leaves_the_original_alone(): void
    {
        $request = Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']);

        $withParams = $request->withParams(['id' => '42']);

        self::assertSame([], $request->params);
        self::assertSame(['id' => '42'], $withParams->params);
        self::assertNotSame($request, $withParams);
    }

    public function test_it_carries_query_and_post_data(): void
    {
        $request = Request::fromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users?page=2'],
            ['page' => '2'],
            ['name' => 'Ada'],
        );

        self::assertSame('2', $request->query['page']);
        self::assertSame('Ada', $request->post['name']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function withHeaders(array $headers): Request
    {
        return Request::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'] + $headers);
    }
}
