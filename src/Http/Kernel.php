<?php

declare(strict_types=1);

namespace App\Http;

use App\Container\Container;
use App\Http\Middleware\Middleware;
use LogicException;

final class Kernel
{
    /**
     * @param list<class-string<Middleware>> $middleware Outermost first.
     */
    public function __construct(
        private readonly array $middleware,
        private readonly Container $container,
    ) {
    }

    public function handle(Request $request): Response
    {
        return ($this->pipeline())($request);
    }

    /**
     * Folds the middleware list into a chain of closures. Reversing the list
     * means the first entry ends up outermost, so it runs first on the way in
     * and last on the way out.
     *
     * @return callable(Request): Response
     */
    private function pipeline(): callable
    {
        $exhausted = static fn (Request $request): Response => throw new LogicException(
            'Middleware pipeline ended without returning a response. The last middleware must not call $next.',
        );

        return array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, string $class): callable => function (Request $request) use ($class, $next): Response {
                /** @var Middleware $middleware */
                $middleware = $this->container->get($class);

                return $middleware->process($request, $next);
            },
            $exhausted,
        );
    }
}
