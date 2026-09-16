<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escapes a value for interpolation into HTML, including inside a quoted
     * attribute.
     *
     * ENT_SUBSTITUTE matters: without it htmlspecialchars returns an empty
     * string for malformed UTF-8, which silently blanks content instead of
     * showing a replacement character.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
