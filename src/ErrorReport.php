<?php

declare(strict_types=1);

namespace Oluso;

final class ErrorReport
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $stackTrace = null,
        public readonly ?string $environment = null,
        public readonly ?string $severity = null,
        public readonly array $tags = [],
        public readonly ?string $fingerprint = null,
        public readonly ?ErrorContext $context = null,
        public readonly ?int $timestampMs = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return omit_null([
            'title' => $this->title,
            'message' => $this->message,
            'stack_trace' => $this->stackTrace,
            'environment' => $this->environment,
            'severity' => $this->severity,
            'tags' => $this->tags !== [] ? $this->tags : null,
            'fingerprint' => $this->fingerprint,
            'context' => $this->context?->toArray(),
            'timestamp' => $this->timestampMs,
        ]);
    }
}
