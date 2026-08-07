<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\RowDetail;

/**
 * Convenience base for the 80% case: a template rendered with the
 * entity (plus optional extra context). Subclasses implement
 * {@see getSupportedEntity()} + {@see template()}; override
 * {@see context()} to feed the template more than the entity and
 * {@see getPermission()} to gate the detail per record.
 *
 * @since 1.1.0
 */
abstract class AbstractRowDetailProvider implements RowDetailProviderInterface
{
    public function getPermission(): ?string
    {
        return null;
    }

    public function getRowDetail(object $entity): RowDetail
    {
        return RowDetail::template($this->template(), $this->context($entity));
    }

    abstract protected function template(): string;

    /**
     * @return array<string, mixed>
     */
    protected function context(object $entity): array
    {
        return [];
    }
}
