<?php

declare(strict_types=1);

namespace Oluso;

final class ServerContext
{
    public function __construct(
        public readonly string $hostname,
        public readonly string $platform,
        public readonly string $phpVersion,
        public readonly int $processId,
        public readonly int $memoryUsed,
        public readonly float $uptimeSeconds,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hostname' => $this->hostname,
            'platform' => $this->platform,
            'phpVersion' => $this->phpVersion,
            'processId' => $this->processId,
            'memoryUsed' => $this->memoryUsed,
            'uptime' => $this->uptimeSeconds,
        ];
    }

    public static function current(): self
    {
        // Under classic PHP-FPM/CLI (a fresh process per request, or one
        // request per script run), there's no meaningful "server uptime" the
        // way long-running runtimes have -- this is time since the current
        // request/script started. Under a persistent-worker runtime
        // (Octane, Swoole), the same worker process handles many requests,
        // so this remains an honest "since this request began" reading
        // either way, rather than pretending to track process uptime.
        $requestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        return new self(
            hostname: gethostname() ?: 'unknown',
            platform: PHP_OS . ' ' . php_uname('r'),
            phpVersion: PHP_VERSION,
            processId: getmypid() ?: 0,
            memoryUsed: memory_get_usage(true),
            uptimeSeconds: microtime(true) - (float) $requestStart,
        );
    }
}
