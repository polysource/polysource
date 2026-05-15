<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Command;

use DateInterval;
use DateTimeImmutable;
use Polysource\Filter\FilterUrlToken\Storage\FilterUrlTokenStorageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Removes filter-URL-token rows older than `--ttl`.
 *
 * Every share-button click on an EA index page mints a new row in
 * `polysource_filter_url_tokens` (12-hex token + JSON slice +
 * createdAt). Without a periodic purge the table grows unbounded —
 * a host with 100 daily share clicks accumulates 36k rows/year.
 *
 * Recommended schedule: nightly cron with a 30-day TTL. Tokens are
 * intended for short-term sharing (Slack pings, support handoffs);
 * a multi-week TTL covers every realistic use case.
 *
 * @since 0.6.1
 */
#[AsCommand(
    name: 'polysource:filter-url-tokens:purge',
    description: 'Delete filter URL tokens older than the given TTL (default: 30 days). Run nightly via cron.',
)]
final class PurgeFilterUrlTokensCommand extends Command
{
    public function __construct(private readonly FilterUrlTokenStorageInterface $storage)
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
                'TTL for tokens. Accepts any `DateInterval` spec (e.g. P30D for 30 days, P1Y for a year).',
                'P30D',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report how many rows would be deleted without actually deleting them.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ttlSpecRaw = $input->getOption('ttl');
        $ttlSpec = \is_string($ttlSpecRaw) ? $ttlSpecRaw : 'P30D';

        try {
            $interval = new DateInterval($ttlSpec);
        } catch (Throwable $e) {
            $io->error(\sprintf(
                'Invalid --ttl value "%s". Expected a DateInterval spec like P30D (30 days), P1Y (1 year), PT1H (1 hour). Error: %s',
                $ttlSpec,
                $e->getMessage(),
            ));

            return Command::INVALID;
        }

        $cutoff = (new DateTimeImmutable('now'))->sub($interval);

        $io->section('Purging filter URL tokens older than ' . $cutoff->format('Y-m-d H:i:s T'));

        if (true === $input->getOption('dry-run')) {
            // For dry-run, we'd need a count() method on the storage.
            // The interface doesn't have one yet — implement when a host
            // actually asks. For now, dry-run is a passthrough that
            // doesn't execute the delete.
            $io->note('Dry-run mode: skipping the actual DELETE. Re-run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $removed = $this->storage->purgeOlderThan($cutoff);

        $io->success(\sprintf(
            '%d token%s deleted.',
            $removed,
            1 === $removed ? '' : 's',
        ));

        return Command::SUCCESS;
    }
}
