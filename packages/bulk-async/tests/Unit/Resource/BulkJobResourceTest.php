<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Resource;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Action\CancelBulkJobAction;
use Polysource\BulkAsync\DataSource\BulkJobDataSource;
use Polysource\BulkAsync\Filter\BulkJobFilter;
use Polysource\BulkAsync\Resource\BulkJobResource;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Core\Action\ActionInterface;

final class BulkJobResourceTest extends TestCase
{
    public function testExposesExpectedSlugAndPermission(): void
    {
        $resource = $this->makeResource();

        self::assertSame('bulk-jobs', $resource->getName());
        self::assertSame('Bulk jobs', $resource->getLabel());
        self::assertSame('id', $resource->getIdentifierProperty());
        self::assertSame('POLYSOURCE_BULK_JOB_VIEW', $resource->getPermission());
    }

    public function testShipsTheFourCanonicalFilters(): void
    {
        $resource = $this->makeResource();

        $properties = [];
        foreach ($resource->configureFilters() as $filter) {
            self::assertInstanceOf(BulkJobFilter::class, $filter);
            $properties[] = $filter->getProperty();
        }

        self::assertSame(['actorId', 'status', 'createdAt', 'resourceName'], $properties);
    }

    public function testNoFieldsByDefault(): void
    {
        $fields = [];
        foreach ($this->makeResource()->configureFields('index') as $field) {
            $fields[] = $field;
        }
        self::assertSame([], $fields);
    }

    public function testCustomActionsArePassedThrough(): void
    {
        $cancel = new CancelBulkJobAction(new InMemoryBulkJobStorage());
        $resource = new BulkJobResource(
            $this->makeDataSource(),
            'bulk-jobs',
            [$cancel],
        );

        $actions = [];
        foreach ($resource->configureActions() as $action) {
            self::assertInstanceOf(ActionInterface::class, $action);
            $actions[] = $action;
        }

        self::assertSame([$cancel], $actions);
    }

    private function makeResource(): BulkJobResource
    {
        return new BulkJobResource($this->makeDataSource());
    }

    private function makeDataSource(): BulkJobDataSource
    {
        $em = $this->createMock(EntityManagerInterface::class);

        return new BulkJobDataSource($em);
    }
}
