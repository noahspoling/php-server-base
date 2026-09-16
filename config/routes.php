<?php

declare(strict_types=1);

use App\Controller\HomeController;
use App\Controller\UserController;

/**
 * Routes, keyed by "METHOD /path".
 *
 * {placeholders} capture a single path segment and arrive on the request as
 * params. Paths match exactly: a trailing slash is a different route.
 */
return [
    'GET  /' => [HomeController::class, 'index'],
    'GET  /users' => [UserController::class, 'index'],
    'GET  /users/rows' => [UserController::class, 'rows'],
    'POST /users' => [UserController::class, 'store'],
];
