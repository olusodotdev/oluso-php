<?php

declare(strict_types=1);

namespace Oluso\Tests\Laravel;

use Oluso\Laravel\OlusoMiddleware;
use Oluso\Laravel\OlusoServiceProvider;
use Oluso\Tests\Support\RecordingServer;
use Orchestra\Testbench\TestCase;

final class OlusoMiddlewareTest extends TestCase
{
    private RecordingServer $server;

    protected function setUp(): void
    {
        $this->server = new RecordingServer();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->server->close();
        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [OlusoServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('oluso.api_key', 'test-api-key');
        $app['config']->set('oluso.endpoint', $this->server->url);
        // Send synchronously so tests don't need a separate terminate() call.
        $app['config']->set('oluso.defer_send', false);
    }

    /**
     * @param \Illuminate\Routing\Router $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware(OlusoMiddleware::class)->group(function ($router): void {
            $router->get('/ok', fn () => 'ok');
            $router->get('/broken', fn () => response('broken', 500));
            $router->get('/exploded', function (): never {
                throw new \RuntimeException('handler exploded');
            });
            $router->get('/not-found', function (): never {
                abort(404);
            });
        });
    }

    public function testNormalRequestNotReported(): void
    {
        $response = $this->get('/ok');
        $response->assertStatus(200);

        usleep(100_000);
        self::assertSame(0, $this->server->count());
    }

    public function test5xxResponseAutoReported(): void
    {
        $response = $this->get('/broken');
        $response->assertStatus(500);

        $this->server->waitFor(fn () => $this->server->count() === 1);
        self::assertSame('critical', $this->server->last()['body']['severity']);
    }

    public function testUnhandledExceptionReportsRealErrorOnce(): void
    {
        $response = $this->get('/exploded');
        $response->assertStatus(500);

        $this->server->waitFor(fn () => $this->server->count() === 1);
        self::assertSame('handler exploded', $this->server->last()['body']['message']);

        usleep(100_000); // give a (would-be incorrect) duplicate report a chance to arrive
        self::assertSame(1, $this->server->count());
    }

    public function test404NotReported(): void
    {
        $response = $this->get('/not-found');
        $response->assertStatus(404);

        usleep(100_000);
        self::assertSame(0, $this->server->count());
    }

    public function testBreadcrumbsVisibleInsideHandler(): void
    {
        $seen = [];
        $this->app->make('router')
            ->middleware(OlusoMiddleware::class)
            ->get('/breadcrumbs', function () use (&$seen) {
                [$breadcrumbs] = \Oluso\Scope::current()->snapshot();
                $seen['count'] = count($breadcrumbs);
                return 'ok';
            });

        $this->get('/breadcrumbs');

        self::assertSame(1, $seen['count']); // the incoming-request breadcrumb
    }
}
