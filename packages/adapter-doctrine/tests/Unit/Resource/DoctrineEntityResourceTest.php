<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Tests\Unit\Resource;

use ArrayIterator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Doctrine\DataSource\DoctrineDataSource;
use Polysource\Adapter\Doctrine\Resource\DoctrineEntityResource;
use Polysource\Adapter\Doctrine\Tests\Fixture\Product;
use Polysource\Core\Action\ActionInterface;

final class DoctrineEntityResourceTest extends TestCase
{
    public function testExposesConstructorValues(): void
    {
        $resource = new ConcreteResource(
            $this->makeDataSource(),
            'products',
            'Catalogue',
            'sku',
            'POLYSOURCE_PRODUCT_VIEW',
            [],
        );

        self::assertSame('products', $resource->getName());
        self::assertSame('Catalogue', $resource->getLabel());
        self::assertSame('sku', $resource->getIdentifierProperty());
        self::assertSame('POLYSOURCE_PRODUCT_VIEW', $resource->getPermission());
    }

    public function testNullPermissionMeansNoResourceLevelGate(): void
    {
        $resource = new ConcreteResource($this->makeDataSource(), 'p', 'P');

        self::assertNull($resource->getPermission());
    }

    public function testActionsArePassedThrough(): void
    {
        $action = $this->createMock(ActionInterface::class);
        $resource = new ConcreteResource($this->makeDataSource(), 'p', 'P', actions: [$action]);

        self::assertSame([$action], iterator_to_array($this->collect($resource->configureActions())));
    }

    public function testNoFieldsByDefault(): void
    {
        $resource = new ConcreteResource($this->makeDataSource(), 'p', 'P');
        self::assertSame([], iterator_to_array($this->collect($resource->configureFields('index'))));
    }

    private function makeDataSource(): DoctrineDataSource
    {
        $metadata = new ClassMetadata(Product::class);
        $metadata->setIdentifier(['id']);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
        $metadata->mapField(['fieldName' => 'name', 'type' => 'string']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        return new DoctrineDataSource($em, Product::class);
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return ArrayIterator<int, mixed>
     */
    private function collect(iterable $iterable): ArrayIterator
    {
        if (\is_array($iterable)) {
            return new ArrayIterator(array_values($iterable));
        }

        return new ArrayIterator(array_values(iterator_to_array($iterable, false)));
    }
}

final class ConcreteResource extends DoctrineEntityResource
{
    public function configureFilters(): iterable
    {
        return [];
    }
}
