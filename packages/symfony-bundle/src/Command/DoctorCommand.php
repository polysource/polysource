<?php

declare(strict_types=1);

namespace Polysource\Bundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Polysource\Bundle\Plugin\PluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

/**
 * Install-time + runtime diagnostics for Polysource.
 *
 * Runs a set of checks that mirror the frictions surfaced during
 * dogfooding sessions (v0.5.7 round 3 in particular). Each check
 * has one of three statuses:
 *
 *   PASS — the check is healthy
 *   WARN — non-fatal gap (e.g. host has no Doctrine, so saved-views
 *          features won't work — but the bundle is OK to keep installed)
 *   FAIL — a fix is needed before the host can use Polysource safely
 *
 * Exit code: 0 if no FAIL (any WARN is non-blocking), 1 if any FAIL.
 *
 * Usage: `bin/console polysource:doctor`
 *
 * Closes #29.
 *
 * @since 0.6.0
 */
#[AsCommand(
    name: 'polysource:doctor',
    description: 'Diagnose install-time + runtime health of Polysource on this kernel.',
)]
final class DoctorCommand extends Command
{
    private const STATUS_PASS = 'PASS';
    private const STATUS_WARN = 'WARN';
    private const STATUS_FAIL = 'FAIL';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?PluginRegistry $plugins = null,
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Polysource Doctor');

        $results = [
            $this->checkPhpVersion(),
            $this->checkBundles(),
            $this->checkEasyAdminCoLoaded(),
            $this->checkPlugins(),
            $this->checkDoctrineSchema(),
        ];

        $rows = [];
        $failCount = 0;
        $warnCount = 0;
        foreach ($results as [$check, $status, $message]) {
            $rows[] = [$check, $this->renderStatus($status), $message];
            if (self::STATUS_FAIL === $status) {
                ++$failCount;
            } elseif (self::STATUS_WARN === $status) {
                ++$warnCount;
            }
        }

        $io->table(['Check', 'Status', 'Detail'], $rows);

        if ($failCount > 0) {
            $io->error(\sprintf(
                '%d check(s) failed%s. See details above for remediation.',
                $failCount,
                $warnCount > 0 ? \sprintf(' (and %d warning(s))', $warnCount) : '',
            ));

            return Command::FAILURE;
        }

