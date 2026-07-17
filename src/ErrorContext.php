<?php

declare(strict_types=1);

namespace Oluso;

final class ErrorContext
{
    /**
     * @param array<string, mixed> $custom
     * @param Breadcrumb[] $breadcrumbs
     */
    public function __construct(
        public readonly ?RequestContext $request = null,
        public readonly ?UserContext $user = null,
        public readonly ?ServerContext $server = null,
        public readonly array $custom = [],
        public readonly array $breadcrumbs = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return omit_null([
            'request' => $this->request?->toArray(),
            'user' => $this->user?->toArray(),
            'server' => $this->server?->toArray(),
            'custom' => $this->custom !== [] ? $this->custom : null,
            'breadcrumbs' => $this->breadcrumbs !== []
                ? array_map(static fn (Breadcrumb $b): array => $b->toArray(), $this->breadcrumbs)
                : null,
        ]);
    }
}
