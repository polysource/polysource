<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\RecentRecords\Model\RecentRecord;
use Polysource\Filter\RecentRecords\Storage\Doctrine\RecentRecordRecord;

/**
 * Doctrine ORM implementation of {@see RecentRecordsStorageInterface}.
 *
 * @since 0.5.0
 */
final class DoctrineRecentRecordsStorage implements RecentRecordsStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function upsert(RecentRecord $record): void
    {
        $existing = $this->em->find(
            RecentRecordRecord::class,
            [
                'ownerId' => $record->ownerId,
                'resourceName' => $record->resourceName,
                'recordId' => $record->recordId,
            ],
        );

        if (!$existing instanceof RecentRecordRecord) {
            $existing = new RecentRecordRecord();
            $existing->ownerId = $record->ownerId;
            $existing->resourceName = $record->resourceName;
            $existing->recordId = $record->recordId;
            $this->em->persist($existing);
        }
        $existing->viewedAt = $record->viewedAt;
        $existing->label = $record->label;

        $this->em->flush();
    }

    public function recent(string $ownerId, string $resourceName, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RecentRecordRecord::class, 'r')
            ->where('r.ownerId = :owner')
            ->andWhere('r.resourceName = :resource')
            ->orderBy('r.viewedAt', 'DESC')
            ->addOrderBy('r.recordId', 'DESC')
            ->setMaxResults(max(0, $limit))
            ->setParameter('owner', $ownerId)
            ->setParameter('resource', $resourceName)
        ;

        /** @var list<RecentRecordRecord> $records */
        $records = $qb->getQuery()->getResult();

        return array_values(array_map(
            static fn (RecentRecordRecord $r): RecentRecord => new RecentRecord(
                ownerId: $r->ownerId,
                resourceName: $r->resourceName,
                recordId: $r->recordId,
                viewedAt: $r->viewedAt,
                label: $r->label,
            ),
            $records,
        ));
    }
}
