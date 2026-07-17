<?php

declare(strict_types=1);

namespace Oluso;

/**
 * Caps how many errors are reported within a rolling one-minute window.
 *
 * Classic PHP-FPM/CLI has no shared memory across requests by default, so
 * this naturally resets per request there -- it only meaningfully persists
 * within a single request/script run, or across requests under a
 * persistent-worker runtime (Octane, Swoole), where it's actually the
 * desired behavior (rate limits should be shared across requests, not reset
 * every time).
 */
final class RateLimiter
{
    /** @var float[] */
    private array $timestamps = [];

    private readonly int $maxPerMinute;

    /** @var callable(): float */
    private $now;

    /**
     * @param (callable(): float)|null $now overridable clock, for tests
     */
    public function __construct(int $maxPerMinute = 60, ?callable $now = null)
    {
        $this->maxPerMinute = $maxPerMinute > 0 ? $maxPerMinute : 60;
        $this->now = $now ?? static fn (): float => microtime(true);
    }

    public function canSend(): bool
    {
        $now = ($this->now)();
        $cutoff = $now - 60.0;
        $this->timestamps = array_values(array_filter(
            $this->timestamps,
            static fn (float $ts): bool => $ts > $cutoff,
        ));

        if (count($this->timestamps) < $this->maxPerMinute) {
            $this->timestamps[] = $now;
            return true;
        }
        return false;
    }

    public function count(): int
    {
        $cutoff = ($this->now)() - 60.0;
        $this->timestamps = array_values(array_filter(
            $this->timestamps,
            static fn (float $ts): bool => $ts > $cutoff,
        ));
        return count($this->timestamps);
    }

    public function reset(): void
    {
        $this->timestamps = [];
    }
}
