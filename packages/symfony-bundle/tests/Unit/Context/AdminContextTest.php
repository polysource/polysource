<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Core\Query\DataQuery;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AdminContext::class)]
final class AdminContextTest extends TestCase
{
    #[Test]
    public function withQueryReturnsNewInstanceAndPreservesOtherFields(): void
    {
        $request = new Request();
        $resource = new FakeResource('flags');
        $original = new AdminContext(
            request: $request,
            resource: $resource,
            action: 'index',
            recordId: null,
            locale: 'en',
            user: null,
            query: new DataQuery('flags'),
        );

        $newQuery = (new DataQuery('flags'))->withSearchText('hello');
        $derived = $original->withQuery($newQuery);

        self::assertNotSame($original, $derived);
        self::assertSame($newQuery, $derived->query);
        self::assertSame('flags', $derived->query->resourceName);
        self::assertSame('hello', $derived->query->searchText);
        self::assertSame($request, $derived->request);
        self::assertSame($resource, $derived->resource);
        self::assertSame('index', $derived->action);
        self::assertSame('en', $derived->locale);
    }
}
