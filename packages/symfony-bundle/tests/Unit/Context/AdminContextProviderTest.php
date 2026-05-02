<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Context\AdminContextProvider;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Core\Query\DataQuery;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AdminContextProvider::class)]
final class AdminContextProviderTest extends TestCase
{
    #[Test]
    public function defaultsToNullContext(): void
    {
        $provider = new AdminContextProvider();
        self::assertNull($provider->getContext());
    }

    #[Test]
    public function setAndGetContextRoundtrips(): void
    {
        $provider = new AdminContextProvider();
        $context = $this->buildContext();

        $provider->setContext($context);

        self::assertSame($context, $provider->getContext());
    }

    #[Test]
    public function resetClearsTheContext(): void
    {
        $provider = new AdminContextProvider();
        $provider->setContext($this->buildContext());

        $provider->reset();

        self::assertNull($provider->getContext());
    }

    private function buildContext(): AdminContext
    {
        return new AdminContext(
            request: new Request(),
            resource: new FakeResource('flags'),
            action: 'index',
            recordId: null,
            locale: 'en',
            user: null,
            query: new DataQuery('flags'),
        );
    }
}
