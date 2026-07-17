<?php

declare(strict_types=1);

namespace Oluso\Tests;

use function Oluso\add_breadcrumb;

use Oluso\Client;
use Oluso\Options;
use Oluso\Scope;

use function Oluso\set_user;

use Oluso\Tests\Support\RecordingServer;
use Oluso\UserContext;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private RecordingServer $server;

    private string $queueDir;

    protected function setUp(): void
    {
        $this->server = new RecordingServer();
        $this->queueDir = sys_get_temp_dir() . '/oluso-client-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->server->close();
        Scope::clear();
        array_map('unlink', glob($this->queueDir . '/*') ?: []);
        @rmdir($this->queueDir);
    }

    private function makeClient(array $overrides = []): Client
    {
        return new Client(new Options(...[
            'apiKey' => 'test-api-key',
            'endpoint' => $this->server->url,
            'queueDir' => $this->queueDir,
            ...$overrides,
        ]));
    }

    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(new Options(apiKey: ''));
    }

    public function testCaptureExceptionSendsReport(): void
    {
        $client = $this->makeClient();

        $client->captureException(new \RuntimeException('boom'));

        $this->server->waitFor(fn () => $this->server->count() === 1);
        $received = $this->server->last();

        self::assertSame('boom', $received['body']['message']);
        self::assertSame('test-api-key', $received['headers']['HTTP_X_OLUSO_SIGNATURE'] ?? null);
    }

    public function testCaptureExceptionQueuesOnFailure(): void
    {
        $this->server->setFail(true);
        $client = $this->makeClient();

        $client->captureException(new \RuntimeException('boom'));

        $this->server->waitFor(fn () => $this->server->count() >= 1);

        $queueFile = $this->queueDir . '/error-queue.json';
        $this->server->waitFor(function () use ($queueFile) {
            $contents = @file_get_contents($queueFile);
            return $contents !== false && $contents !== '' && json_decode($contents, true) !== [];
        });

        $queued = json_decode((string) file_get_contents($queueFile), true);
        self::assertCount(1, $queued);
    }

    public function testShouldReportSkipsFilteredErrors(): void
    {
        $client = $this->makeClient([
            'shouldReport' => fn (\Throwable $e) => $e->getMessage() !== 'ignore me',
        ]);

        $client->captureException(new \RuntimeException('ignore me'));
        $client->captureException(new \RuntimeException('report me'));

        $this->server->waitFor(fn () => $this->server->count() === 1);
        usleep(100_000);
        self::assertSame(1, $this->server->count());
    }

    public function testRateLimiterBlocksExcessSends(): void
    {
        $client = $this->makeClient(['maxErrorsPerMinute' => 1]);

        $client->captureException(new \RuntimeException('first'));
        $client->captureException(new \RuntimeException('second'));

        $this->server->waitFor(fn () => $this->server->count() === 1);
        usleep(100_000);
        self::assertSame(1, $this->server->count());
    }

    public function testCaptureExceptionIncludesScopedBreadcrumbsAndUser(): void
    {
        $client = $this->makeClient();

        Scope::start();
        add_breadcrumb('user clicked checkout');
        set_user(new UserContext(id: 'user-123'));

        $client->captureException(new \RuntimeException('checkout failed'));

        $this->server->waitFor(fn () => $this->server->count() === 1);
        $received = $this->server->last();

        self::assertSame('user clicked checkout', $received['body']['context']['breadcrumbs'][0]['message']);
        self::assertSame('user-123', $received['body']['context']['user']['id']);
    }

    public function testDeferSendQueuesUntilFlush(): void
    {
        $client = $this->makeClient(['deferSend' => true]);

        $client->captureException(new \RuntimeException('deferred'));
        usleep(100_000);
        self::assertSame(0, $this->server->count());

        $client->flush();

        $this->server->waitFor(fn () => $this->server->count() === 1);
        self::assertSame('deferred', $this->server->last()['body']['message']);
    }
}
