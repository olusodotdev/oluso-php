<?php

declare(strict_types=1);

namespace Oluso;

final class RequestContext
{
    /**
     * @param array<string, string>|null $headers
     * @param array<string, mixed>|null $query
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly ?array $headers = null,
        public readonly ?array $query = null,
        public readonly mixed $body = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?int $responseTimeMs = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return omit_null([
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'query' => $this->query,
            'body' => $this->body,
            'ip' => $this->ip,
            'userAgent' => $this->userAgent,
            'responseTime' => $this->responseTimeMs,
        ]);
    }
}
