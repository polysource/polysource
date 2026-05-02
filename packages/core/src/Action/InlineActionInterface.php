<?php

declare(strict_types=1);

namespace Polysource\Core\Action;

use Polysource\Core\Query\DataRecord;

/**
 * Action that operates on a single record (inline button on a list row,
 * or in the detail view). Examples: "Edit", "Delete", "Retry".
 */
interface InlineActionInterface extends ActionInterface
{
    public function execute(DataRecord $record): ActionResult;
}
