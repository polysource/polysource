<?php

declare(strict_types=1);

namespace Polysource\Adapter\Flysystem\Resource;

use Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

abstract class FlysystemResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        FlysystemDataSource $dataSource,
        private readonly string $slug,
        private readonly string $label,
        private readonly string $identifierProperty = 'path',
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
