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
        self::assertSame([], self::collectIterable($r->configureFields('index')));
        self::assertSame([], self::collectIterable($r->configureActions()));
        self::assertSame([], self::collectIterable($r->configureFilters()));
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
                return null;
            }
        };
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return list<mixed>
     */
    private static function collectIterable(iterable $iterable): array
    {
        $out = [];
        foreach ($iterable as $value) {
            $out[] = $value;
        }

        return $out;
    }
}
