<?php

declare(strict_types=1);

namespace Oluso;

final class Sanitizer
{
    private const REDACTED = '[REDACTED]';

    private const DEFAULT_SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey',
        'access_token', 'auth', 'credentials', 'mysql_pwd', 'private_key',
        'privatekey', 'session', 'cookie', 'csrf', 'xsrf', 'authorization',
        'bearer', 'jwt', 'ssn', 'social_security', 'credit_card', 'card_number',
        'cvv', 'pin',
    ];

    /** @var string[] */
    private readonly array $sensitiveKeys;

    /**
     * @param string[] $customSensitiveKeys
     */
    public function __construct(array $customSensitiveKeys = [])
    {
        $this->sensitiveKeys = [...self::DEFAULT_SENSITIVE_KEYS, ...$customSensitiveKeys];
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->sensitiveKeys as $sensitive) {
            if (stripos($key, $sensitive) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string>
     */
    public function sanitizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $lower = strtolower($key);
            $flat = is_array($value) ? implode(', ', $value) : (string) $value;

            $out[$key] = ($lower === 'authorization' || $lower === 'cookie' || $this->isSensitiveKey($key))
                ? self::REDACTED
                : $flat;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    public function sanitizeQuery(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $out[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $out[$key] = implode(', ', array_map('strval', $value));
            } else {
                $out[$key] = (string) $value;
            }
        }
        return $out;
    }

    /**
     * Recursively redact sensitive keys from arbitrary array data. PHP
     * arrays built from request/JSON data can't contain reference cycles in
     * the way an arbitrary object graph could, so no circular-reference
     * guard is needed.
     */
    public function sanitizeValue(mixed $value, int $maxDepth = 10): mixed
    {
        if ($maxDepth <= 0) {
            return '[Max Depth Reached]';
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $val) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $out[$key] = self::REDACTED;
                } else {
                    $out[$key] = $this->sanitizeValue($val, $maxDepth - 1);
                }
            }
            return $out;
        }

        return $value;
    }

    public static function truncateString(string $value, int $maxLength = 1000): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        return substr($value, 0, $maxLength) . '... [truncated]';
    }
}
