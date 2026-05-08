<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Refund;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Zenstruck\Foundry\Story;

/**
 * Three demo saved views shipped with the showcase, all public-scope so
 * every role can pick them from the dropdown without owning them:
 *
 *   1. "Late deliveries"          (Order: status in [paid, preparing])
 *   2. "High-value pending refunds" (Refund: status=pending, amount>5000c)
 *   3. "Low-stock active products"  (Product: stock<10, status=active)
 *
 * filtersJson encodes the EA-bridge query-string shape so the
 * dropdown rewrites the URL with `?filter[...]=...` and the
 * existing FilterConfigurator chain handles the rest.
 */
final class SavedViewsStory extends Story
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function build(): void
    {
        // - resourceName MUST equal the entity FQCN — the EA bridge calls
        //   saved_views_dropdown(ea.crud.entityFqcn) so the dropdown
        //   filters by FQCN, not by slug.
        // - filtersJson MUST be a flat list of {property,operator,values}
        //   — DoctrineSavedViewStorage::deserializeFilters() rejects
        //   any wrapper key.
        $this->save(
            id: 'sv-late-deliveries',
            name: 'Late deliveries',
            resource: Order::class,
            criteria: [
                ['property' => 'status', 'operator' => 'in', 'values' => ['paid', 'preparing']],
            ],
        );

        $this->save(
            id: 'sv-high-value-pending-refunds',
            name: 'High-value pending refunds',
            resource: Refund::class,
            criteria: [
                ['property' => 'status', 'operator' => 'eq', 'values' => ['pending']],
                ['property' => 'amountCents', 'operator' => 'gte', 'values' => ['5000']],
            ],
        );

        $this->save(
            id: 'sv-low-stock-active-products',
            name: 'Low-stock active products',
            resource: Product::class,
            criteria: [
                ['property' => 'status', 'operator' => 'eq', 'values' => ['active']],
                ['property' => 'stock', 'operator' => 'lt', 'values' => ['10']],
            ],
        );

        // Private (admin-only) view on the audit-log Polysource resource.
        // Kept private on purpose so the saved-views scope visibility
        // E2E test has a fixture that proves admin sees it AND ops
        // doesn't (the whole point of `private` scope per ADR-019).
        // resourceName is the Polysource resource slug (`audit-log`),
        // NOT the entity FQCN — Polysource standalone resources route
        // by slug, not by FQCN.
        $this->save(
            id: 'sv-audit-admin',
            name: 'Admin actions',
            resource: 'audit-log',
            criteria: [
                ['property' => 'actorId', 'operator' => 'eq', 'values' => ['admin@shop.co']],
            ],
            scope: 'private',
        );
    }

    /**
     * @param list<array{property: string, operator: string, values: list<string>}> $criteria
     */
    private function save(string $id, string $name, string $resource, array $criteria, string $scope = 'public'): void
    {
        $record = new SavedViewRecord();
        $record->id = $id;
        $record->name = $name;
        $record->resourceName = $resource;
        $record->ownerId = 'admin@shop.co';
        $record->scope = $scope;
        $record->filtersJson = json_encode($criteria, \JSON_THROW_ON_ERROR);
        $record->columnsJson = json_encode([], \JSON_THROW_ON_ERROR);
        $record->sortJson = json_encode([], \JSON_THROW_ON_ERROR);
        $record->isDefault = false;

        $this->em->persist($record);
        $this->em->flush();
    }
}
