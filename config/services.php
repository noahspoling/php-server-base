<?php

declare(strict_types=1);

use App\Container\Container;
use App\Http\Middleware\ErrorHandler;
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
