<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Container\Container;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteNotFound;
use App\Http\Router;
use App\View\Renderer;
use App\View\View;

/**
 * The terminal middleware: it always produces a response and never calls $next.
 *
 * This is also where the presentation boundary is enforced. A controller hands
 * back a View — a template name and data — and only this class knows how to
 * turn one into HTML.
 */
final class RouteDispatcher implements Middleware
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly Renderer $renderer,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $route = $this->router->match($request->method, $request->path);

        if ($route === null) {
            throw new RouteNotFound("No route for {$request->method} {$request->path}");
        }

        $controller = $this->container->get($route->controller);
        $result = $controller->{$route->action}($request->withParams($route->params));

        return $result instanceof View
            ? $this->renderer->render($result, $request)
            : $result;
    }
}
