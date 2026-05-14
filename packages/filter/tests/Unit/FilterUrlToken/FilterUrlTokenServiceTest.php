<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\FilterUrlToken;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;
use Polysource\Filter\FilterUrlToken\Storage\InMemoryFilterUrlTokenStorage;

#[CoversClass(FilterUrlTokenService::class)]
#[CoversClass(InMemoryFilterUrlTokenStorage::class)]
#[CoversClass(FilterUrlToken::class)]
final class FilterUrlTokenServiceTest extends TestCase
{
    #[Test]
    public function tokenizeReturnsNullForEmptySlice(): void
    {
        $service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());

        self::assertNull($service->tokenize('orders', []));
    }

    #[Test]
    public function tokenizeMintsAHexTokenAndStoresTheSlice(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $service = new FilterUrlTokenService($storage);

        $token = $service->tokenize('orders', ['status' => 'paid']);

        self::assertNotNull($token);
        self::assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $token->token);
        self::assertSame('orders', $token->resourceName);
        self::assertSame(['status' => 'paid'], $token->filtersSlice);

        $resolved = $service->resolve($token->token);
        self::assertNotNull($resolved);
        self::assertSame(['status' => 'paid'], $resolved->filtersSlice);
    }

    #[Test]
    public function resolveReturnsNullForUnknownToken(): void
    {
        $service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());

        self::assertNull($service->resolve('aabbccddeeff'));
    }

    #[Test]
    public function resolveRejectsMalformedToken(): void
    {
        $service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());

        // Wrong length / non-hex chars are rejected at the gate;
        // the storage never sees them (prevents user-input being
        // passed verbatim to the storage layer).
        self::assertNull($service->resolve('not-a-real-token'));
        self::assertNull($service->resolve('TOOSHORT'));
        self::assertNull($service->resolve('ZZZZZZZZZZZZ'));
    }

    #[Test]
    public function modelRejectsMalformedTokenAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterUrlToken('NOT-HEX-12', 'orders', ['status' => 'paid'], new DateTimeImmutable());
    }

    #[Test]
    public function modelRejectsEmptySlice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterUrlToken('aabbccddeeff', 'orders', [], new DateTimeImmutable());
    }

    #[Test]
    public function tokenizeProducesUniqueTokensAcrossCalls(): void
    {
        $service = new FilterUrlTokenService(new InMemoryFilterUrlTokenStorage());

        $a = $service->tokenize('orders', ['status' => 'paid']);
        $b = $service->tokenize('orders', ['status' => 'archived']);

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotSame($a->token, $b->token);
    }
}
