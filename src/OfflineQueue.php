<?php

declare(strict_types=1);

namespace Oluso;

/**
 * Persists error reports that failed to send, for retry on the next
 * successful send.
 *
 * Unlike the Node/Go/Python SDKs, the relevant concurrency boundary here
 * isn't threads within one process -- it's separate PHP-FPM worker
 * *processes* potentially touching the same queue file at the same time.
 * flock() (not a language-level mutex) is the correct primitive for that.
 */
final class OfflineQueue
{
    private readonly string $filePath;

    private readonly int $maxSize;

    public function __construct(int $maxSize = 100, ?string $queueDir = null)
    {
        $this->maxSize = $maxSize > 0 ? $maxSize : 100;
        $dir = $queueDir ?? sys_get_temp_dir() . '/oluso-queue';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $this->filePath = rtrim($dir, '/') . '/error-queue.json';
    }

    /**
     * @param array<string, mixed> $report an already-serialized report (see ErrorReport::toArray())
     */
    public function enqueue(array $report): void
    {
        $this->withLock(function (array $queue) use ($report): array {
            $queue[] = ['report' => $report, 'timestamp' => microtime(true), 'retries' => 0];
            if (count($queue) > $this->maxSize) {
                array_shift($queue);
            }
            return $queue;
        });
    }

    public function size(): int
    {
        $count = null;
        $this->withSharedLock(function (array $queue) use (&$count): void {
            $count = count($queue);
        });
        return $count ?? 0;
    }

    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    public function clear(): void
    {
        $this->withLock(static fn (array $queue): array => []);
    }

    /**
     * Attempt to send each queued report in order via $sendFn (which should
     * throw on failure), stopping at the first failure (which is requeued
     * at the front with an incremented retry count, and dropped after 3
     * failed attempts).
     *
     * $sendFn is called without holding the file lock, so another
     * process/request enqueueing isn't blocked for the duration of a slow
     * or hanging send.
     *
     * @param callable(array<string, mixed>): void $sendFn
     */
    public function processQueue(callable $sendFn): void
    {
        while (true) {
            $item = null;
            $this->withSharedLock(function (array $queue) use (&$item): void {
                $item = $queue[0] ?? null;
            });
            if ($item === null) {
                return;
            }

            try {
                $sendFn($item['report']);
            } catch (\Throwable) {
                $this->withLock(function (array $queue) use ($item): array {
                    if (($queue[0]['timestamp'] ?? null) !== $item['timestamp']) {
                        return $queue; // mutated concurrently by another process; leave it alone
                    }
                    $queue[0]['retries']++;
                    if ($queue[0]['retries'] >= 3) {
                        array_shift($queue);
                    }
                    return $queue;
                });
                return;
            }

            $this->withLock(function (array $queue) use ($item): array {
                if (($queue[0]['timestamp'] ?? null) === $item['timestamp']) {
                    array_shift($queue);
                }
                return $queue;
            });
        }
    }

    /**
     * @param callable(array<int, array{report: array<string, mixed>, timestamp: float, retries: int}>): array $mutator
     */
    private function withLock(callable $mutator): void
    {
        $fp = @fopen($this->filePath, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $queue = $this->readFromHandle($fp);
            $queue = $mutator($queue);
            $this->writeToHandle($fp, $queue);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @param callable(array<int, array{report: array<string, mixed>, timestamp: float, retries: int}>): void $reader
     */
    private function withSharedLock(callable $reader): void
    {
        $fp = @fopen($this->filePath, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_SH)) {
                return;
            }
            $reader($this->readFromHandle($fp));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @param resource $fp
     * @return array<int, array{report: array<string, mixed>, timestamp: float, retries: int}>
     */
    private function readFromHandle($fp): array
    {
        rewind($fp);
        $contents = stream_get_contents($fp);
        if ($contents === false || $contents === '') {
            return [];
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return [];
        }

        $cutoff = microtime(true) - 24 * 60 * 60;
        return array_values(array_filter(
            $data,
            static fn (mixed $item): bool => is_array($item) && (float) ($item['timestamp'] ?? 0) > $cutoff,
        ));
    }

    /**
     * @param resource $fp
     * @param array<int, array{report: array<string, mixed>, timestamp: float, retries: int}> $queue
     */
    private function writeToHandle($fp, array $queue): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($queue) ?: '[]');
        fflush($fp);
    }
}
