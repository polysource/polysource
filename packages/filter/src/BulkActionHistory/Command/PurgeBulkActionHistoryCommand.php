<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Command;

use DateInterval;
use DateTimeImmutable;
use Polysource\Filter\BulkActionHistory\Storage\BulkActionHistoryStorageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Removes bulk-action-history rows older than `--ttl`.
 *
 * The `polysource_bulk_action_history` table is append-only. Every
 * bulk action recorded by `BulkActionHistoryService::record()` adds a
 * row. Hosts with high bulk-action throughput accumulate rows
 * indefinitely without a periodic purge.
 *
 * **Compliance warning:** if your audit log is the canonical
 * compliance register (GDPR Art. 30, SOX, internal policy), do NOT
 * run this command — the table IS the register. The TTL knob is for
 * casual housekeeping in hosts where bulk-action-history serves
 * primarily as a "recent actions" UX surface.
 *
 * Recommended schedule (for non-regulated hosts): weekly cron with a
 * 90-day TTL.
 *
 * @since 0.6.1
 */
#[AsCommand(
    name: 'polysource:bulk-action-history:purge',
    description: 'Delete bulk-action-history rows older than the given TTL (default: 90 days). NOT for regulated audit logs.',
)]
final class PurgeBulkActionHistoryCommand extends Command
{
    public function __construct(private readonly BulkActionHistoryStorageInterface $storage)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'ttl',
                null,
                InputOption::VALUE_REQUIRED,
                'TTL for entries. Accepts any `DateInterval` spec (e.g. P90D for 90 days, P1Y for a year).',
                'P90D',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report intent without actually deleting.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ttlSpecRaw = $input->getOption('ttl');
        $ttlSpec = \is_string($ttlSpecRaw) ? $ttlSpecRaw : 'P90D';

        try {
            $interval = new DateInterval($ttlSpec);
        } catch (Throwable $e) {
            $io->error(\sprintf(
                'Invalid --ttl value "%s". Expected a DateInterval spec like P90D (90 days), P1Y (1 year), PT1H (1 hour). Error: %s',
                $ttlSpec,
                $e->getMessage(),
            ));

            return Command::INVALID;
        }

        $cutoff = (new DateTimeImmutable('now'))->sub($interval);

        $io->section('Purging bulk-action-history entries older than ' . $cutoff->format('Y-m-d H:i:s T'));

        if (true === $input->getOption('dry-run')) {
            $io->note('Dry-run mode: skipping the actual DELETE. Re-run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $removed = $this->storage->purgeOlderThan($cutoff);

        $io->success(\sprintf(
            '%d entr%s deleted.',
            $removed,
            1 === $removed ? 'y' : 'ies',
        ));

        return Command::SUCCESS;
    }
}