        if ($warnCount > 0) {
            $io->warning(\sprintf('%d warning(s). See details above.', $warnCount));
        } else {
            $io->success('All checks passed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{string, string, string}
     */
    private function checkPhpVersion(): array
    {
        $current = \PHP_VERSION;
        $required = '8.1.0';

        if (version_compare($current, $required, '>=')) {
            return ['PHP version', self::STATUS_PASS, \sprintf('%s (>= %s)', $current, $required)];
        }

        return [
            'PHP version',
            self::STATUS_FAIL,
            \sprintf('%s — Polysource requires >= %s', $current, $required),
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function checkBundles(): array
    {
        $loaded = array_keys($this->kernel->getBundles());
        $polysource = array_filter($loaded, static fn (string $name): bool => str_starts_with($name, 'Polysource'));

        if ([] === $polysource) {
            return [
                'Polysource bundles',
                self::STATUS_FAIL,
                'No Polysource bundle is registered on this kernel. Add them to config/bundles.php.',
            ];
        }

        return [
            'Polysource bundles',
            self::STATUS_PASS,
            \sprintf('%d registered (%s)', \count($polysource), implode(', ', $polysource)),
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function checkEasyAdminCoLoaded(): array
    {
        $loaded = array_keys($this->kernel->getBundles());
        $hasBridge = \in_array('PolysourceEasyAdminFilterBridgeBundle', $loaded, true);
        $hasEa = \in_array('EasyAdminBundle', $loaded, true);

        if (!$hasBridge) {
            return [
                'EA bridge co-load',
                self::STATUS_PASS,
                'PolysourceEasyAdminFilterBridgeBundle not loaded — skip.',
            ];
        }

        if ($hasEa) {
            return [
                'EA bridge co-load',
                self::STATUS_PASS,
                'EasyAdminBundle is loaded alongside the bridge.',
            ];
        }

        // The bridge ships C1 guards (v0.5.7) so it's a no-op on EA-less
        // kernels. The DI extension + Bundle::boot() both short-circuit.
        // Calling it a WARN: the host probably forgot to load EA, but the
        // bridge won't crash.
        return [
            'EA bridge co-load',
            self::STATUS_WARN,
            'Bridge bundle is loaded but EasyAdminBundle is NOT. '
            . 'Bridge is a no-op on this kernel (v0.5.7 C1 guard). '
            . 'Did you mean to scope the bridge to an EA-only kernel?',
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function checkPlugins(): array
    {
        if (null === $this->plugins) {
            return [
                'Polysource plugins',
                self::STATUS_WARN,
                'PluginRegistry not wired — `polysource:plugins:list` won\'t work either.',
            ];
        }

        $rows = [];
        foreach ($this->plugins->all() as $plugin) {
            $rows[] = $plugin->getPluginName() . ' ' . $plugin->getPluginVersion();
        }

        if ([] === $rows) {
            return [
                'Polysource plugins',
                self::STATUS_WARN,
                'No plugins discovered. Either no Polysource feature bundle is loaded, or the AsPlugin attribute is missing.',
            ];
        }

        return [
            'Polysource plugins',
            self::STATUS_PASS,
            \sprintf('%d discovered (%s)', \count($rows), implode(', ', $rows)),
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function checkDoctrineSchema(): array
    {
        if (null === $this->doctrine) {
            return [
                'Doctrine schema',
                self::STATUS_WARN,
                'Doctrine not installed — saved views / column prefs / audit / etc. need Doctrine to persist. Skip if your host has its own storage.',
            ];
        }

        try {
            $em = $this->doctrine->getManager();
            if (!$em instanceof EntityManagerInterface) {
                return [
                    'Doctrine schema',
                    self::STATUS_WARN,
                    'Default Doctrine manager is not an EntityManager (probably ODM) — schema check skipped.',
                ];
            }
            $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
            $allMetadata = $em->getMetadataFactory()->getAllMetadata();
            $polysourceMetadata = array_values(array_filter(
                $allMetadata,
                static fn ($m): bool => str_starts_with($m->getName(), 'Polysource\\'),
            ));

            if ([] === $polysourceMetadata) {
                return [
                    'Doctrine schema',
                    self::STATUS_PASS,
                    'No Polysource Doctrine entity registered on this EntityManager — skip.',
                ];
            }

            $sqls = $tool->getUpdateSchemaSql($polysourceMetadata);

            // Filter out non-DDL noise (some DBAL platforms emit comments).
            $significant = array_values(array_filter(
                $sqls,
                static fn (string $sql): bool => '' !== trim($sql),
            ));

            if ([] === $significant) {
                return [
                    'Doctrine schema',
                    self::STATUS_PASS,
                    \sprintf(
                        '%d Polysource entit%s in sync with the database.',
                        \count($polysourceMetadata),
                        1 === \count($polysourceMetadata) ? 'y' : 'ies',
                    ),
                ];
            }

            return [
                'Doctrine schema',
                self::STATUS_FAIL,
                \sprintf(
                    '%d pending DDL statement(s) — run `bin/console doctrine:migrations:diff` then `migrations:migrate`. '
                    . 'See docs/user/easyadmin-filter-bridge/getting-started.md#2c-database-schema-required-if-doctrine-is-wired.',
                    \count($significant),
                ),
            ];
        } catch (Throwable $e) {
            return [
                'Doctrine schema',
                self::STATUS_WARN,
                \sprintf('Could not check schema: %s', $e->getMessage()),
            ];
        }
    }

    private function renderStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_PASS => '<fg=green>✓ PASS</>',
            self::STATUS_WARN => '<fg=yellow>! WARN</>',
            self::STATUS_FAIL => '<fg=red>✗ FAIL</>',
            default => $status,
        };
    }
}
