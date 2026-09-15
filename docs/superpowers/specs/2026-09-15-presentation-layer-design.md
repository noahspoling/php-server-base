# Presentation Layer: Checksum-Cached Static Section + MVC Runtime

Date: 2026-09-15
Status: Approved for planning

## Problem

`php-server-base` is a bare LAMP scaffold: Docker Compose, two Dockerfiles, a MySQL
init script, and a single demo `www/index.php` that echoes HTML inline and hardcodes
database credentials. There is no view layer, no asset directory, and no way to serve
a static file at all.

Two defects block any presentation work:

1. `apache/Dockerfile` never changes `DocumentRoot`, so it remains
   `/usr/local/apache2/htdocs` — empty in the `httpd:2.4` image. Only `.php` requests
   succeed, because `ProxyPassMatch` forwards them by path to PHP-FPM, which does have
   `./www` mounted. A request for `/static/base.css` returns 404.
2. The `AllowOverride All` on `apache/Dockerfile:13` is scoped to the `htdocs`
   directory block, so an `.htaccess` under `www/` would be ignored.

We want a reusable base where application logic cannot emit HTML, where presentation
assets are fingerprinted so they can be cached permanently, and where htmx fragment
endpoints are first-class.

## Goals

- A declared static section — base CSS, htmx, hyperscript — emitted as one block with
  content-hashed URLs and correct cache headers.
- A structural split between application logic and presentation logic, enforced by
  types and directory layout rather than by convention.
- htmx fragment responses from the same handlers that serve full pages.
- Automated tests for the logic that carries risk.

## Non-Goals

- An asset build step (bundling, minification, transpilation). Assets are vendored
  pre-minified and served as-is.
- A component CSS vocabulary. `base.css` styles bare elements only.
- Authentication, sessions, CSRF, or a migration system. Out of scope for this spec.

## Decisions

Each decision below was chosen explicitly during design.

| Decision | Choice | Rationale |
| --- | --- | --- |
| Scope | Assets + layout + htmx partials | The split requested requires all three. |
| Asset URL form | Hashed path + Apache rewrite | Enables `immutable` caching with no build step. |
| Asset origin | Vendored into `www/static` | Works offline; our checksum governs caching. |
| Hash computation | On demand, memoized per request | No build step, never stale, microseconds for four files. |
| `base.css` content | Tokens + reset + element defaults | htmx apps are form- and table-heavy; no class vocabulary to inherit. |
| Testing | PHPUnit via Composer | Composer is already in `php/Dockerfile`. |
| Structure | Full MVC — controllers, container, middleware | Chosen by the user over a lighter front-controller design. |

## Architecture

```
config/
  assets.php        Static section manifest
  services.php      Container bindings
  middleware.php    Pipeline order
  routes.php        Route table
src/
  Container/        Container, NotFoundException
  Http/             Request, Response, Kernel, Router
  Http/Middleware/  Middleware, ErrorHandler, SecurityHeaders, RouteDispatcher
  Controller/       Controller (base), HomeController, UserController
  Repository/       UserRepository
  View/             Assets, AssetException, View, Renderer, helpers.php
views/
  layout.php
  home.php
  users/index.php
  users/rows.php
www/                Webroot — nothing else is reachable over HTTP
  index.php         Front controller
  static/           base.css, htmx.min.js, _hyperscript.min.js
tests/
apache/app.conf     Replaces the sed/echo stack in apache/Dockerfile
```

`src/`, `views/`, `config/`, and `vendor/` sit outside the document root.

### Request flow

```
Request::fromGlobals()
  → Kernel folds config/middleware.php into a nested closure chain
    → ErrorHandler       catches Throwable → 500; RouteNotFound → 404
    → SecurityHeaders    X-Content-Type-Options, Referrer-Policy, CSP
    → RouteDispatcher    terminal; matches route, resolves controller from container
      → Controller returns View | Response
        → Renderer turns a View into a Response (layout or fragment)
  → Response::send()
```

