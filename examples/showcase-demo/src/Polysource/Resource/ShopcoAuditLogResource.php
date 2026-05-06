<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use Polysource\Audit\Resource\AuditLogResource;

/**
 * Showcase override of polysource/audit's AuditLogResource — adds
 * concrete fields so the audit log index renders 8 columns instead
 * of empty rows. Per ADR-011 the upstream package returns `[]` from
 * `configureFields()` until v0.2 ships concrete field types.
 *
 * DataRecord properties:
 *   id, occurredAt, actorId, actorLabel, resourceName, actionName,
 *   outcome, message, durationMs, recordIds, context
 */
final class ShopcoAuditLogResource extends AuditLogResource
{
    public function configureFields(string $page): iterable
    {
        yield Field::new('occurredAt', 'When')->asDateTime();
        yield Field::new('actorLabel', 'Actor')->asText();
        yield Field::new('resourceName', 'Resource')->asText();
        yield Field::new('actionName', 'Action')->asText();
        yield Field::new('outcome', 'Outcome')->asText();
        yield Field::new('durationMs', 'Duration (ms)')->asText();

        if ($page === 'detail') {
            yield Field::new('id', 'ID')->asId();
            yield Field::new('actorId', 'Actor ID')->asText();
            yield Field::new('message', 'Message')->asText();
            yield Field::new('recordIds', 'Records')->asCode();
            yield Field::new('context', 'Context')->asCode();
        }
    }
}
