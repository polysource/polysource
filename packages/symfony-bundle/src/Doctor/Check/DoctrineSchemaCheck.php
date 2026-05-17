<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;
use Throwable;

/**
 * Detects pending Polysource schema changes (saved views, column
 * preferences, audit log tables, …). When DDL is pending the host
 * sees a clear "run doctrine:migrations:diff then migrate" message;
 * otherwise the check is silent.
 *
 * Wraps the Doctrine call in a `Throwable` catch because Schema
 * introspection can fail for reasons unrelated to Polysource (broken
 * platform, missing connection, …) — those collapse to WARN with the
 * driver message embedded.
 *
 * @since 0.9.0
 */
final class DoctrineSchemaCheck implements HealthCheckInterface
{
    public function __construct(private readonly ?ManagerRegistry $doctrine = null)
    {
    }

    public function getName(): string
    {
        return 'Doctrine schema';
    }

    public function run(): HealthCheckResult
    {
        if (null === $this->doctrine) {
            return HealthCheckResult::warn(
                'Doctrine not installed — saved views / column prefs / audit / etc. need '
                . 'Doctrine to persist. Skip if your host has its own storage.',
            );
        }

        try {
            $em = $this->doctrine->getManager();
            if (!$em instanceof EntityManagerInterface) {
                return HealthCheckResult::warn(
                    'Default Doctrine manager is not an EntityManager (probably ODM) — schema check skipped.',
                );
            }

            $tool = new SchemaTool($em);
            $allMetadata = $em->getMetadataFactory()->getAllMetadata();
            $polysourceMetadata = array_values(array_filter(
                $allMetadata,
                static fn ($m): bool => str_starts_with($m->getName(), 'Polysource\\'),
            ));

            if ([] === $polysourceMetadata) {
                return HealthCheckResult::pass(
                    'No Polysource Doctrine entity registered on this EntityManager — skip.',
                );
            }

            $sqls = $tool->getUpdateSchemaSql($polysourceMetadata);
            // Filter out non-DDL noise (some DBAL platforms emit comments).
            $significant = array_values(array_filter(
                $sqls,
                static fn (string $sql): bool => '' !== trim($sql),
            ));

            if ([] === $significant) {
                return HealthCheckResult::pass(\sprintf(
                    '%d Polysource entit%s in sync with the database.',
                    \count($polysourceMetadata),
                    1 === \count($polysourceMetadata) ? 'y' : 'ies',
                ));
            }

            return HealthCheckResult::fail(\sprintf(
                '%d pending DDL statement(s) — run `bin/console doctrine:migrations:diff` then `migrations:migrate`. '
                . 'See docs/user/easyadmin-filter-bridge/getting-started.md#2c-database-schema-required-if-doctrine-is-wired.',
                \count($significant),
            ));
        } catch (Throwable $e) {
            return HealthCheckResult::warn(
                \sprintf('Could not check schema: %s', $e->getMessage()),
            );
        }
    }
}
