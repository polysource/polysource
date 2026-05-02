<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Functional-test resource. Wraps an in-memory data source so the smoke
 * tests can exercise routes, controllers, the resolver and the view
 * listener without depending on an adapter package.
 */
final class TestResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'flag-a', 'enabled' => true]),
            new DataRecord('2', ['name' => 'flag-b', 'enabled' => false]),
        ]));
    }

    public function getName(): string
    {
        return 'flags';
    }

    public function getLabel(): string
    {
        return 'Feature flags';
    }
}
