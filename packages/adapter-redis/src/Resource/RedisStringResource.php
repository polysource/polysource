<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Resource;

use Polysource\Adapter\Redis\DataSource\RedisStringDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base for Redis-backed string resources.
 *
 * Usage — host subclass per Redis string namespace:
 *
 *     #[AsResource]
 *     final class UserSessionResource extends RedisStringResource
 *     {
 *         public function __construct(RedisClientInterface $client)
 *         {
 *             parent::__construct(
 *                 dataSource: new RedisStringDataSource($client, 'PHPREDIS_SESSION:'),
 *                 slug: 'user-sessions',
 *                 label: 'User sessions',
 *                 permission: 'POLYSOURCE_SESSION_VIEW',
 *             );
 *         }
 *     }
 *
 * @since 0.8.0
 */
abstract class RedisStringResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        RedisStringDataSource $dataSource,
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
