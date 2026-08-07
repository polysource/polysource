<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\RowDetail\HasRowDetailsInterface;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use Polysource\Core\RowDetail\RowDetail;

/**
 * Nested-listing fixture: each parent row expands into the
 * `child-items` listing scoped by `parentId`, except record 9 whose
 * detail targets the permission-gated `secret-items` resource.
 */
final class ParentOrdersResource extends AbstractResource implements HasRowDetailsInterface
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'alpha']),
            new DataRecord('2', ['name' => 'beta']),
            new DataRecord('9', ['name' => 'gamma']),
        ]));
    }

    public function getName(): string
    {
        return 'parent-orders';
    }

    public function getLabel(): string
    {
        return 'Parent orders';
    }

    public function hasRowDetail(DataRecord $record): bool
    {
        return true;
    }

    public function getRowDetail(DataRecord $record): RowDetail
    {
        if ('9' === (string) $record->identifier) {
            return RowDetail::listing('secret-items');
        }

        return RowDetail::listing('child-items', ['parentId' => $record->identifier], pageSize: 2);
    }

    public function getRowDetailPermission(): ?string
    {
        return null;
    }
}
