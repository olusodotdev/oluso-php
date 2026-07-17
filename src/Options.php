<?php

declare(strict_types=1);

namespace Oluso;

final class Options
{
    public const DEFAULT_ENDPOINT = 'https://api.oluso.dev/api/v1/error/report';

    /**
     * @param string[] $tags
     * @param string[] $sensitiveKeys
     * @param (\Closure(\Throwable): bool)|null $shouldReport
     * @param (\Closure(\Throwable, ?ErrorContext): string)|null $fingerprint
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $endpoint = self::DEFAULT_ENDPOINT,
        public readonly string $environment = 'production',
        public readonly Severity $defaultSeverity = Severity::Medium,
        public readonly array $tags = [],
        public readonly ?\Closure $shouldReport = null,
        public readonly ?\Closure $fingerprint = null,
        public readonly float $timeout = 5.0,
        public readonly bool $logToConsole = true,
        public readonly int $maxBreadcrumbs = 30,
        public readonly bool $enableOfflineQueue = true,
        public readonly int $maxQueueSize = 100,
        public readonly ?string $queueDir = null,
        public readonly int $maxErrorsPerMinute = 60,
        public readonly array $sensitiveKeys = [],
        /**
         * When true, captured reports are queued in memory and only sent
         * when flush() is called, instead of being sent synchronously as
         * soon as they're captured. Since plain PHP has no background
         * thread/event loop to send on, synchronous-by-default is the safer
         * choice for a standalone script (nothing to forget). Framework
         * integrations that have a natural "after the response is sent"
         * hook (e.g. Laravel's terminable middleware) can enable this to
         * avoid adding request latency, calling flush() from that hook.
         */
        public readonly bool $deferSend = false,
    ) {
    }
}
