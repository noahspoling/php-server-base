<?php

declare(strict_types=1);

namespace App\View;

/**
 * What a controller returns: the name of a template and the data it needs.
 *
 * Deliberately inert. It holds no renderer and has no render method, so a
 * controller has no way to produce HTML.
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $template,
        public readonly array $data = [],
    ) {
    }
}
