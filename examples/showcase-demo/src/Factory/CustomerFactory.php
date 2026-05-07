<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Customer;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Customer>
 */
final class CustomerFactory extends PersistentProxyObjectFactory
{
    private const COUNTRIES = ['FR', 'DE', 'ES', 'IT', 'BE', 'NL', 'PT', 'GB'];

    public static function class(): string
    {
        return Customer::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $createdAt = DateTimeImmutable::createFromMutable(
            self::faker()->dateTimeBetween('-2 years', 'now')
        );

        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'phone' => self::faker()->boolean(80) ? self::faker()->phoneNumber() : null,
            'addressLine' => self::faker()->streetAddress(),
            'city' => self::faker()->city(),
            'postalCode' => self::faker()->postcode(),
            'country' => self::faker()->randomElement(self::COUNTRIES),
            'createdAt' => $createdAt,
        ];
    }
}
