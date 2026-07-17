<?php

declare(strict_types=1);

namespace Oluso;

final class Client
{
    private readonly Sanitizer $sanitizer;

    private readonly RateLimiter $rateLimiter;

    private readonly OfflineQueue $queue;

    /** @var array<int, array<string, mixed>> */
    private array $pending = [];

    public function __construct(private readonly Options $options)
    {
        if ($this->options->apiKey === '') {
            throw new \InvalidArgumentException('oluso: apiKey is required');
        }
        $this->sanitizer = new Sanitizer($options->sensitiveKeys);
        $this->rateLimiter = new RateLimiter($options->maxErrorsPerMinute);
        $this->queue = new OfflineQueue($options->maxQueueSize, $options->queueDir);
    }

    public function maxBreadcrumbs(): int
    {
        return $this->options->maxBreadcrumbs;
    }

    /**
     * The Sanitizer configured for this client (respects
     * Options::$sensitiveKeys), for framework integrations that build a
     * RequestContext manually.
     */
    public function sanitizer(): Sanitizer
    {
        return $this->sanitizer;
    }

    /**
     * Report $error, attaching any breadcrumbs/user/custom context on the
     * current Scope, plus any $customContext given here.
     *
     * @param array<string, mixed> $customContext
     */
    public function captureException(\Throwable $error, array $customContext = []): void
    {
        $this->capture($error, null, null, $customContext);
    }

    /**
     * Like captureException() but also attaches HTTP request context and a
     * response status code, for reporting an error with request context
     * from a framework integration.
     *
     * @param array<string, mixed> $customContext
     */
    public function captureHttpError(
        \Throwable $error,
        RequestContext $requestContext,
        int $statusCode,
        array $customContext = [],
    ): void {
        $this->capture($error, $requestContext, $statusCode, $customContext);
    }

    /**
     * @param array<string, mixed> $customContext
     */
    private function capture(
        \Throwable $error,
        ?RequestContext $requestContext,
        ?int $statusCode,
        array $customContext,
    ): void {
        if ($this->options->shouldReport !== null && !($this->options->shouldReport)($error)) {
            return;
        }
        if (!$this->rateLimiter->canSend()) {
            if ($this->options->logToConsole) {
                error_log('[Oluso] rate limit exceeded, error not reported');
            }
            return;
        }
        if ($this->options->logToConsole) {
            error_log('[Oluso] ' . $error);
        }

        $stackTrace = $error->getTraceAsString();
        $errorContext = $this->buildErrorContext($requestContext, $customContext);

        $fingerprint = $this->options->fingerprint !== null
            ? ($this->options->fingerprint)($error, $errorContext)
            : Fingerprint::generate($error, $stackTrace);

        $severity = $this->options->defaultSeverity;
        if ($statusCode !== null) {
            $severity = match (true) {
                $statusCode >= 500 => Severity::Critical,
                $statusCode >= 400 => Severity::High,
                default => $severity,
            };
        }

        $report = new ErrorReport(
            title: self::errorTitle($error),
            message: $error->getMessage(),
            stackTrace: $stackTrace,
            environment: $this->options->environment,
            severity: $severity->value,
            tags: $this->options->tags,
            fingerprint: $fingerprint,
            context: $errorContext,
            timestampMs: (int) round(microtime(true) * 1000),
        );

        if ($this->options->deferSend) {
            $this->pending[] = $report->toArray();
            return;
        }

        $this->sendReport($report->toArray());
    }

    /**
     * @param array<string, mixed> $customContext
     */
    private function buildErrorContext(?RequestContext $requestContext, array $customContext): ErrorContext
    {
        $scope = Scope::current();
        [$breadcrumbs, $user, $custom] = $scope?->snapshot() ?? [[], null, []];

        return new ErrorContext(
            request: $requestContext,
            user: $user,
            server: ServerContext::current(),
            custom: [...$custom, ...$customContext],
            breadcrumbs: $breadcrumbs,
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    private function sendReport(array $report): void
    {
        try {
            Transport::sendErrorReport(
                $this->options->endpoint,
                $report,
                $this->options->apiKey,
                $this->options->timeout,
            );
        } catch (TransportException $e) {
            if ($this->options->logToConsole) {
                error_log('[Oluso] failed to send error report: ' . $e->getMessage());
            }
            if ($this->options->enableOfflineQueue) {
                $this->queue->enqueue($report);
            }
            return;
        }

        if ($this->options->enableOfflineQueue && !$this->queue->isEmpty()) {
            $this->queue->processQueue(fn (array $r) => Transport::sendErrorReport(
                $this->options->endpoint,
                $r,
                $this->options->apiKey,
                $this->options->timeout,
            ));
        }
    }

    /**
     * Send any reports queued by deferSend (see Options), and process the
     * offline queue. Call this after the response has been sent to the
     * client (e.g. from a "terminate" hook) if deferSend is enabled, or any
     * time you want to make sure nothing is left unsent.
     */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $report) {
            $this->sendReport($report);
        }

        if ($this->options->enableOfflineQueue && !$this->queue->isEmpty()) {
            $this->queue->processQueue(fn (array $r) => Transport::sendErrorReport(
                $this->options->endpoint,
                $r,
                $this->options->apiKey,
                $this->options->timeout,
            ));
        }
    }

    private static function errorTitle(\Throwable $error): string
    {
        $message = $error->getMessage();
        $firstLine = trim(explode("\n", $message, 2)[0]);
        if ($firstLine !== '') {
            return strlen($firstLine) <= 100 ? $firstLine : substr($firstLine, 0, 97) . '...';
        }
        return $error::class . ' error';
    }
}
