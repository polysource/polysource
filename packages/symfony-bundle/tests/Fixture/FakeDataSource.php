<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Fixture;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;

/**
 * In-memory data source for tests. Records are passed at construction.
 */
final class FakeDataSource implements DataSourceInterface
{
    /**
     * @param list<DataRecord> $records
     */
    public function __construct(
        private readonly array $records = [],
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        return new DataPage($this->records, \count($this->records));
    }

    public function find(string|int $identifier): ?DataRecord
    {
        foreach ($this->records as $record) {
            if ($record->identifier === $identifier) {
                return $record;
            }
        }

        return null;
    }

    public function count(DataQuery $query): int
    {
        return \count($this->records);
    }
}
