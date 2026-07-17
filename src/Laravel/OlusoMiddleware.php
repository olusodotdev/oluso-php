<?php

declare(strict_types=1);

namespace Oluso\Laravel;

use Closure;
use Illuminate\Http\Request;

use function Oluso\add_breadcrumb;

use Oluso\BreadcrumbLevel;
use Oluso\Client;
use Oluso\Scope;

/**
 * Scopes breadcrumbs to each request, auto-reports 5xx responses that
 * aren't already covered by the reportable() hook registered in
 * OlusoServiceProvider (see its docblock for why both exist), and flushes
 * queued reports from its terminate() hook -- called by Laravel after the
 * response has already been sent to the client, so error reporting never
 * adds latency to the response.
 */
final class OlusoMiddleware
{
    public function __construct(private readonly Client $client)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $scope = Scope::start($this->client->maxBreadcrumbs());
        $scope->setRequestStart(microtime(true));

        add_breadcrumb("{$request->method()} {$request->path()}", category: 'http', data: [
            'method' => $request->method(),
            'url' => $request->path(),
        ]);

        $response = $next($request);

        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        if ($status >= 500 && !$request->attributes->get('_oluso_reported', false)) {
            $error = new \RuntimeException("server error: {$status} - {$request->method()} {$request->path()}");
            $this->client->captureHttpError(
                $error,
                RequestContextBuilder::fromRequest($this->client, $request),
                $status,
            );
        }

        add_breadcrumb(
            "Response {$status} - {$request->method()} {$request->path()}",
            level: $status >= 400 ? BreadcrumbLevel::Error : BreadcrumbLevel::Info,
            category: 'http',
            data: ['statusCode' => $status],
        );

        return $response;
    }

    public function terminate(Request $request, mixed $response): void
    {
        $this->client->flush();
        Scope::clear();
    }
}
