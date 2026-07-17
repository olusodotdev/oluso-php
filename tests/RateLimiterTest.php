<?php

declare(strict_types=1);

namespace Oluso\Tests;

use Oluso\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testAllowsSendsUnderLimit(): void
    {
        $rl = new RateLimiter(3);
        self::assertTrue($rl->canSend());
        self::assertTrue($rl->canSend());
        self::assertTrue($rl->canSend());
        self::assertSame(3, $rl->count());
    }

    public function testBlocksOnceLimitReached(): void
    {
        $rl = new RateLimiter(2);
        self::assertTrue($rl->canSend());
        self::assertTrue($rl->canSend());
        self::assertFalse($rl->canSend());
        self::assertSame(2, $rl->count());
    }

    public function testAllowsAgainAfterWindowPasses(): void
    {
        $now = 0.0;
        $rl = new RateLimiter(1, now: function () use (&$now): float {
            return $now;
        });

        self::assertTrue($rl->canSend());
        self::assertFalse($rl->canSend());

        $now = 61.0;
        self::assertTrue($rl->canSend());
    }

    public function testResetClearsTrackedTimestamps(): void
    {
        $rl = new RateLimiter(1);
        self::assertTrue($rl->canSend());
        self::assertFalse($rl->canSend());
        $rl->reset();
        self::assertTrue($rl->canSend());
    }
}
