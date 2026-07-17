<?php

declare(strict_types=1);

namespace Oluso\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Oluso\Client;
use Oluso\Options;
use Oluso\RequestContext;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class OlusoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/oluso.php', 'oluso');

        $this->app->singleton(Client::class, static function ($app): Client {
            $config = $app['config']->get('oluso', []);

            return new Client(new Options(
                apiKey: (string) ($config['api_key'] ?? ''),
                endpoint: (string) ($config['endpoint'] ?? Options::DEFAULT_ENDPOINT),
                environment: (string) ($config['environment'] ?? 'production'),
                maxBreadcrumbs: (int) ($config['max_breadcrumbs'] ?? 30),
                maxErrorsPerMinute: (int) ($config['max_errors_per_minute'] ?? 60),
                sensitiveKeys: (array) ($config['sensitive_keys'] ?? []),
                deferSend: (bool) ($config['defer_send'] ?? true),
            ));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/oluso.php' => $this->app->configPath('oluso.php'),
        ], 'oluso-config');

        $this->registerExceptionReporting();
    }

    /**
     * Laravel's middleware protocol has no per-exception hook the way
     * Django's process_exception does -- the closest equivalent is the
     * exception handler's reportable() callback, which (like Flask's
     * got_request_exception signal) fires with the real exception before
     * Laravel renders its response, without needing to change what that
     * response is. Combined with OlusoMiddleware's 5xx check (for
     * responses that aren't from a raised exception at all), both mark the
     * request via the same "_oluso_reported" attribute to avoid
     * double-reporting.
     */
    private function registerExceptionReporting(): void
    {
        if (!$this->app->bound(ExceptionHandler::class)) {
            return;
        }

        $handler = $this->app->make(ExceptionHandler::class);
        if (!method_exists($handler, 'reportable')) {
            return;
        }

        $handler->reportable(function (\Throwable $e): void {
            $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($statusCode < 500) {
                return; // an intentional 4xx (validation, not-found, etc.) -- not an error worth reporting
            }

            $client = $this->app->make(Client::class);
            $request = $this->app->bound('request') ? $this->app->make('request') : null;

            if ($request !== null) {
                $request->attributes->set('_oluso_reported', true);
                $requestContext = RequestContextBuilder::fromRequest($client, $request);
            } else {
                $requestContext = new RequestContext(url: '', method: '');
            }

            $client->captureHttpError($e, $requestContext, $statusCode);
        });
    }
}
