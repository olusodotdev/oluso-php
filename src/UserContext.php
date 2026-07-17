<?php

declare(strict_types=1);

namespace Oluso;

final class UserContext
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $email = null,
        public readonly ?string $username = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return omit_null([
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'extra' => $this->extra !== [] ? $this->extra : null,
        ]);
    }
}
