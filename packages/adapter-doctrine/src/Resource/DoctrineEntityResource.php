<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Resource;

use Polysource\Adapter\Doctrine\DataSource\DoctrineDataSource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\AbstractResource;

/**
 * Convenience base class for Doctrine entity resources.
 *
 * Usage — host subclass for each entity they want to admin:
 *
 *     #[AsResource]
 *     final class ProductResource extends DoctrineEntityResource
 *     {
 *         public function __construct(EntityManagerInterface $em)
 *         {
 *             parent::__construct(
 *                 dataSource: new DoctrineDataSource($em, Product::class, allowedFilters: [
 *                     'name'      => 'name',
 *                     'createdAt' => 'createdAt',
 *                 ]),
 *                 slug: 'products',
 *                 label: 'Products',
 *             );
 *         }
 *     }
 *
 * Not `final` so hosts can override `configureFields()` /
 * `configureActions()` / `configureFilters()` per their domain
 * needs without forking the package.
 *
 * Cf. ADR-012 — this is the Doctrine cohabitation case: hosts run
 * EasyAdmin on some entities and Polysource on others (e.g.
 * messenger failed messages + Doctrine product catalogue) inside
 * the same admin panel.
 */
abstract class DoctrineEntityResource extends AbstractResource
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        DoctrineDataSource $dataSource,
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
