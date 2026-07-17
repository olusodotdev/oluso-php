<?php

declare(strict_types=1);

namespace Oluso\Tests\Support;

/**
 * A real local HTTP server (PHP's built-in dev server), used instead of
 * mocking cURL -- exercises the actual Transport code path end to end, the
 * same approach used for the Go/Python SDKs' tests.
 */
final class RecordingServer
{
    public readonly string $url;

    private readonly string $host;

    private readonly int $port;

    private readonly string $docRoot;

    private readonly string $resultsFile;

    private readonly string $failFlag;

    /** @var resource|null */
    private $process = null;

    public function __construct()
    {
        $this->host = '127.0.0.1';
        $this->port = random_int(20000, 60000);
        $this->docRoot = sys_get_temp_dir() . '/oluso-test-server-' . bin2hex(random_bytes(4));
        mkdir($this->docRoot, 0755, true);

        $this->resultsFile = $this->docRoot . '/results.jsonl';
        file_put_contents($this->resultsFile, '');
        $this->failFlag = $this->docRoot . '/fail.flag';

        $router = <<<PHP
<?php
\$body = file_get_contents('php://input');
\$headers = [];
foreach (\$_SERVER as \$key => \$value) {
    if (str_starts_with(\$key, 'HTTP_')) {
        \$headers[\$key] = \$value;
    }
}
\$record = ['body' => json_decode(\$body, true), 'headers' => \$headers];
file_put_contents('{$this->resultsFile}', json_encode(\$record) . "\\n", FILE_APPEND | LOCK_EX);
http_response_code(file_exists('{$this->failFlag}') ? 503 : 200);
PHP;
        file_put_contents($this->docRoot . '/index.php', $router);

        $cmd = sprintf(
            'exec php -S %s:%d -t %s',
            $this->host,
            $this->port,
            escapeshellarg($this->docRoot),
        );
        $this->process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        $this->url = "http://{$this->host}:{$this->port}/index.php";
        $this->waitUntilUp();
    }

    private function waitUntilUp(): void
    {
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen($this->host, $this->port, timeout: 0.2);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(20_000);
        }
        throw new \RuntimeException('oluso test server did not come up in time');
    }

    public function setFail(bool $fail): void
    {
        if ($fail) {
            touch($this->failFlag);
        } else {
            @unlink($this->failFlag);
        }
    }

    /**
     * @return array<int, array{body: array<string, mixed>, headers: array<string, string>}>
     */
    public function requests(): array
    {
        $contents = (string) @file_get_contents($this->resultsFile);
        $lines = array_filter(explode("\n", $contents), static fn (string $l): bool => $l !== '');
        return array_values(array_map(
            static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $lines,
        ));
    }

    public function count(): int
    {
        return count($this->requests());
    }

    /**
     * @return array{body: array<string, mixed>, headers: array<string, string>}
     */
    public function last(): array
    {
        $requests = $this->requests();
        if ($requests === []) {
            throw new \RuntimeException('no requests received yet');
        }
        return $requests[count($requests) - 1];
    }

    public function waitFor(callable $condition, float $timeout = 3.0): void
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            if ($condition()) {
                return;
            }
            usleep(10_000);
        }
        throw new \RuntimeException('condition not met before timeout');
    }

    public function close(): void
    {
        if ($this->process !== null && is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        @unlink($this->resultsFile);
        @unlink($this->failFlag);
        @unlink($this->docRoot . '/index.php');
        @rmdir($this->docRoot);
    }
}
