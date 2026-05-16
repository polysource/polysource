<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Resource;

use Polysource\Adapter\Redis\DataSource\RedisListDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base for Redis-backed list resources.
 *
 * Usage — host subclass per workflow / queue / stream namespace:
 *
 *     #[AsResource]
 *     final class WorkflowQueueResource extends RedisListResource
 *     {
 *         public function __construct(RedisClientInterface $client)
 *         {
 *             parent::__construct(
 *                 dataSource: new RedisListDataSource($client, 'queue:'),
 *                 slug: 'workflow-queues',
 *                 label: 'Workflow queues',
 *                 permission: 'POLYSOURCE_QUEUE_VIEW',
 *             );
 *         }
 *     }
 *
 * @since 0.8.0
 */
abstract class RedisListResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        RedisListDataSource $dataSource,
        private readonly string $slug,
        private readonly string $label,
        private readonly string $identifierProperty = 'id',
        private readonly ?string $permission = null,
        private readonly iterable $actions = [],
    ) {
        parent::__construct($dataSource);
    }

    public function getName(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIdentifierProperty(): string
    {
        return $this->identifierProperty;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function configureFields(string $page): iterable
    {
        unset($page);

        return [];
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }
}