## Component: Assets

The static section is declared, not scattered through templates.

```php
// config/assets.php
return [
    'css' => ['base.css'],
    'js'  => ['htmx.min.js', '_hyperscript.min.js'],
];
```

`Assets::head()` renders the whole block. `Assets::url()` remains public for one-off
references such as favicons and images.

```php
final class Assets
{
    private array $hashes = [];

    public function url(string $path): string
    {
        $hash = $this->hashes[$path] ??= $this->hash($path);
        $dot  = strrpos($path, '.');
        return $this->prefix . '/' . substr($path, 0, $dot) . '.' . $hash . substr($path, $dot);
    }

    private function hash(string $path): string
    {
        $real = realpath($this->dir . '/' . $path);
        if ($real === false || !str_starts_with($real, $this->root)) {
            throw new AssetException("Unknown asset: {$path}");
        }
        return substr(hash_file('xxh128', $real), 0, 8);
    }
}
```

`$dir` is the configured static directory and `$root` is
`realpath($dir) . DIRECTORY_SEPARATOR`, resolved once in the constructor so the
prefix comparison is against a canonical path.

`xxh128` is non-cryptographic and fast; cache busting does not need collision
resistance against an adversary. The `realpath` prefix check is both the
missing-file guard and the directory-traversal guard. Unknown paths throw rather
than emitting a URL that 404s at runtime.

Hashes are memoized per request, so a template may ask for `base.css` repeatedly at
the cost of one `hash_file` call.

Rendered output:

```html
<link rel="stylesheet" href="/static/base.a1b2c3d4.css">
<script src="/static/htmx.min.2f9c71e0.js" defer></script>
<script src="/static/_hyperscript.min.9e4a15bb.js" defer></script>
```

Both scripts use `defer`: execution order is preserved and deferred until after parse.

### Serving and caching

Hashed filenames never exist on disk. Apache strips the hash and flags the request:

```apache
RewriteRule ^/static/(.+)\.[0-9a-f]{8}\.(css|js|svg|png|woff2)$ /static/$1.$2 [L,E=HASHED_ASSET:1]
Header always set Cache-Control "public, max-age=31536000, immutable" env=HASHED_ASSET
```

The `env=HASHED_ASSET` condition is load-bearing. A request for the unhashed
`/static/base.css` receives no `Cache-Control` and falls back to ETag revalidation,
so a hand-written unhashed link cannot pin a stale file in a browser for a year.
Only URLs carrying a checksum receive the immutable promise — which is safe precisely
because editing the file mints a different URL.

### Apache changes this depends on

- Enable `mod_headers`. It is not currently loaded, and `Header` requires it.
- Replace `ProxyPassMatch` (`apache/Dockerfile:10`) with
  `<FilesMatch \.php$> SetHandler "proxy:fcgi://php:9000" </FilesMatch>`.
  `ProxyPassMatch` resolves before `mod_rewrite` and would bypass the front
  controller; `SetHandler` runs after rewriting, so rewrites apply.
- Set `DocumentRoot "/var/www/app/www"` with a matching `<Directory>` block granting
  access and disabling indexes.
- Route everything that is not `/static/` to the front controller.
- Move this configuration into `apache/app.conf`, copied into the image. The current
  `sed`/`echo` approach becomes unreadable at this size.

## Component: Container

PSR-11. Factories are declared in `config/services.php`; resolved instances are
memoized, so bindings are singletons by default.

Classes with no binding fall back to constructor autowiring by reflection, limited to
typed class parameters. No scalar injection, no setter injection, no property
injection. This keeps controllers out of the bindings file. Unresolvable ids throw
`NotFoundException`.

## Component: Http

`Request` is an immutable value object built by `Request::fromGlobals()`, exposing
method, path, query, post, headers, and route attributes, plus htmx predicates.
`Response` carries body, status, and headers, with `html()` and `redirect()`
constructors and a `send()` method.

