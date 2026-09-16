<?php

declare(strict_types=1);

use App\Http\Middleware\ErrorHandler;
use App\Http\Middleware\RouteDispatcher;
use App\Http\Middleware\SecurityHeaders;

/**
 * The pipeline, outermost first.
 *
 * ErrorHandler is first so it wraps everything after it. RouteDispatcher is
 * last because it is terminal: it always returns a response and never calls
 * the next link.
 */
return [
    ErrorHandler::class,
    SecurityHeaders::class,
    RouteDispatcher::class,
];
