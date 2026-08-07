<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterOperator;

/**
 * In-memory source honouring Eq filters + offset/limit pagination —
 * what the embedded-listing tests need to prove parent scoping and
 * `rd_page` paging actually reach the data source (the plain
 * {@see InMemoryDataSource} ignores the query entirely).
 */
final class FilterableInMemoryDataSource implements DataSourceInterface
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
        $matching = array_values(array_filter(
            $this->records,
            static function (DataRecord $record) use ($query): bool {
                foreach ($query->filters as $criterion) {
                    if (FilterOperator::Eq !== $criterion->operator) {
                        return false;
                    }
                    $recordValue = $record->get($criterion->property);
                    if (!\is_scalar($recordValue) || !\is_scalar($criterion->value)) {
                        return false;
                    }
                    if ((string) $recordValue !== (string) $criterion->value) {
                        return false;
                    }
                }

                return true;
            },
        ));

        $offset = $query->pagination->offset ?? 0;
        $limit = $query->pagination->limit ?? 20;

        return new DataPage(
            items: \array_slice($matching, $offset, $limit),
            total: \count($matching),
        );
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
