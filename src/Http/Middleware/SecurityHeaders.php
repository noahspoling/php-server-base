<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class SecurityHeaders implements Middleware
{
    /**
     * Every script and stylesheet is vendored and served from this origin, so
     * the policy needs no host allowlist.
     *
     * 'unsafe-eval' is deliberately absent. htmx compiles hx-on: handlers and
     * event filters with new Function, so those two features do not work under
     * this policy — put behaviour in hyperscript's _ attributes instead, which
     * parse their own language. An application that needs hx-on: can rebind
     * this middleware with a looser policy and accept the tradeoff.
     */
    private const DEFAULTS = [
        'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach (self::DEFAULTS as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
