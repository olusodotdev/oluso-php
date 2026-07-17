<?php

declare(strict_types=1);

namespace Oluso;

final class Fingerprint
{
    /**
     * Produce a stable identifier for grouping similar errors together,
     * from the error's class, a normalized version of its message (with
     * dynamic values like IDs and paths stripped), and a signature built
     * from its stack trace.
     *
     * Uses a plain sha256 (truncated) rather than treating this as a
     * security-sensitive hash: deduplication only needs a stable,
     * well-distributed hash, not a cryptographic one.
     */
    public static function generate(\Throwable $error, ?string $stackTrace = null): string
    {
        $components = [$error::class, self::normalizeMessage($error->getMessage())];

        $stackTrace ??= $error->getTraceAsString();
        if ($stackTrace !== '') {
            $components[] = self::stackSignature($stackTrace);
        }

        return substr(hash('sha256', implode('|', $components)), 0, 8);
    }

    private static function normalizeMessage(string $message): string
    {
        $message = preg_replace('/\d+/', 'N', $message) ?? $message;
        $message = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            'UUID',
            $message,
        ) ?? $message;
        $message = preg_replace('#/[\w/.\-]+#', 'PATH', $message) ?? $message;
        $message = preg_replace('#https?://\S+#', 'URL', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;
        return trim($message);
    }

    /**
     * Extract just the call signature from each stack frame (e.g.
     * "Foo->bar()"), dropping the file path, line number, and literal
     * arguments -- all of which are too volatile across deployments/calls
     * to be useful for grouping, but the call sequence itself is exactly
     * what identifies "the same logical error" across occurrences.
     */
    private static function stackSignature(string $stack, int $limit = 6): string
    {
        $frames = [];
        foreach (explode("\n", $stack) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^#\d+\s+.+\(\d+\):\s*(.+)$/', $line, $m)) {
                continue; // e.g. the trailing "#N {main}" line
            }
            $frames[] = preg_replace('/\(.*\)$/', '()', $m[1]) ?? $m[1];
            if (count($frames) >= $limit) {
                break;
            }
        }
        return implode('->', $frames);
    }
}
