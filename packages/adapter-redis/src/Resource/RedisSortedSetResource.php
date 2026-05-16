<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Resource;

use Polysource\Adapter\Redis\DataSource\RedisSortedSetDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base for Redis-backed sorted-set resources.
 *
 * Usage — host subclass per leaderboard / time-series / priority queue:
 *
 *     #[AsResource]
 *     final class LeaderboardResource extends RedisSortedSetResource
 *     {
 *         public function __construct(RedisClientInterface $client)
 *         {
 *             parent::__construct(
 *                 dataSource: new RedisSortedSetDataSource($client, 'leaderboard:'),
 *                 slug: 'leaderboards',
 *                 label: 'Leaderboards',
 *                 permission: 'POLYSOURCE_LEADERBOARD_VIEW',
 *             );
 *         }
 *     }
 *
 * @since 0.8.0
 */
abstract class RedisSortedSetResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        RedisSortedSetDataSource $dataSource,
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
