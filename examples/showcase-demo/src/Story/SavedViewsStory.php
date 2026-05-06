<?php

declare(strict_types=1);

namespace App\Story;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Symfony\Component\Uid\Uuid;
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
        $this->save(
            id: 'sv-late-deliveries',
            name: 'Late deliveries',
            resource: 'orders',
            filters: [
                'resource' => 'orders',
                'criteria' => [
                    ['property' => 'status', 'operator' => 'in', 'values' => ['paid', 'preparing']],
                ],
            ],
        );

        $this->save(
            id: 'sv-high-value-pending-refunds',
            name: 'High-value pending refunds',
            resource: 'refunds',
            filters: [
                'resource' => 'refunds',
                'criteria' => [
                    ['property' => 'status', 'operator' => 'eq', 'values' => ['pending']],
                    ['property' => 'amountCents', 'operator' => 'gte', 'values' => ['5000']],
                ],
            ],
        );

        $this->save(
            id: 'sv-low-stock-active-products',
            name: 'Low-stock active products',
            resource: 'products',
            filters: [
                'resource' => 'products',
                'criteria' => [
                    ['property' => 'status', 'operator' => 'eq', 'values' => ['active']],
                    ['property' => 'stock', 'operator' => 'lt', 'values' => ['10']],
                ],
            ],
        );
    }

    /**
     * @param array{resource: string, criteria: list<array{property: string, operator: string, values: list<string>}>} $filters
     */
    private function save(string $id, string $name, string $resource, array $filters): void
    {
        $record = new SavedViewRecord();
        $record->id = $id;
        $record->name = $name;
        $record->resourceName = $resource;
        $record->ownerId = 'admin@shop.co';
        $record->scope = 'public';
        $record->filtersJson = json_encode($filters, \JSON_THROW_ON_ERROR);
        $record->columnsJson = json_encode([], \JSON_THROW_ON_ERROR);
        $record->sortJson = json_encode([], \JSON_THROW_ON_ERROR);
        $record->isDefault = false;

        $this->em->persist($record);
        $this->em->flush();
    }
}
