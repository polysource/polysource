<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use stdClass;

/**
 * Two records with opposite gating outcomes:
 *   - record 1: status `open`, owner `me`   → every action visible
 *   - record 2: status `done`, owner `other` → status-gated hidden
 *     by isDisplayed(), owner-gated denied by the permission voter
 *
 * Both carry an object rawSource so {@see SubjectRequiringAction}
 * (the workflow-transition stand-in) is visible on BOTH rows.
 */
final class RecordGatedTestResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'first', 'status' => 'open', 'owner' => 'me'], new stdClass()),
            new DataRecord('2', ['name' => 'second', 'status' => 'done', 'owner' => 'other'], new stdClass()),
        ]));
    }

    public function getName(): string
    {
        return 'record-gated';
    }

    public function getLabel(): string
    {
        return 'Record gated';
    }

    public function configureActions(): iterable
    {
        yield new StatusGatedAction();
        yield new OwnerGatedAction();
        yield new SubjectRequiringAction();
    }
}
