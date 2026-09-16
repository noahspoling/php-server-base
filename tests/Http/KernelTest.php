<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Container\Container;
use App\Http\Kernel;
use App\Http\Middleware\Middleware;
use App\Http\Request;
use App\Http\Response;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    public function test_middleware_run_outermost_first_and_unwind_in_reverse(): void
    {
        $container = new Container([]);

        $this->kernel($container, [Outer::class, Inner::class, Terminal::class])->handle($this->request());

        self::assertSame(
            ['outer-before', 'inner-before', 'terminal', 'inner-after', 'outer-after'],
            $container->get(Recorder::class)->calls,
        );
    }

    public function test_a_middleware_that_does_not_call_next_short_circuits_the_rest(): void
    {
        $container = new Container([]);

        $response = $this->kernel($container, [ShortCircuit::class, Terminal::class])->handle($this->request());

        self::assertSame('short-circuited', $response->body);
        self::assertSame([], $container->get(Recorder::class)->calls);
    }

    public function test_an_outer_middleware_can_decorate_the_response_from_an_inner_one(): void
    {
        $response = $this->kernel(new Container([]), [Stamping::class, Terminal::class])->handle($this->request());

        self::assertSame('yes', $response->header('X-Stamped'));
        self::assertSame('terminal', $response->body);
    }

    public function test_a_pipeline_that_never_returns_a_response_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('without returning a response');

        $this->kernel(new Container([]), [Outer::class])->handle($this->request());
    }

    /**
     * @param list<class-string<Middleware>> $middleware
     */
    private function kernel(Container $container, array $middleware): Kernel
    {
        return new Kernel($middleware, $container);
    }

    private function request(): Request
    {
        return new Request('GET', '/');
    }
}

final class Recorder
{
    /** @var list<string> */
    public array $calls = [];
}

final class Outer implements Middleware
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->recorder->calls[] = 'outer-before';
        $response = $next($request);
        $this->recorder->calls[] = 'outer-after';

        return $response;
    }
}

final class Inner implements Middleware
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->recorder->calls[] = 'inner-before';
        $response = $next($request);
        $this->recorder->calls[] = 'inner-after';

        return $response;
    }
}

final class Terminal implements Middleware
{
    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->recorder->calls[] = 'terminal';

        return Response::html('terminal');
    }
}

final class ShortCircuit implements Middleware
{
    public function process(Request $request, callable $next): Response
    {
        return Response::html('short-circuited');
    }
}

final class Stamping implements Middleware
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Stamped', 'yes');
    }
}
