<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Refund;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Refund>
 */
final class RefundFactory extends PersistentProxyObjectFactory
{
    private const REASONS = [
        Refund::REASON_DEFECTIVE,
        Refund::REASON_NOT_AS_DESCRIBED,
        Refund::REASON_LATE_DELIVERY,
        Refund::REASON_CHANGED_MIND,
        Refund::REASON_OTHER,
    ];

    private const STATUSES = [
        Refund::STATUS_PENDING,
        Refund::STATUS_PROCESSED,
        Refund::STATUS_PROCESSED,
        Refund::STATUS_REJECTED,
    ];

    public static function class(): string
    {
        return Refund::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $createdAt = \DateTimeImmutable::createFromMutable(
            self::faker()->dateTimeBetween('-9 months', 'now')
        );

        $status = self::faker()->randomElement(self::STATUSES);

        return [
            'amountCents' => self::faker()->numberBetween(500, 30000),
            'reason' => self::faker()->randomElement(self::REASONS),
            'status' => $status,
            'note' => self::faker()->boolean(60) ? self::faker()->sentence() : null,
            'createdAt' => $createdAt,
            'processedAt' => $status !== Refund::STATUS_PENDING
                ? $createdAt->modify('+'.self::faker()->numberBetween(1, 10).' days')
                : null,
        ];
    }
}
