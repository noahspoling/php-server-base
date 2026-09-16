<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class SecurityHeaders implements Middleware
{
    /**
     * Everything the base ships is vendored and same-origin, so the starting
     * policy needs no host allowlist at all.
     */
    private const BASE = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    /**
     * @param bool         $allowEval Adds 'unsafe-eval' to script-src, which htmx
     *                                needs for hx-on: handlers and event filters,
     *                                because it compiles those attributes with
     *                                new Function.
     *
     *                                This re-enables eval for the whole origin,
     *                                so any string reaching a script sink becomes
     *                                executable. It defaults to false so it is
     *                                never on by accident; opting in happens in
     *                                config/services.php where it is visible.
     *
     *                                'unsafe-inline' is deliberately not implied.
     *                                hx-on: is read by htmx rather than by the
     *                                browser as a native handler, so inline
     *                                scripts and on* attributes stay blocked.
     * @param list<string> $styleSrc  Extra origins allowed to serve stylesheets,
     *                                for CDN-hosted icon fonts and similar.
     * @param list<string> $fontSrc   Extra origins allowed to serve font files.
     *                                Usually a different host from the stylesheet
     *                                that references them.
     */
    public function __construct(
        private readonly bool $allowEval = false,
        private readonly array $styleSrc = [],
        private readonly array $fontSrc = [],
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach ($this->defaults() as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'Content-Security-Policy' => $this->policy(),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
    }

    private function policy(): string
    {
        $directives = self::BASE;

        if ($this->allowEval) {
            $directives[] = "script-src 'self' 'unsafe-eval'";
        }

        // Only emitted when there is something to allow. A directive listing
        // nothing but 'self' would be noise, since default-src already says it.
        if ($this->styleSrc !== []) {
            $directives[] = "style-src 'self' " . implode(' ', $this->styleSrc);
        }

        if ($this->fontSrc !== []) {
            $directives[] = "font-src 'self' " . implode(' ', $this->fontSrc);
        }

        return implode('; ', $directives);
    }
}
