# oluso-php

AI-powered error monitoring for PHP applications: automatic error reporting, breadcrumb tracking, and intelligent error grouping.

## Installation

```bash
composer require olusodotdev/oluso-php
```

Laravel auto-discovers the package's service provider — no manual registration needed.

## Usage with Laravel

```php
// config/oluso.php (or just set OLUSO_API_KEY in .env — the package ships a sensible default config)
return [
    'api_key' => env('OLUSO_API_KEY'),
    'environment' => env('APP_ENV', 'production'),
];
```

```php
// bootstrap/app.php (Laravel 11+) or app/Http/Kernel.php (Laravel 10)
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Oluso\Laravel\OlusoMiddleware::class);
})
```

```php
// routes/web.php
Route::get('/', function () {
    throw new RuntimeException('something went wrong'); // captured and reported automatically
});
```

`OlusoMiddleware` scopes breadcrumbs to each request and auto-reports 5xx responses. Unhandled exceptions are reported separately via Laravel's `reportable()` exception hook (registered automatically by the service provider), since Laravel's own exception handler intercepts them before they'd ever reach the middleware's response check — this is what gets you the real exception (type, message, trace) instead of a generic "server error: 500". Both paths mark the request so a single exception is never double-reported.

By default, reports are queued during the request and only sent from the middleware's `terminate()` hook — called by Laravel *after* the response has already been sent to the client, so error reporting never adds latency to the response. Set `'defer_send' => false` in `config/oluso.php` to send synchronously instead.

## Breadcrumbs & User Context

```php
use Oluso\UserContext;
use function Oluso\add_breadcrumb;
use function Oluso\set_user;

Route::get('/checkout', function () {
    add_breadcrumb('user started checkout', category: 'action');
    set_user(new UserContext(id: 'user_456'));

    try {
        doCheckout();
    } catch (\Throwable $e) {
        app(\Oluso\Client::class)->captureException($e, ['cartId' => 'cart_123']);
    }
});
```

For non-request work (a queued job, an Artisan command) where you still want a scope, open one yourself:

```php
use Oluso\Scope;
use function Oluso\add_breadcrumb;

Scope::start();
add_breadcrumb('job started');
$client->captureException($e);
Scope::clear();
```

Classic PHP-FPM is shared-nothing per request, so `Scope` is just a static holder — there's no cross-request leakage to guard against the way Node/Python/Go need `AsyncLocalStorage`/`contextvars`/`context.Context` for. The one thing this doesn't handle is true *concurrent* requests sharing one PHP process via coroutines (Swoole specifically) — that needs coroutine-local storage, out of scope for this first pass.

## Manual Reporting (framework-agnostic)

The core client has no framework dependency and works in any PHP script:

```php
use Oluso\Client;
use Oluso\Options;

$client = new Client(new Options(apiKey: 'your-api-key'));

try {
    doWork();
} catch (\Throwable $e) {
    $client->captureException($e, ['customMeta' => 'extra-info']);
}
```

Plain PHP has no background thread or event loop to send on, so `captureException()` sends **synchronously by default** — nothing to forget, correct by default for a script that exits right after. Set `Options::$deferSend = true` if you have your own "after the response" hook and want to call `$client->flush()` from it (this is what the Laravel integration does automatically).

## Advanced Configuration

```php
use Oluso\Options;
use Oluso\Severity;

$options = new Options(
    apiKey: 'your-api-key',
    endpoint: Options::DEFAULT_ENDPOINT, // override for self-hosting
    environment: 'staging',
    defaultSeverity: Severity::Medium,
    maxBreadcrumbs: 50,
    maxErrorsPerMinute: 100,
    sensitiveKeys: ['ssn', 'internal_id'],
    shouldReport: fn (\Throwable $e) => !str_contains($e->getMessage(), 'expected'),
);
```

## Error Report Structure

Reports sent to the API include:

- **Metadata**: Title, message, stack trace, severity, tags.
- **Context**: Request details (URL, method, headers, etc.), server details (hostname, PHP version, memory).
- **History**: Breadcrumbs leading up to the error.
- **Identification**: Fingerprint for deduplication and user ID.

## License

MIT
