<?php

declare(strict_types=1);

namespace App\Http;

final class Route
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly string $controller,
        public readonly string $action,
        public readonly array $params = [],
    ) {
    }
}
