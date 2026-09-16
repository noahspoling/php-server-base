<?php

declare(strict_types=1);

namespace App\Controller;

use App\View\View;

/**
 * Base for controllers.
 *
 * Note what is absent: no Renderer, no output buffer, no echo. A controller
 * names a template and hands over data; turning that into HTML is the view
 * layer's job, and RouteDispatcher's to arrange.
 */
abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = []): View
    {
        return new View($template, $data);
    }
}
