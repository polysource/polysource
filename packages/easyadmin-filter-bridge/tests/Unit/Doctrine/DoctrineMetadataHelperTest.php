<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Doctrine\DoctrineMetadataHelper;
use stdClass;

#[CoversClass(DoctrineMetadataHelper::class)]
final class DoctrineMetadataHelperTest extends TestCase
{
    #[Test]
    public function extractsTargetFromDoctrine2xArrayMapping(): void
    {
        // Doctrine ORM 2.x ClassMetadata returns the mapping as an
        // associative array. The helper reads `targetEntity` directly.
        $mapping = [
            'targetEntity' => self::class,
            'fieldName' => 'createdBy',
            'type' => 2,
        ];

        self::assertSame(self::class, DoctrineMetadataHelper::extractTargetEntity($mapping));
    }

    #[Test]
    public function extractsTargetFromDoctrine3xObjectMapping(): void
    {
        // Doctrine ORM 3.x returns AssociationMapping objects with
        // public properties. The helper handles them by reflecting
        // public properties via array cast.
        $mapping = new stdClass();
        $mapping->targetEntity = self::class;
        $mapping->fieldName = 'createdBy';

        self::assertSame(self::class, DoctrineMetadataHelper::extractTargetEntity($mapping));
    }

    #[Test]
    public function returnsNullForUnknownClass(): void
    {
        $mapping = ['targetEntity' => 'App\Entity\NonExistent'];

        self::assertNull(DoctrineMetadataHelper::extractTargetEntity($mapping));
    }

    #[Test]
    public function returnsNullForMissingKey(): void
    {
        $mapping = ['fieldName' => 'foo'];

        self::assertNull(DoctrineMetadataHelper::extractTargetEntity($mapping));
    }

    #[Test]
    public function returnsNullForEmptyString(): void
    {
        $mapping = ['targetEntity' => ''];

        self::assertNull(DoctrineMetadataHelper::extractTargetEntity($mapping));
    }

    #[Test]
    public function returnsNullForGarbageInput(): void
    {
        self::assertNull(DoctrineMetadataHelper::extractTargetEntity(null));
        self::assertNull(DoctrineMetadataHelper::extractTargetEntity('not a mapping'));
        self::assertNull(DoctrineMetadataHelper::extractTargetEntity(42));
    }

    #[Test]
    public function readFieldReturnsArbitraryFieldsFromArrayMapping(): void
    {
        $mapping = [
            'targetEntity' => self::class,
            'mappedBy' => 'inverseSide',
            'joinColumns' => [['name' => 'created_by_id']],
        ];

        self::assertSame('inverseSide', DoctrineMetadataHelper::readField($mapping, 'mappedBy'));
        self::assertSame(
            [['name' => 'created_by_id']],
            DoctrineMetadataHelper::readField($mapping, 'joinColumns'),
        );
    }

    #[Test]
    public function readFieldReturnsArbitraryFieldsFromObjectMapping(): void
    {
        $mapping = new stdClass();
        $mapping->inversedBy = 'authoredBy';

        self::assertSame('authoredBy', DoctrineMetadataHelper::readField($mapping, 'inversedBy'));
    }

    #[Test]
    public function readFieldReturnsNullForMissingFieldAndGarbage(): void
    {
        self::assertNull(DoctrineMetadataHelper::readField(['foo' => 1], 'bar'));
        self::assertNull(DoctrineMetadataHelper::readField(null, 'targetEntity'));
        self::assertNull(DoctrineMetadataHelper::readField('garbage', 'targetEntity'));
    }
}
