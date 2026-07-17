<?php

declare(strict_types=1);

namespace Oluso;

final class Transport
{
    /**
     * POST $report to $endpoint. Throws TransportException on any failure
     * (network error, timeout, or non-2xx status) so callers can decide how
     * to handle it (e.g. enqueue for retry) -- this never fails silently.
     *
     * @param array<string, mixed> $report
     */
    public static function sendErrorReport(
        string $endpoint,
        array $report,
        string $apiKey,
        float $timeoutSeconds,
    ): void {
        $body = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new TransportException('oluso: failed to encode report as JSON: ' . json_last_error_msg());
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new TransportException('oluso: failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-oluso-signature: ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) round($timeoutSeconds * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($timeoutSeconds * 1000),
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new TransportException("oluso: send report: {$error}");
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException("oluso: reporting failed with status {$statusCode}");
        }
    }
}
