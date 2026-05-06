<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LoginAttempt;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<LoginAttempt>
 */
final class LoginAttemptFactory extends PersistentProxyObjectFactory
{
    /** Heavy bias on bad_credentials (real-world skew). */
    private const STATUS_DISTRIBUTION = [
        LoginAttempt::STATUS_SUCCESS,
        LoginAttempt::STATUS_SUCCESS,
        LoginAttempt::STATUS_SUCCESS,
        LoginAttempt::STATUS_SUCCESS,
        LoginAttempt::STATUS_BAD_CREDENTIALS,
        LoginAttempt::STATUS_BAD_CREDENTIALS,
        LoginAttempt::STATUS_BAD_CREDENTIALS,
        LoginAttempt::STATUS_USER_NOT_FOUND,
        LoginAttempt::STATUS_USER_NOT_FOUND,
        LoginAttempt::STATUS_RATE_LIMITED,
        LoginAttempt::STATUS_BLOCKED,
    ];

    public static function class(): string
    {
        return LoginAttempt::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $occurredAt = \DateTimeImmutable::createFromMutable(
            self::faker()->dateTimeBetween('-30 days', 'now')
        );

        return [
            'email' => self::faker()->safeEmail(),
            'ip' => self::faker()->ipv4(),
            'userAgent' => self::faker()->userAgent(),
            'status' => self::faker()->randomElement(self::STATUS_DISTRIBUTION),
            'occurredAt' => $occurredAt,
        ];
    }
}
