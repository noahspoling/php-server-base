<?php

declare(strict_types=1);

use App\Container\Container;
use App\Http\Kernel;
use App\Http\Request;

/**
 * The only PHP file in the document root.
 *
 * Everything else — src/, views/, config/, vendor/ — lives outside the webroot
 * and is reachable only through the filesystem, never by URL.
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$container = new Container(require $root . '/config/services.php');
$kernel = new Kernel(require $root . '/config/middleware.php', $container);

$kernel->handle(Request::fromGlobals())->send();
