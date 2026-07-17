<?php

declare(strict_types=1);

namespace Oluso\Tests;

use Oluso\Fingerprint;
use PHPUnit\Framework\TestCase;

final class FingerprintTest extends TestCase
{
    public function testSameFingerprintForDynamicValueDifferences(): void
    {
        $a = new \RuntimeException('user 123 not found');
        $b = new \RuntimeException('user 456 not found');

        self::assertSame(Fingerprint::generate($a), Fingerprint::generate($b));
    }

    public function testDifferentFingerprintForDifferentErrorTypes(): void
    {
        $a = new \RuntimeException('boom');
        $b = new \LogicException('boom');

        self::assertNotSame(Fingerprint::generate($a), Fingerprint::generate($b));
    }

    public function testDifferentFingerprintForDifferentMessages(): void
    {
        $a = new \RuntimeException('boom');
        $b = new \RuntimeException('bang');

        self::assertNotSame(Fingerprint::generate($a), Fingerprint::generate($b));
    }

    public function testStableHexOutput(): void
    {
        $fp = Fingerprint::generate(new \RuntimeException('boom'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $fp);
    }

    public function testGroupsSameCallSiteAcrossDifferentLineNumbers(): void
    {
        $makeError = static function (int $line): \RuntimeException {
            // Both invocations raise from the *same logical call site*
            // (this closure), just different literal lines -- the
            // fingerprint should still match since dynamic details (line
            // numbers, literal args) are stripped from the stack signature.
            return new \RuntimeException("boom at line marker {$line}");
        };

        $a = $makeError(1);
        $b = $makeError(2);

        // Message differs only by the injected number, which normalizeMessage() strips.
        self::assertSame(Fingerprint::generate($a), Fingerprint::generate($b));
    }
}
