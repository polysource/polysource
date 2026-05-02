<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

#[CoversClass(AbstractResource::class)]
final class AbstractResourceTest extends TestCase
{
    #[Test]
    public function defaultsAreSensible(): void
    {
        $ds = $this->dataSourceStub();
        $r = new class($ds) extends AbstractResource {
            public function getName(): string
            {
                return 'demo';
            }

            public function getLabel(): string
            {
                return 'Demo';
            }
        };

        self::assertSame('id', $r->getIdentifierProperty());
        self::assertSame($ds, $r->getDataSource());
        self::assertSame([], \iterator_to_array($this->toIterable($r->configureFields('index')), false));
        self::assertSame([], \iterator_to_array($this->toIterable($r->configureActions()), false));
        self::assertSame([], \iterator_to_array($this->toIterable($r->configureFilters()), false));
        self::assertNull($r->getPermission());
    }

    private function dataSourceStub(): DataSourceInterface
    {
        return new class implements DataSourceInterface {
            public function search(DataQuery $query): DataPage
            {
                return new DataPage([], 0);
            }

            public function find(string|int $identifier): ?DataRecord
            {
                return null;
            }

            public function count(DataQuery $query): ?int
            {
                return 0;
            }
        };
    }

    /**
     * @param iterable<mixed> $iterable
     */
    private function toIterable(iterable $iterable): iterable
    {
        return $iterable;
    }
}
