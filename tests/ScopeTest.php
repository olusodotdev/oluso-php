<?php

declare(strict_types=1);

namespace Oluso\Tests;

use function Oluso\add_breadcrumb;

use Oluso\Scope;

use function Oluso\set_custom_context;
use function Oluso\set_user;

use Oluso\UserContext;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Scope::clear();
    }

    public function testAddBreadcrumbAndSnapshot(): void
    {
        Scope::start();
        add_breadcrumb('step 1');
        add_breadcrumb('step 2');
        set_user(new UserContext(id: 'user-1'));
        set_custom_context('cartId', 'cart-42');

        [$breadcrumbs, $user, $custom] = Scope::current()->snapshot();

        self::assertSame(['step 1', 'step 2'], array_map(static fn ($b) => $b->message, $breadcrumbs));
        self::assertSame('user-1', $user->id);
        self::assertSame('cart-42', $custom['cartId']);
    }

    public function testMaxBreadcrumbsEvictsOldest(): void
    {
        Scope::start(maxBreadcrumbs: 2);
        add_breadcrumb('1');
        add_breadcrumb('2');
        add_breadcrumb('3');

        [$breadcrumbs] = Scope::current()->snapshot();
        self::assertSame(['2', '3'], array_map(static fn ($b) => $b->message, $breadcrumbs));
    }

    public function testNoOpWithoutStart(): void
    {
        // None of these should throw even though there's no active scope.
        add_breadcrumb('ignored');
        set_user(new UserContext(id: 'ignored'));
        set_custom_context('k', 'v');

        self::assertNull(Scope::current());
    }

    public function testStartReplacesPreviousScope(): void
    {
        Scope::start();
        add_breadcrumb('only in first scope');

        Scope::start(); // simulates the next request reusing this worker process
        [$breadcrumbs] = Scope::current()->snapshot();

        self::assertSame([], $breadcrumbs);
    }
}
