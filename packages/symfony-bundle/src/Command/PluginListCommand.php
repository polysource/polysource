<?php

declare(strict_types=1);

namespace Polysource\Bundle\Command;

use Polysource\Bundle\Plugin\PluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists every Polysource plugin discovered at compile time.
 *
 * Usage: `bin/console polysource:plugins:list`
 *
 * Useful for support, debug, and documentation:
 *   - "is the bridge actually loaded?"
 *   - "what version of polysource/adapter-messenger does this app run?"
 *   - "did our plugin install correctly?"
 *
 * Per ADR-018 §4.
 *
 * @since 0.1.0
 */
#[AsCommand(
    name: 'polysource:plugins:list',
    description: 'List installed Polysource plugins with their versions.',
)]
final class PluginListCommand extends Command
{
    public function __construct(private readonly PluginRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->registry->all() as $plugin) {
            $rows[] = [$plugin->getPluginName(), $plugin->getPluginVersion()];
        }

        if ([] === $rows) {
            $io->warning('No Polysource plugins discovered. Did you register the bundles in config/bundles.php?');

            return Command::SUCCESS;
        }

        $io->title('Polysource plugins');
        $io->table(['Name', 'Version'], $rows);

        return Command::SUCCESS;
    }
}
