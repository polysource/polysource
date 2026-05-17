<?php

declare(strict_types=1);

namespace Polysource\Bundle\Command;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Install-time + runtime diagnostics for Polysource.
 *
 * Runs every tagged {@see HealthCheckInterface} service and tables
 * the results. Three statuses:
 *
 *   PASS — healthy
 *   WARN — non-fatal gap (e.g. host has no Doctrine — saved-views
 *          features won't work but the bundle is OK to keep installed)
 *   FAIL — fix needed before the host can use Polysource safely
 *
 * Exit code: 0 if no FAIL (any WARN is non-blocking), 1 if any FAIL.
 *
 * Plugins ship their own checks by tagging a service
 * `polysource.doctor.check` — the compiler pass collects them into
 * the iterator passed here. Refactored to the registry pattern in
 * v0.9.0 (previously a 299-line god-command with 5 hardcoded checks).
 *
 * Usage: `bin/console polysource:doctor`
 *
 * @since 0.6.0
 */
#[AsCommand(
    name: 'polysource:doctor',
    description: 'Diagnose install-time + runtime health of Polysource on this kernel.',
)]
final class DoctorCommand extends Command
{
    /**
     * @param iterable<HealthCheckInterface> $checks
     */
    public function __construct(private readonly iterable $checks)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Polysource Doctor');

        $rows = [];
        $failCount = 0;
        $warnCount = 0;

        foreach ($this->checks as $check) {
            $result = $check->run();
            $rows[] = [$check->getName(), $this->renderStatus($result->status), $result->detail];
            if (HealthCheckResult::STATUS_FAIL === $result->status) {
                ++$failCount;
            } elseif (HealthCheckResult::STATUS_WARN === $result->status) {
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

    private function renderStatus(string $status): string
    {
        return match ($status) {
            HealthCheckResult::STATUS_PASS => '<fg=green>✓ PASS</>',
            HealthCheckResult::STATUS_WARN => '<fg=yellow>! WARN</>',
            HealthCheckResult::STATUS_FAIL => '<fg=red>✗ FAIL</>',
            default => $status,
        };
    }
}