Middleware is a single interface; `$next` is a plain callable rather than a second
handler type:

```php
interface Middleware {
    public function process(Request $request, callable $next): Response;
}
```

`Kernel` folds `config/middleware.php` into a nested closure chain, outermost first.
The innermost fallback throws — a pipeline that ends without producing a response is
a programming error, not a 404.

`Router` compiles `{placeholder}` segments to a regex per route and returns matched
values as request attributes. No type constraints on placeholders. Paths are matched
literally: `/users` and `/users/` are distinct routes and neither is normalized into
the other. A miss returns null, which `RouteDispatcher` turns into `RouteNotFound`.

### Content Security Policy

Vendoring pays off here — every script and stylesheet is same-origin, so the default
policy is `default-src 'self'` with no host allowlist and no `unsafe-inline` for
scripts.

One constraint follows from that and must be stated rather than discovered: htmx
compiles `hx-on:` handlers and event filters with `new Function`, which a policy
without `'unsafe-eval'` blocks. The default policy omits `'unsafe-eval'`, so those
two htmx features are unavailable in the base. Behavior belongs in hyperscript's
`_` attributes, which parse their own language and need no eval. An application that
genuinely needs `hx-on:` relaxes the policy in its own `SecurityHeaders` binding and
accepts the tradeoff explicitly.

Inline `<style>` is not used by the base, so `style-src 'self'` holds without
`unsafe-inline`.

```php
// config/routes.php
return [
    'GET  /'            => [HomeController::class, 'index'],
    'GET  /users'       => [UserController::class, 'index'],
    'GET  /users/rows'  => [UserController::class, 'rows'],
    'POST /users'       => [UserController::class, 'store'],
    'GET  /users/{id}'  => [UserController::class, 'show'],
];
```

## Component: Controller and View

Controllers return a `View` — an inert value object holding a template name and data.
It has no rendering method and no reference to `Renderer`.

```php
final class UserController extends Controller
{
    public function __construct(private readonly UserRepository $users) {}

    public function rows(Request $request): View
    {
        return $this->view('users/rows', ['users' => $this->users->all()]);
    }
}
```

This is the enforcement mechanism. A controller cannot emit HTML: it holds no
renderer, opens no output buffer, and does not live in the document root.
`RouteDispatcher` passes the returned `View` to `Renderer`. Controllers may return a
`Response` directly for redirects and non-HTML responses.

The demo logic in `www/index.php` moves to `HomeController` and a `UserRepository`
resolved from the container, with credentials from environment variables rather than
the hardcoded values on `www/index.php:2-5`.

## Component: Renderer

One flat view tree. A template is `views/<name>.php`. Whether it is wrapped in the
layout is a property of the request, not of the file's location, so there is no
`pages/` versus `partials/` split.

The naive rule — "`HX-Request: true` means fragment" — is wrong in two cases:

| Request | Renders |
| --- | --- |
| `HX-History-Restore-Request: true` | Full page. htmx needs a whole document to restore history. |
| `HX-Boosted: true` | Full page. Boosted navigation swaps `<body>`, so the head must come along. |
| `HX-Request: true` | Fragment only. |
| No htmx headers | Full page. |

The first two are checked before the third.

Because one URL can answer with either shape, responses carry
`Vary: HX-Request, HX-Boosted, HX-History-Restore-Request`. Without it, a shared
cache can hand a bare fragment to a cold browser navigation.

`Renderer` captures the template in an output buffer and discards it on throw, so a
half-rendered page cannot leak past `ErrorHandler`. Templates escape through a global
`e()` autoloaded via Composer's `files` entry.

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <?= $assets->head() ?>
</head>
<body><?= $content ?></body>
</html>
```

`$content` is the single deliberately unescaped value in the system; it is already
rendered HTML. Every value reaching a template from a controller passes through
`e()`.

## Component: base.css

Custom properties for color, spacing, and type scale, including a
`prefers-color-scheme: dark` block. A modern reset. Styling for bare elements —
headings, links, forms, inputs, buttons, tables. No component classes: consuming
applications override tokens rather than fighting rules.

## Infrastructure

Both containers mount the project root at the same path:

```yaml
apache:
  volumes: [ ./:/var/www/app ]
