<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Resource;

use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base for Redis-backed hash resources.
 *
 * Usage — host subclass for each Redis namespace they want to admin:
 *
 *     #[AsResource]
 *     final class FeatureFlagResource extends RedisHashResource
 *     {
 *         public function __construct(RedisHashClientInterface $client)
 *         {
 *             parent::__construct(
 *                 dataSource: new RedisHashDataSource($client, 'polysource:flag:'),
 *                 slug: 'feature-flags',
 *                 label: 'Feature flags',
 *                 permission: 'POLYSOURCE_FEATURE_FLAG_VIEW',
 *             );
 *         }
 *     }
 *
 * Not `final` so hosts can override `configureFields()` /
 * `configureActions()` per their domain needs without forking.
 */
abstract class RedisHashResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        RedisHashDataSource $dataSource,
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
