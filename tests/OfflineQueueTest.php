<?php

declare(strict_types=1);

namespace Oluso\Tests;

use Oluso\OfflineQueue;
use PHPUnit\Framework\TestCase;

final class OfflineQueueTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/oluso-queue-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testEnqueueAndProcess(): void
    {
        $q = new OfflineQueue(10, $this->dir);
        $q->enqueue(['message' => 'a']);
        $q->enqueue(['message' => 'b']);
        self::assertSame(2, $q->size());

        $sent = [];
        $q->processQueue(function (array $r) use (&$sent): void {
            $sent[] = $r['message'];
        });

        self::assertTrue($q->isEmpty());
        self::assertSame(['a', 'b'], $sent);
    }

    public function testMaxSizeEvictsOldest(): void
    {
        $q = new OfflineQueue(2, $this->dir);
        $q->enqueue(['message' => '1']);
        $q->enqueue(['message' => '2']);
        $q->enqueue(['message' => '3']);
        self::assertSame(2, $q->size());

        $sent = [];
        $q->processQueue(function (array $r) use (&$sent): void {
            $sent[] = $r['message'];
        });
        self::assertSame(['2', '3'], $sent);
    }

    public function testFailureStopsProcessingAndRequeues(): void
    {
        $q = new OfflineQueue(10, $this->dir);
        $q->enqueue(['message' => 'a']);
        $q->enqueue(['message' => 'b']);

        $callCount = 0;
        $q->processQueue(function (array $r) use (&$callCount): void {
            $callCount++;
            throw new \RuntimeException('network down');
        });

        self::assertSame(1, $callCount);
        self::assertSame(2, $q->size());
    }

    public function testDropsAfterThreeFailedRetries(): void
    {
        $q = new OfflineQueue(10, $this->dir);
        $q->enqueue(['message' => 'a']);

        for ($i = 0; $i < 3; $i++) {
            $q->processQueue(function (array $r): void {
                throw new \RuntimeException('network down');
            });
        }

        self::assertTrue($q->isEmpty());
    }

    public function testPersistsAcrossInstances(): void
    {
        $q1 = new OfflineQueue(10, $this->dir);
        $q1->enqueue(['message' => 'persisted']);

        $q2 = new OfflineQueue(10, $this->dir);
        self::assertSame(1, $q2->size());

        $sent = [];
        $q2->processQueue(function (array $r) use (&$sent): void {
            $sent[] = $r['message'];
        });
        self::assertSame(['persisted'], $sent);
    }
}
