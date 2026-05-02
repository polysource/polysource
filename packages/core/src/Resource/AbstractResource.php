<?php

declare(strict_types=1);

namespace Polysource\Core\Resource;

use Polysource\Core\Action\ActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Filter\FilterInterface;

/**
 * Convenience base class for {@see ResourceInterface}.
 *
 * Provides sensible defaults so the user only needs to override what differs
 * for their resource (typically: configureFields, getDataSource, getName).
 */
abstract class AbstractResource implements ResourceInterface
{
    public function __construct(
        protected readonly DataSourceInterface $dataSource,
    ) {
    }

    public function getIdentifierProperty(): string
    {
        return 'id';
    }

    public function getDataSource(): DataSourceInterface
    {
        return $this->dataSource;
    }

    /**
     * @return iterable<FieldInterface>
     */
    public function configureFields(string $page): iterable
    {
        return [];
    }

    /**
     * @return iterable<ActionInterface>
     */
    public function configureActions(): iterable
    {
        return [];
    }

    /**
     * @return iterable<FilterInterface>
     */
    public function configureFilters(): iterable
    {
        return [];
    }

    public function getPermission(): ?string
    {
        return null;
    }
}
