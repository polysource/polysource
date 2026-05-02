<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Registry;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Core\Exception\ResourceNotFoundException;

#[CoversClass(ResourceRegistry::class)]
final class ResourceRegistryTest extends TestCase
{
    #[Test]
    public function getReturnsResourceByItsSlug(): void
    {
        $registry = new ResourceRegistry([new FakeResource('failed-messages')]);

        self::assertSame('failed-messages', $registry->get('failed-messages')->getName());
    }

    #[Test]
    public function hasReportsRegisteredResources(): void
    {
        $registry = new ResourceRegistry([new FakeResource('flags')]);

        self::assertTrue($registry->has('flags'));
        self::assertFalse($registry->has('unknown'));
    }

    #[Test]
    public function getThrowsWhenResourceIsUnknown(): void
    {
        $registry = new ResourceRegistry([]);

        $this->expectException(ResourceNotFoundException::class);
        $registry->get('missing');
    }

    #[Test]
    public function allReturnsMapKeyedByResourceName(): void
    {
        $registry = new ResourceRegistry([
            new FakeResource('flags'),
            new FakeResource('failed-messages'),
        ]);

        $all = $registry->all();
        self::assertCount(2, $all);
        self::assertArrayHasKey('flags', $all);
        self::assertArrayHasKey('failed-messages', $all);
    }

    #[Test]
    public function indexingDuplicateNamesThrows(): void
    {
        $registry = new ResourceRegistry([
            new FakeResource('flags'),
            new FakeResource('flags'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate Polysource resource name "flags"');
        $registry->all();
    }
}
