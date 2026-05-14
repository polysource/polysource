<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\ColumnPreference\Model\ColumnPreference;
use Polysource\Filter\ColumnPreference\Storage\Doctrine\ColumnPreferenceRecord;

/**
 * Doctrine-backed implementation of {@see ColumnPreferenceStorageInterface}.
 *
 * Same VO↔record conversion pattern as
 * {@see \Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage}.
 */
final class DoctrineColumnPreferenceStorage implements ColumnPreferenceStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(string $ownerId, string $resourceName): ?ColumnPreference
    {
        $record = $this->em
            ->getRepository(ColumnPreferenceRecord::class)
            ->find(['ownerId' => $ownerId, 'resourceName' => $resourceName])
        ;

        if (!$record instanceof ColumnPreferenceRecord) {
            return null;
        }

        return self::fromRecord($record);
    }

    public function save(ColumnPreference $preference): void
    {
        $record = $this->em
            ->getRepository(ColumnPreferenceRecord::class)
            ->find(['ownerId' => $preference->ownerId, 'resourceName' => $preference->resourceName])
        ;

        if (!$record instanceof ColumnPreferenceRecord) {
            $record = new ColumnPreferenceRecord();
            $record->ownerId = $preference->ownerId;
            $record->resourceName = $preference->resourceName;
            $this->em->persist($record);
        }

        $record->hiddenColumnsJson = json_encode(
            $preference->hiddenColumns,
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );

        $this->em->flush();
    }

    public function delete(string $ownerId, string $resourceName): void
    {
        $record = $this->em
            ->getRepository(ColumnPreferenceRecord::class)
            ->find(['ownerId' => $ownerId, 'resourceName' => $resourceName])
        ;

        if ($record instanceof ColumnPreferenceRecord) {
            $this->em->remove($record);
            $this->em->flush();
        }
    }

    private static function fromRecord(ColumnPreferenceRecord $record): ColumnPreference
    {
        /** @var list<string> $hidden */
        $hidden = json_decode($record->hiddenColumnsJson, true, flags: \JSON_THROW_ON_ERROR);

        return new ColumnPreference(
            ownerId: $record->ownerId,
            resourceName: $record->resourceName,
            hiddenColumns: $hidden,
        );
    }
}
