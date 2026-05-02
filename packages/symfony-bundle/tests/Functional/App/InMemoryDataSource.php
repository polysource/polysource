<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;

/**
 * Hand-rolled in-memory data source used by the bundle smoke tests.
 *
 * Lives next to the TestKernel rather than under `Tests\Fixture\` so that
 * the kernel cache build can autoload it without relying on the unit-test
 * fixture namespace (which is autoload-dev only).
 */
final class InMemoryDataSource implements DataSourceInterface
{
    /**
     * @param list<DataRecord> $records
     */
    public function __construct(
        private readonly array $records,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        return new DataPage(items: $this->records, total: \count($this->records));
    }

    public function find(string|int $identifier): ?DataRecord
    {
        foreach ($this->records as $record) {
            if ((string) $record->identifier === (string) $identifier) {
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
