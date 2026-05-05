<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Filter\BulkJobFilter;
use Polysource\BulkAsync\Job\BulkJobStatus;

final class BulkJobFilterTest extends TestCase
{
    public function testFactoriesProduceDistinctProperties(): void
    {
        self::assertSame('actorId', BulkJobFilter::actorId()->getProperty());
        self::assertSame('status', BulkJobFilter::status()->getProperty());
        self::assertSame('createdAt', BulkJobFilter::createdAt()->getProperty());
        self::assertSame('resourceName', BulkJobFilter::resourceName()->getProperty());
    }

    public function testStatusFilterAdvertisesAllEnumValues(): void
    {
        $dto = BulkJobFilter::status()->getAsDto();
        $expected = array_map(static fn (BulkJobStatus $s): string => $s->value, BulkJobStatus::cases());

        self::assertSame(['in'], $dto->supportedOperators);
        self::assertSame($expected, $dto->customOptions['choices'] ?? null);
    }

    public function testCreatedAtSupportsRangeOperators(): void
    {
        self::assertSame(['between', 'gte', 'lte'], BulkJobFilter::createdAt()->getSupportedOperators());
    }
}
