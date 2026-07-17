<?php

declare(strict_types=1);

namespace Oluso\Tests;

use Oluso\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    public function testSanitizeHeadersRedactsAuthAndCookie(): void
    {
        $sanitizer = new Sanitizer();

        $result = $sanitizer->sanitizeHeaders([
            'Authorization' => 'Bearer secret-token',
            'Cookie' => 'session=abc123',
            'X-Request-Id' => 'req-1',
        ]);

        self::assertSame('[REDACTED]', $result['Authorization']);
        self::assertSame('[REDACTED]', $result['Cookie']);
        self::assertSame('req-1', $result['X-Request-Id']);
    }

    public function testSanitizeValueRedactsSensitiveKeys(): void
    {
        $sanitizer = new Sanitizer(['internal_id']);

        $result = $sanitizer->sanitizeValue([
            'username' => 'alice',
            'password' => 'hunter2',
            'internal_id' => '42',
            'nested' => ['api_key' => 'xyz', 'note' => 'hello'],
        ]);

        self::assertSame('alice', $result['username']);
        self::assertSame('[REDACTED]', $result['password']);
        self::assertSame('[REDACTED]', $result['internal_id']);
        self::assertSame('[REDACTED]', $result['nested']['api_key']);
        self::assertSame('hello', $result['nested']['note']);
    }

    public function testSanitizeValueHandlesLists(): void
    {
        $sanitizer = new Sanitizer();

        $result = $sanitizer->sanitizeValue([
            ['token' => 'abc'],
            'plain string',
        ]);

        self::assertSame('[REDACTED]', $result[0]['token']);
        self::assertSame('plain string', $result[1]);
    }

    public function testSanitizeValueMaxDepth(): void
    {
        $sanitizer = new Sanitizer();
        $nested = ['a' => ['b' => ['c' => ['d' => 'too deep']]]];

        $result = $sanitizer->sanitizeValue($nested, maxDepth: 2);

        self::assertSame('[Max Depth Reached]', $result['a']['b']);
    }

    public function testTruncateString(): void
    {
        self::assertSame('short', Sanitizer::truncateString('short', 100));
        self::assertSame('01234... [truncated]', Sanitizer::truncateString('0123456789', 5));
    }
}
