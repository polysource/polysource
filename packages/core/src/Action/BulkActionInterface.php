<?php

declare(strict_types=1);

namespace Polysource\Core\Action;

use Polysource\Core\Query\DataRecord;

/**
 * Action that operates on multiple records at once (checkbox selection
 * + "Apply"). Examples: "Delete selected", "Retry all", "Mark as resolved".
 */
interface BulkActionInterface extends ActionInterface
{
    /**
     * @param iterable<DataRecord> $records
     */
    public function executeBatch(iterable $records): ActionResult;
}
