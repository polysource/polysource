<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Embedded resource guarded by `RECORD_OWNER`, which the
 * {@see RecordSubjectPermission} fixture only grants on owned
 * DataRecords — a Resource subject is always denied, so embedding
 * this listing must 403 the panel.
 */
final class SecretItemsResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('s1', ['label' => 'classified']),
        ]));
    }

    public function getName(): string
    {
        return 'secret-items';
    }

    public function getLabel(): string
    {
        return 'Secret items';
    }

    public function getPermission(): string
    {
        return 'RECORD_OWNER';
    }
}
