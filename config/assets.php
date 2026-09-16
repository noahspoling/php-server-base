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
        // Font Awesome first, so base.css and application styles can override it.
        'font-awesome/css/font-awesome.min.css',
        'base.css',
    ],
    'js' => [
        'htmx.min.js',
        '_hyperscript.min.js',
        // Checks the vendored Font Awesome actually applied, and pulls the CDN
        // copy if it did not. See www/static/fa-fallback.js.
        'fa-fallback.js',
    ],
];
