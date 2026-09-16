<?php

declare(strict_types=1);

namespace App\View;

use App\Http\Request;
use App\Http\Response;
use Throwable;

final class Renderer
{
    /**
     * One URL can answer with a full document or a bare fragment, so caches
     * must key on the headers that decide which.
     */
    private const VARY = 'HX-Request, HX-Boosted, HX-History-Restore-Request';

    /** Canonical view directory, with a trailing separator. */
    private readonly string $root;

    public function __construct(
        string $viewDir,
        private readonly Assets $assets,
        private readonly string $layout = 'layout',
    ) {
        $root = realpath($viewDir);

        if ($root === false) {
            throw new ViewException("View directory does not exist: {$viewDir}");
        }

        $this->root = $root . DIRECTORY_SEPARATOR;
    }

    public function render(View $view, Request $request): Response
    {
        $content = $this->capture($view->template, $view->data);

        $body = $this->wantsFullPage($request)
            ? $this->capture($this->layout, [
                'content' => $content,
                'title' => $view->data['title'] ?? 'App',
            ])
            : $content;

        return Response::html($body)->withHeader('Vary', self::VARY);
    }

    /**
     * htmx asks for a fragment, with two exceptions that both still set
     * HX-Request: a boosted navigation swaps <body> and a history restore
     * replaces the document, so both need the whole page.
     */
    private function wantsFullPage(Request $request): bool
    {
        if ($request->isHistoryRestore() || $request->isBoosted()) {
            return true;
        }

        return !$request->isHtmx();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $file = realpath($this->root . $template . '.php');

        if ($file === false || !str_starts_with($file, $this->root)) {
            throw new ViewException("Unknown view: {$template}");
        }

        $level = ob_get_level();
        ob_start();

        try {
            // Static closure: templates get their data and nothing else — no
            // $this, so they cannot reach back into the renderer.
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);

                require $__file;
            })($file, ['assets' => $this->assets] + $data);
        } catch (Throwable $throwable) {
            // Discard anything the template managed to emit, so a half-rendered
            // page cannot leak out past the error handler.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $throwable;
        }

        return (string) ob_get_clean();
    }
}
