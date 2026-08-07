<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Entity\LoginAttempt;
use App\Polysource\Field\Field;
use App\Polysource\Filter\LoginAttemptFilter;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Adapter\Doctrine\DataSource\DoctrineDataSource;
use Polysource\Adapter\Doctrine\Resource\DoctrineEntityResource;
use Polysource\Bundle\Attribute\AsResource;

/**
 * Read-only ops view of the security audit table.
 *
 * Demonstrates the "Doctrine cohabitation" case from ADR-012:
 * LoginAttempt rows are written by the Symfony firewall and never
 * mutated, so giving them an EasyAdmin CRUD would be misleading.
 * This resource exposes the table to ops via Polysource Admin
 * instead, with filters tuned for security triage (find a brute-
 * force burst by IP, see who got rate-limited, etc.).
 *
 * `configureFields()` yields a curated security-triage view (when /
 * email / ip / status, plus id + user agent on the detail page)
 * built on the showcase's `Field` factory.
 */
#[AsResource]
final class LoginAttemptResource extends DoctrineEntityResource
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct(
            dataSource: new DoctrineDataSource(
                em: $em,
                entityClass: LoginAttempt::class,
                allowedFilters: [
                    'email' => 'email',
                    'ip' => 'ip',
                    'status' => 'status',
                    'occurredAt' => 'occurredAt',
                ],
                allowedSorts: [
                    'occurredAt' => 'occurredAt',
                    'email' => 'email',
                    'ip' => 'ip',
                    'status' => 'status',
                ],
            ),
            slug: 'login-attempts',
            label: 'Login attempts',
            permission: 'POLYSOURCE_LOGIN_ATTEMPTS_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        yield LoginAttemptFilter::email();
        yield LoginAttemptFilter::ip();
        yield LoginAttemptFilter::status();
        yield LoginAttemptFilter::occurredAt();
    }

    public function configureFields(string $page): iterable
    {
        yield Field::new('occurredAt', 'When')->asDateTime();
        yield Field::new('email', 'Email')->asText();
        yield Field::new('ip', 'IP')->asText();
        yield Field::new('status', 'Status')->asText();

        if ($page === 'detail') {
            yield Field::new('id', 'ID')->asId();
            yield Field::new('userAgent', 'User agent')->asText();
        }
    }
}
