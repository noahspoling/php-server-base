<?php

declare(strict_types=1);

/**
 * The static section, rendered as one block by App\View\Assets::head().
 *
 * Paths are relative to www/static. Stylesheets are emitted first, then
 * scripts with defer, each in the order listed here.
 */
return [
    'css' => [
        'base.css',
    ],
    'js' => [
        'htmx.min.js',
        '_hyperscript.min.js',
    ],
];
