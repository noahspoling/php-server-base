<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

interface Middleware
{
    /**
     * @param callable(Request): Response $next The rest of the pipeline.
     */
    public function process(Request $request, callable $next): Response;
}
