<?php

declare(strict_types=1);

namespace App\Story;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\BulkActionHistory\Storage\Doctrine\BulkActionHistoryRecord;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Seeds 40 entries into `polysource_bulk_action_history` covering
 * the past 14 days across the 4 ShopCo resources + a realistic
 * mix of actors. Demonstrates the v0.5.0 #8 audit log populated
 * for the BulkActionHistoryCrudController showcase page.
 *
 * Writes directly via Doctrine — same approach as
 * AuditEntriesStory: the polysource record is host-internal
 * storage, no Foundry factory.
 *
 * @since 0.5.2 (showcase wiring)
 */
final class BulkActionHistoryStory extends Story
{
    /**
     * @var array<string, list<array{name: string, range: array{int, int}}>>
     */
    private const ACTIONS_BY_RESOURCE = [
        'orders' => [
            ['name' => 'mark_cancelled', 'range' => [1, 12]],
            ['name' => 'mark_paid', 'range' => [3, 25]],
            ['name' => 'export', 'range' => [10, 200]],
        ],
        'products' => [
            ['name' => 'mark_active', 'range' => [1, 50]],
            ['name' => 'mark_inactive', 'range' => [1, 30]],
            ['name' => 'bulk_price_update', 'range' => [5, 80]],
        ],
        'customers' => [
            ['name' => 'send_newsletter', 'range' => [10, 500]],
            ['name' => 'export_gdpr', 'range' => [1, 5]],
        ],
        'refunds' => [
            ['name' => 'mark_approved', 'range' => [1, 8]],
            ['name' => 'mark_rejected', 'range' => [1, 6]],
        ],
    ];

    private const ACTORS = [
        'admin@shop.co',
        'ops@shop.co',
        'ops-jane@shop.co',
        'ops-marcus@shop.co',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function build(): void
    {
        $now = new DateTimeImmutable();
        $resources = array_keys(self::ACTIONS_BY_RESOURCE);

        for ($i = 0; $i < 40; ++$i) {
            $resource = $resources[$i % \count($resources)];
            $actions = self::ACTIONS_BY_RESOURCE[$resource];
            $action = $actions[$i % \count($actions)];
            $owner = self::ACTORS[$i % \count(self::ACTORS)];

            // Spread occurredAt over the past 14 days, more recent
            // entries cluster towards the top (the list is sorted by
            // occurredAt DESC, so we want today/yesterday to dominate).
            $daysBack = (int) floor($i / 6); // 0..6
            $hoursBack = $i * 7 % 24;
            $minutesBack = $i * 11 % 60;
            $occurredAt = $now->modify(\sprintf('-%d days -%d hours -%d minutes', $daysBack, $hoursBack, $minutesBack));

            $count = random_int($action['range'][0], $action['range'][1]);

            $record = new BulkActionHistoryRecord();
            $record->id = Uuid::v7()->toRfc4122();
            $record->ownerId = $owner;
            $record->resourceName = $resource;
            $record->actionName = $action['name'];
            $record->affectedCount = $count;
            $record->occurredAt = $occurredAt;
            $record->metadataJson = json_encode([
                'scope' => 0 === $i % 3 ? 'all_matching' : 'selected',
                'filterSlice' => 0 === $i % 4 ? ['status' => 'paid'] : [],
            ], \JSON_THROW_ON_ERROR);

            $this->em->persist($record);
        }

        $this->em->flush();
    }
}
