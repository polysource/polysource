<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Resource;

use Polysource\Adapter\Http\DataSource\HttpDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

abstract class HttpResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        HttpDataSource $dataSource,
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
