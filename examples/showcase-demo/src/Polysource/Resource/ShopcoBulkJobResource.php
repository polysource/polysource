<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use Polysource\BulkAsync\Resource\BulkJobResource;

/**
 * Showcase override of polysource/bulk-async's BulkJobResource —
 * adds concrete fields so the bulk-jobs index shows status / progress
 * / counts instead of empty rows. Per ADR-011 the upstream package
 * returns `[]` from `configureFields()` until v0.2.
 *
 * DataRecord properties:
 *   id, createdAt, startedAt, completedAt, resourceName, actionName,
 *   actorId, status, processedCount, failedCount, total, progress,
 *   errorMessage
 */
final class ShopcoBulkJobResource extends BulkJobResource
{
    public function configureFields(string $page): iterable
    {
        yield Field::new('createdAt', 'Created')->asDateTime();
        yield Field::new('resourceName', 'Resource')->asText();
        yield Field::new('actionName', 'Action')->asText();
        yield Field::new('status', 'Status')->asText();
        yield Field::new('progress', 'Progress')->asText();
        yield Field::new('failedCount', 'Failed')->asText();
        yield Field::new('actorId', 'Actor')->asText();

        if ($page === 'detail') {
            yield Field::new('id', 'ID')->asId();
            yield Field::new('startedAt', 'Started')->asDateTime();
            yield Field::new('completedAt', 'Completed')->asDateTime();
            yield Field::new('processedCount', 'Processed')->asText();
            yield Field::new('total', 'Total')->asText();
            yield Field::new('errorMessage', 'Error')->asText();
        }
    }
}
