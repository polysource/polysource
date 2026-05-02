<?php

declare(strict_types=1);

namespace Polysource\Core\DataSource;

use Polysource\Core\Query\DataRecord;

/**
 * Optional extension for sources that can fetch multiple records in one call.
 *
 * Implementing this interface enables batched lookups, which prevents N+1
 * queries when rendering a list of resources that reference each other
 * (e.g. a list of orders pointing to user records).
 *
 * Adapters where `findMany([1,2,3])` is just three `find()` calls in a loop
 * SHOULD NOT implement this interface — the default {@see DataSourceInterface}
 * fallback is fine.
 */
interface BatchableDataSourceInterface extends DataSourceInterface
{
    /**
     * @param  list<string|int>           $identifiers
     * @return array<string|int, DataRecord> records keyed by their identifier; missing IDs are absent
     */
    public function findMany(array $identifiers): array;
}
