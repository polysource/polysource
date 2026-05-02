<?php

declare(strict_types=1);

namespace Polysource\Core\DataSource;

use Polysource\Core\Exception\UnsupportedOperationException;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;

/**
 * Minimal read-only contract for any data source that Polysource can administer.
 *
 * Cf. ADR-001/002 for the rationale of the 3-method contract.
 *
 * Adapters that don't support a specific operation MUST throw
 * {@see UnsupportedOperationException} rather than returning a fake value.
 * The UI will detect this and adapt (e.g. hide the count, or fall back
 * to cursor pagination).
 */
interface DataSourceInterface
{
    /**
     * List records matching the given query.
     */
    public function search(DataQuery $query): DataPage;

    /**
     * Find a single record by its identifier. Returns null if not found.
     */
    public function find(string|int $identifier): ?DataRecord;

    /**
     * Total number of records matching the query.
     *
     * Returns:
     *   - int : exact count (e.g. Doctrine COUNT query)
     *   - null: count is unknown or too expensive (e.g. Messenger failed,
     *           Redis SCAN, S3 list); the UI will use cursor pagination.
     */
    public function count(DataQuery $query): ?int;
}
