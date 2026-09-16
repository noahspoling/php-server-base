<?php

declare(strict_types=1);

use App\Container\Container;
use App\Http\Middleware\ErrorHandler;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Router;
use App\View\Assets;
use App\View\Renderer;

/**
 * Container bindings.
 *
 * Only things the container cannot work out on its own need an entry here:
 * anything taking a scalar, a path, or a value from the environment.
 * Controllers and repositories are autowired from their type hints.
 */

$root = dirname(__DIR__);

$env = static fn (string $key, string $default = ''): string => (string) (getenv($key) ?: $default);

return [
    Assets::class => static fn (Container $c): Assets => new Assets(
        $root . '/www/static',
        require __DIR__ . '/assets.php',
        '/static',
    ),

    Renderer::class => static fn (Container $c): Renderer => new Renderer(
        $root . '/views',
        $c->get(Assets::class),
    ),

    Router::class => static fn (Container $c): Router => new Router(require __DIR__ . '/routes.php'),

    ErrorHandler::class => static fn (Container $c): ErrorHandler => new ErrorHandler(
        filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    ),

    SecurityHeaders::class => static fn (Container $c): SecurityHeaders => new SecurityHeaders(
        // htmx compiles hx-on: handlers with new Function, which CSP blocks
        // without 'unsafe-eval'. Turning this on re-enables eval for the whole
        // origin — behaviour that can live in hyperscript's _ attributes does
        // not need it.
        allowEval: filter_var($env('CSP_ALLOW_EVAL', 'true'), FILTER_VALIDATE_BOOL),

        // Google Fonts serves the icon stylesheet from one host and the font
        // files it references from another, so both are needed for Material
        // Icons to render. Drop these once the icons are vendored.
        styleSrc: ['https://fonts.googleapis.com'],
        fontSrc: ['https://fonts.gstatic.com'],
    ),

    PDO::class => static fn (Container $c): PDO => new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env('DB_HOST', 'mysql'),
            $env('DB_PORT', '3306'),
            $env('DB_NAME', 'lamp_db'),
        ),
        $env('DB_USER', 'lamp_user'),
        $env('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, so parameters are never interpolated
            // into SQL by the driver.
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ),
];
