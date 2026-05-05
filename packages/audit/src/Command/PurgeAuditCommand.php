<?php

declare(strict_types=1);

namespace Polysource\Audit\Command;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Cron-friendly retention command — delete audit entries older than
 * `--before=YYYY-MM-DD`. Mandatory cutoff (no implicit default) so
 * an accidental `bin/console polysource:audit:purge` doesn't wipe
 * the table.
 *
 * Hosts schedule via crontab / systemd timer / Symfony Scheduler:
 *
 *     # Keep 13 months of audit (HIPAA / GDPR Art. 30 baseline)
 *     0 3 * * 0 /app/bin/console polysource:audit:purge --before=$(date -d "13 months ago" +\%Y-\%m-\%d)
 *
 * The `--dry-run` flag prints what *would* be deleted without
 * touching the table — useful for verifying the cutoff in staging.
 *
 * Cf. ADR-020 §7. Returns exit code 0 on success, 1 on missing/bad
 * cutoff, 2 on database failure.
 */
#[AsCommand(
    name: 'polysource:audit:purge',
    description: 'Delete audit log entries older than the given cutoff date',
)]
final class PurgeAuditCommand extends Command
{
    public const EXIT_OK = 0;
    public const EXIT_BAD_INPUT = 1;
    public const EXIT_DB_ERROR = 2;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'before',
                null,
                InputOption::VALUE_REQUIRED,
                'Cutoff date (YYYY-MM-DD or any ISO-8601 datetime). Entries with occurred_at < this value are deleted.',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Print the count of entries that would be deleted without actually deleting them.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $beforeRaw = $input->getOption('before');
        if (!\is_string($beforeRaw) || '' === $beforeRaw) {
            $io->error('--before=YYYY-MM-DD is required (no implicit default — refusing to wipe the audit log accidentally).');

            return self::EXIT_BAD_INPUT;
        }

        try {
            $cutoff = new DateTimeImmutable($beforeRaw, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            $io->error(\sprintf('Invalid --before value "%s": %s', $beforeRaw, $e->getMessage()));

            return self::EXIT_BAD_INPUT;
        }

        $isDryRun = (bool) $input->getOption('dry-run');

        try {
            $countQb = $this->em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from(AuditEntryRecord::class, 'r')
                ->where('r.occurredAt < :cutoff')
                ->setParameter('cutoff', $cutoff);
            $count = (int) $countQb->getQuery()->getSingleScalarResult();
        } catch (Throwable $e) {
            $io->error(\sprintf('Could not count entries: %s', $e->getMessage()));

            return self::EXIT_DB_ERROR;
        }

        if (0 === $count) {
            $io->success(\sprintf('No audit entries older than %s — nothing to purge.', $cutoff->format(\DATE_ATOM)));

            return self::EXIT_OK;
        }

        if ($isDryRun) {
            $io->note(\sprintf('[dry-run] Would delete %d audit %s older than %s.', $count, 1 === $count ? 'entry' : 'entries', $cutoff->format(\DATE_ATOM)));

            return self::EXIT_OK;
        }

        try {
            $deleteQb = $this->em->createQueryBuilder()
                ->delete(AuditEntryRecord::class, 'r')
                ->where('r.occurredAt < :cutoff')
                ->setParameter('cutoff', $cutoff);
            $rawDeleted = $deleteQb->getQuery()->execute();
            $deleted = \is_int($rawDeleted) ? $rawDeleted : 0;
        } catch (Throwable $e) {
            $io->error(\sprintf('Purge failed: %s', $e->getMessage()));

            return self::EXIT_DB_ERROR;
        }

        $io->success(\sprintf('Deleted %d audit %s older than %s.', $deleted, 1 === $deleted ? 'entry' : 'entries', $cutoff->format(\DATE_ATOM)));

        return self::EXIT_OK;
    }
}
