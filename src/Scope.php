<?php

declare(strict_types=1);

namespace Oluso;

/**
 * Tracks breadcrumbs/user/custom context for "the current unit of work"
 * (typically one HTTP request).
 *
 * Classic PHP-FPM/CLI is shared-nothing per request (a fresh process or a
 * full engine reset between requests), so a simple static holder is safe
 * there -- there's no cross-request leakage to guard against the way
 * Node/Python/Go need AsyncLocalStorage/contextvars/context.Context for.
 * Framework integrations still call start() at the beginning of each
 * request (mirroring those other SDKs) both for a consistent API and
 * because it's also correct under persistent-worker runtimes (Octane,
 * Swoole), where the same worker process handles many requests in
 * sequence. The one thing this does *not* handle safely is true
 * *concurrent* requests sharing one PHP process via coroutines (Swoole
 * coroutines specifically) -- that needs coroutine-local storage, which is
 * out of scope for this first pass.
 */
final class Scope
{
    private static ?self $current = null;

    /** @var Breadcrumb[] */
    private array $breadcrumbs = [];

    private ?UserContext $user = null;

    /** @var array<string, mixed> */
    private array $custom = [];

    private ?float $requestStart = null;

    public function __construct(private readonly int $maxBreadcrumbs = 30)
    {
    }

    public static function start(int $maxBreadcrumbs = 30): self
    {
        return self::$current = new self($maxBreadcrumbs);
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        $breadcrumb->timestamp = microtime(true);
        $this->breadcrumbs[] = $breadcrumb;
        if (count($this->breadcrumbs) > $this->maxBreadcrumbs) {
            array_shift($this->breadcrumbs);
        }
    }

    public function setUser(UserContext $user): void
    {
        $this->user = $user;
    }

    public function setCustom(string $key, mixed $value): void
    {
        $this->custom[$key] = $value;
    }

    public function setRequestStart(float $timestamp): void
    {
        $this->requestStart = $timestamp;
    }

    public function requestStart(): ?float
    {
        return $this->requestStart;
    }

    /**
     * @return array{0: Breadcrumb[], 1: ?UserContext, 2: array<string, mixed>}
     */
    public function snapshot(): array
    {
        return [$this->breadcrumbs, $this->user, $this->custom];
    }
}
