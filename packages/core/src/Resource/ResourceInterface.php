<?php

declare(strict_types=1);

namespace Polysource\Core\Resource;

use Polysource\Core\Action\ActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Filter\FilterInterface;

/**
 * A Polysource Resource — declares an admin-managed entity backed by a
 * {@see DataSourceInterface}.
 *
 * Use {@see AbstractResource} as a base class to avoid boilerplate.
 *
 * Cf. ADR-005 — Resources can be discovered via the `#[AsResource]` attribute
 * (when the Symfony bundle is installed) or registered manually with the
 * `polysource.resource` DI tag.
 */
interface ResourceInterface
{
    /**
     * URL slug — kebab-case recommended (e.g. "failed-messages", "feature-flags").
     */
    public function getName(): string;

    public function getLabel(): string;

    /**
     * Property name carrying the unique identifier in the {@see \Polysource\Core\Query\DataRecord}.
     * Used to build detail URLs and to call DataSource::find().
     */
    public function getIdentifierProperty(): string;

    public function getDataSource(): DataSourceInterface;

    /**
     * Configure fields displayed for the given page ('index'|'detail'|'edit'|'new').
     *
     * @return iterable<FieldInterface>
     */
    public function configureFields(string $page): iterable;

    /**
     * Configure inline / bulk / global actions.
     *
     * @return iterable<ActionInterface>
     */
    public function configureActions(): iterable;

    /**
     * Configure filters available on the index page.
     *
     * @return iterable<FilterInterface>
     */
    public function configureFilters(): iterable;

    /**
     * Permission attribute checked before any access to this resource.
     * Returns null if no resource-level permission applies (per-action checks
     * still happen).
     */
    public function getPermission(): ?string;
}
