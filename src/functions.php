<?php

declare(strict_types=1);

namespace Oluso;

/**
 * Remove null values from an array (keeping falsy-but-present values like
 * 0, '', and false, unlike array_filter()'s default callback).
 *
 * @internal
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function omit_null(array $data): array
{
    return array_filter($data, static fn (mixed $v): bool => $v !== null);
}

/**
 * Record a breadcrumb on the current scope (see Scope::start()). A no-op if
 * no scope is active.
 *
 * @param array<string, mixed>|null $data
 */
function add_breadcrumb(
    string $message,
    BreadcrumbLevel $level = BreadcrumbLevel::Info,
    ?string $category = null,
    ?array $data = null,
): void {
    Scope::current()?->addBreadcrumb(new Breadcrumb($message, $level, $category, $data));
}

/**
 * Set the user context on the current scope. A no-op if no scope is active.
 */
function set_user(UserContext $user): void
{
    Scope::current()?->setUser($user);
}

/**
 * Set a custom key/value on the current scope. A no-op if no scope is active.
 */
function set_custom_context(string $key, mixed $value): void
{
    Scope::current()?->setCustom($key, $value);
}
