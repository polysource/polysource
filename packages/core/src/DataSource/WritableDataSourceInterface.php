<?php

declare(strict_types=1);

namespace Polysource\Core\DataSource;

use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataRecord;

/**
 * Extends {@see DataSourceInterface} with create/update/delete operations.
 *
 * Adapters that only support reading do not implement this interface
 * (Interface Segregation Principle, cf. ADR architecture).
 *
 * The UI auto-detects whether a source is writable and toggles the
 * Create/Edit/Delete buttons accordingly.
 */
interface WritableDataSourceInterface extends DataSourceInterface
{
    /**
     * Create a new record from the given payload. Returns the created record.
     */
    public function create(DataPayload $payload): DataRecord;

    /**
     * Update an existing record. Returns the updated record.
     */
    public function update(string|int $identifier, DataPayload $payload): DataRecord;

    /**
     * Delete a record by identifier.
     */
    public function delete(string|int $identifier): void;
}
