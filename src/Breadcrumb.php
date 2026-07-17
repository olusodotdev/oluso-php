<?php

declare(strict_types=1);

namespace Oluso;

final class Breadcrumb
{
    public ?float $timestamp = null;

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly string $message,
        public readonly BreadcrumbLevel $level = BreadcrumbLevel::Info,
        public readonly ?string $category = null,
        public readonly ?array $data = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return omit_null([
            'timestamp' => $this->timestamp !== null ? (int) round($this->timestamp * 1000) : null,
            'message' => $this->message,
            'level' => $this->level->value,
            'category' => $this->category,
            'data' => $this->data,
        ]);
    }
}
