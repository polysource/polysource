<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\FilterUrlToken\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\FilterUrlToken\Command\PurgeFilterUrlTokensCommand;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;
use Polysource\Filter\FilterUrlToken\Storage\InMemoryFilterUrlTokenStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeFilterUrlTokensCommand::class)]
final class PurgeFilterUrlTokensCommandTest extends TestCase
{
    #[Test]
    public function purgesTokensOlderThanDefaultTtlOf30Days(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $storage->save(new FilterUrlToken('aaaa11111111', 'r', ['x' => 1], new DateTimeImmutable('-60 days')));
        $storage->save(new FilterUrlToken('bbbb22222222', 'r', ['x' => 1], new DateTimeImmutable('-5 days')));

        $tester = new CommandTester(new PurgeFilterUrlTokensCommand($storage));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 token deleted', $tester->getDisplay());
        self::assertNull($storage->find('aaaa11111111'));
        self::assertNotNull($storage->find('bbbb22222222'));
    }

    #[Test]
    public function acceptsCustomTtlOption(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $storage->save(new FilterUrlToken('aaaaaaaaaaaa', 'r', ['x' => 1], new DateTimeImmutable('-2 hours')));
        $storage->save(new FilterUrlToken('bbbbbbbbbbbb', 'r', ['x' => 1], new DateTimeImmutable('-30 minutes')));

        $tester = new CommandTester(new PurgeFilterUrlTokensCommand($storage));
        $tester->execute(['--ttl' => 'PT1H']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNull($storage->find('aaaaaaaaaaaa'), '2h-old token must be purged with TTL=1h');
        self::assertNotNull($storage->find('bbbbbbbbbbbb'), '30min-old token must survive');
    }

    #[Test]
    public function rejectsMalformedTtlSpec(): void
    {
        $tester = new CommandTester(new PurgeFilterUrlTokensCommand(new InMemoryFilterUrlTokenStorage()));
        $tester->execute(['--ttl' => 'not-an-interval']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Invalid --ttl value', $tester->getDisplay());
    }

    #[Test]
    public function dryRunDoesNotDelete(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $storage->save(new FilterUrlToken('aaaa11111111', 'r', ['x' => 1], new DateTimeImmutable('-60 days')));

        $tester = new CommandTester(new PurgeFilterUrlTokensCommand($storage));
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dry-run mode', $tester->getDisplay());
        self::assertNotNull($storage->find('aaaa11111111'), 'dry-run must NOT delete');
    }

    #[Test]
    public function pluralisesTheCountInTheSuccessMessage(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $storage->save(new FilterUrlToken('aaaaaaaaaaaa', 'r', ['x' => 1], new DateTimeImmutable('-60 days')));
        $storage->save(new FilterUrlToken('bbbbbbbbbbbb', 'r', ['x' => 1], new DateTimeImmutable('-60 days')));

        $tester = new CommandTester(new PurgeFilterUrlTokensCommand($storage));
        $tester->execute([]);

        self::assertStringContainsString('2 tokens deleted', $tester->getDisplay());
    }
}
