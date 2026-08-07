<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\RowDetail\HasRowDetailsInterface;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use Polysource\Core\RowDetail\RowDetail;

/**
 * Native row-details fixture:
 *   - record 1 (status open, owner me)    → detail available
 *   - record 2 (status done, owner me)    → hasRowDetail() false → no chevron, panel 404
 *   - record 3 (status open, owner other) → permission denied per record
 *
 * Kernel wires {@see RecordSubjectPermission}, which grants
 * `RECORD_OWNER` only when the DataRecord subject's `owner` is `me`.
 */
final class RowDetailTestResource extends AbstractResource implements HasRowDetailsInterface
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'first', 'status' => 'open', 'owner' => 'me']),
            new DataRecord('2', ['name' => 'second', 'status' => 'done', 'owner' => 'me']),
            new DataRecord('3', ['name' => 'third', 'status' => 'open', 'owner' => 'other']),
        ]));
    }

    public function getName(): string
    {
        return 'row-detailed';
    }

    public function getLabel(): string
    {
        return 'Row detailed';
    }

    public function hasRowDetail(DataRecord $record): bool
    {
        return 'done' !== $record->get('status');
    }

    public function getRowDetail(DataRecord $record): ?RowDetail
    {
        if (!$this->hasRowDetail($record)) {
            return null;
        }

        $name = $record->get('name');

        return RowDetail::template('row_detail/native_detail.html.twig', [
            'shouted_name' => strtoupper(\is_string($name) ? $name : ''),
        ]);
    }

    public function getRowDetailPermission(): string
    {
        return 'RECORD_OWNER';
    }
}
