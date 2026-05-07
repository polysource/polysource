<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

final class BulkCapTestResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', ['name' => 'a']),
            new DataRecord('2', ['name' => 'b']),
        ]));
    }

    public function getName(): string
    {
        return 'bulk-cap';
    }

    public function getLabel(): string
    {
        return 'Bulk cap';
    }

    public function configureActions(): iterable
    {
        yield new NoopBulkAction();
    }
}