php:
  volumes: [ ./:/var/www/app ]
```

The paths must agree because `SetHandler proxy:fcgi` sends Apache's local
`SCRIPT_FILENAME` to PHP-FPM. `php/Dockerfile` sets `WORKDIR /var/www/app` and
chowns that path.

The `php` service gains an `environment:` block. It currently has none, which is why
`www/index.php` hardcodes credentials. The `PDO` factory in `config/services.php`
reads them. A committed `.env.example` documents the required variables, since
`.env` is gitignored.

`composer.json` declares PSR-4 `App\` → `src/`, a `files` entry for
`src/View/helpers.php`, `App\Tests\` → `tests/`, and PHPUnit 11 as a dev dependency.
`composer.lock` is committed; `vendor/` is gitignored and installed with
`docker compose exec php composer install`.

## Testing

PHPUnit, run with `docker compose exec php vendor/bin/phpunit`.

- **Assets** — hash changes when file content changes; unknown path throws; traversal
  path throws; memoization returns a stable value; URL shape is `name.<8 hex>.ext`;
  `head()` emits the manifest in order with `defer` on scripts.
- **Router** — method and path matching; placeholder extraction; no match returns
  null; `/users/` does not match a `/users` route.
- **Renderer** — each of the four rows of the full-versus-fragment table; `Vary`
  header present; data escaped; buffer discarded on throw.
- **Container** — factory result memoized; autowiring resolves typed class
  parameters; missing id throws `NotFoundException`.
- **Kernel** — middleware runs in declared order; `ErrorHandler` catches a throw from
  a downstream middleware; exhausted pipeline throws.

The Apache rewrite and the hashed-only `immutable` header are server configuration
and cannot be unit tested. They are verified with `curl -I` against the running
stack, comparing a hashed URL, an unhashed URL, and a front-controller route. The
observed output is reported rather than asserted as working.

## Implementation sequence

This spec covers a full subsystem, so the plan should land it in four stages, each
leaving the stack runnable:

1. **Foundation** — `composer.json`, PSR-4 autoload, PHPUnit, `.env.example`, and the
   Docker and Apache changes (shared mount path, `DocumentRoot`, `mod_headers`,
   `SetHandler`, `apache/app.conf`). Verified by serving a plain file from
   `www/static`.
2. **Static section** — `Assets`, `config/assets.php`, vendored htmx and hyperscript,
   `base.css`, the rewrite and the hashed-only `immutable` header. Verified by unit
   tests plus `curl -I`.
3. **MVC runtime** — `Container`, `Request`, `Response`, `Middleware`, `Kernel`,
   `Router`, `RouteDispatcher`, `ErrorHandler`, `SecurityHeaders`, front controller.
4. **View layer** — `View`, `Renderer`, `layout.php`, `e()`, `Controller` base,
   `HomeController`, `UserRepository`, and the htmx fragment demo that replaces the
   current `www/index.php`.

Stage 1 is a prerequisite for everything; stages 3 and 4 are where the demo page
returns.

## Risks

- **Path agreement between containers.** If the two mounts diverge, PHP-FPM receives
  a `SCRIPT_FILENAME` it cannot open and every request fails. Both mounts and the
  `DocumentRoot` are changed together.
- **Rewrite ordering.** `SetHandler` must replace `ProxyPassMatch`, or the front
  controller is bypassed silently for some paths. Covered by the `curl` verification.
- **Reflection autowiring.** Convenient but implicit. Bounded to typed class
  constructor parameters so failures are immediate and legible rather than subtle.
