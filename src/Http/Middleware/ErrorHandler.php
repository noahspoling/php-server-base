<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteNotFound;
use Throwable;

/**
 * Outermost middleware: turns anything thrown downstream into a response.
 *
 * Deliberately has no dependency on Renderer. An error page that needs the
 * view layer to work cannot report a failure in the view layer.
 */
final class ErrorHandler implements Middleware
{
    /** @var callable(string): void */
    private $log;

    /**
     * @param callable(string): void|null $log Defaults to error_log.
     */
    public function __construct(
        private readonly bool $debug = false,
        ?callable $log = null,
    ) {
        $this->log = $log ?? static function (string $message): void {
            error_log($message);
        };
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (RouteNotFound $notFound) {
            return $this->page(404, 'Not Found', 'That page does not exist.');
        } catch (Throwable $throwable) {
            ($this->log)(sprintf(
                '%s: %s in %s:%d',
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ));

            return $this->page(500, 'Server Error', $this->detail($throwable));
        }
    }

    private function detail(Throwable $throwable): string
    {
        if (!$this->debug) {
            return 'Something went wrong. The error has been logged.';
        }

        return sprintf(
            '<p><strong>%s</strong>: %s</p><p>%s:%d</p><pre>%s</pre>',
            e($throwable::class),
            e($throwable->getMessage()),
            e($throwable->getFile()),
            $throwable->getLine(),
            e($throwable->getTraceAsString()),
        );
    }

    private function page(int $status, string $title, string $detail): Response
    {
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>' . e($title) . '</title></head><body><h1>' . e($title) . '</h1>'
            . $detail
            . '</body></html>';

        return Response::html($body, $status);
    }
}
