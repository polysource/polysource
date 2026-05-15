<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\BulkActionHistory\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\BulkActionHistory\Command\PurgeBulkActionHistoryCommand;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;
use Polysource\Filter\BulkActionHistory\Storage\InMemoryBulkActionHistoryStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeBulkActionHistoryCommand::class)]
final class PurgeBulkActionHistoryCommandTest extends TestCase
{
    #[Test]
    public function purgesEntriesOlderThanDefault90DayTtl(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->entry('e-old', new DateTimeImmutable('-120 days')));
        $storage->append($this->entry('e-new', new DateTimeImmutable('-30 days')));

        $tester = new CommandTester(new PurgeBulkActionHistoryCommand($storage));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 entry deleted', $tester->getDisplay());
    }

    #[Test]
    public function pluralisesCorrectly(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->entry('a', new DateTimeImmutable('-120 days')));
        $storage->append($this->entry('b', new DateTimeImmutable('-120 days')));

        $tester = new CommandTester(new PurgeBulkActionHistoryCommand($storage));
        $tester->execute([]);

        self::assertStringContainsString('2 entries deleted', $tester->getDisplay());
    }

    #[Test]
    public function rejectsMalformedTtl(): void
    {
        $tester = new CommandTester(new PurgeBulkActionHistoryCommand(new InMemoryBulkActionHistoryStorage()));
        $tester->execute(['--ttl' => 'bad-interval']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Invalid --ttl value', $tester->getDisplay());
    }

    #[Test]
    public function dryRunDoesNotDelete(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->entry('e-old', new DateTimeImmutable('-200 days')));

        $tester = new CommandTester(new PurgeBulkActionHistoryCommand($storage));
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(1, $storage->recent(null, null, 10), 'dry-run must NOT delete');
    }

    private function entry(string $id, DateTimeImmutable $occurredAt): BulkActionEntry
    {
        return new BulkActionEntry(
            id: $id,
            ownerId: 'tester',
            resourceName: 'App\\Entity\\Order',
            actionName: 'archive',
            affectedCount: 1,
            occurredAt: $occurredAt,
            metadata: [],
        );
    }
}
