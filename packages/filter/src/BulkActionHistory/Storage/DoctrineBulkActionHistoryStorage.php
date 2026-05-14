<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;
use Polysource\Filter\BulkActionHistory\Storage\Doctrine\BulkActionHistoryRecord;

/**
 * Doctrine ORM implementation of {@see BulkActionHistoryStorageInterface}.
 *
 * @since 0.5.0
 */
final class DoctrineBulkActionHistoryStorage implements BulkActionHistoryStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function append(BulkActionEntry $entry): void
    {
        $record = new BulkActionHistoryRecord();
        $record->id = $entry->id;
        $record->ownerId = $entry->ownerId;
        $record->resourceName = $entry->resourceName;
        $record->actionName = $entry->actionName;
        $record->affectedCount = $entry->affectedCount;
        $record->occurredAt = $entry->occurredAt;
        $record->metadataJson = [] === $entry->metadata
            ? null
            : json_encode($entry->metadata, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        $this->em->persist($record);
        $this->em->flush();
    }

    public function recent(?string $resourceName, ?string $ownerId, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(BulkActionHistoryRecord::class, 'e')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(max(0, $limit))
        ;

        if (null !== $resourceName) {
            $qb->andWhere('e.resourceName = :resource')->setParameter('resource', $resourceName);
        }
        if (null !== $ownerId) {
            $qb->andWhere('e.ownerId = :owner')->setParameter('owner', $ownerId);
        }

        /** @var list<BulkActionHistoryRecord> $records */
        $records = $qb->getQuery()->getResult();

        return array_values(array_map(
            static fn (BulkActionHistoryRecord $r): BulkActionEntry => new BulkActionEntry(
                id: $r->id,
                ownerId: $r->ownerId,
                resourceName: $r->resourceName,
                actionName: $r->actionName,
                affectedCount: $r->affectedCount,
                occurredAt: $r->occurredAt,
                metadata: self::decodeMetadata($r->metadataJson),
            ),
            $records,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeMetadata(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }

        $raw = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (\is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
