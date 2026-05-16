<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Resource;

use Polysource\Adapter\Redis\DataSource\RedisSetDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base for Redis-backed set resources.
 *
 * Usage — host subclass per unique-tracking namespace:
 *
 *     #[AsResource]
 *     final class OnlineUserResource extends RedisSetResource
 *     {
 *         public function __construct(RedisClientInterface $client)
 *         {
 *             parent::__construct(
 *                 dataSource: new RedisSetDataSource($client, 'online:'),
 *                 slug: 'online-users',
 *                 label: 'Online users',
 *                 permission: 'POLYSOURCE_ONLINE_VIEW',
 *             );
 *         }
 *     }
 *
 * @since 0.8.0
 */
abstract class RedisSetResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        RedisSetDataSource $dataSource,
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
