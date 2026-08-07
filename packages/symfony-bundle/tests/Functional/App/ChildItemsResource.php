<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Child listing for the nested-row-detail tests: three items under
 * parent 1 (so pageSize=2 forces a second page), one under parent 2.
 */
final class ChildItemsResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new FilterableInMemoryDataSource([
            new DataRecord('c1', ['label' => 'child-one', 'parentId' => '1']),
            new DataRecord('c2', ['label' => 'child-two', 'parentId' => '1']),
            new DataRecord('c3', ['label' => 'child-three', 'parentId' => '1']),
            new DataRecord('c4', ['label' => 'child-four', 'parentId' => '2']),
        ]));
    }

    public function getName(): string
    {
        return 'child-items';
    }

    public function getLabel(): string
    {
        return 'Child items';
    }
}
