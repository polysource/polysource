<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Order;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Re-date the most-recent 50 orders so the dashboard widgets always
 * have data to render on the day the showcase is opened.
 *
 * The OrderFactory seeds `createdAt` between -12 months and "now AT
 * FIXTURE-LOAD TIME". Since `make showcase` only re-runs fixtures
 * when the database is empty, every subsequent boot drifts the
 * widgets' "Orders today" counter and the "Orders / hour (last 24h)"
 * chart further from reality — by 2026-05-10 a fixture load from
 * 2026-05-06 leaves both widgets at 0.
 *
 * This command bumps the 50 most recent orders' `createdAt` (and
 * the dependent `paidAt` / `shippedAt` / `deliveredAt` /
 * `cancelledAt` / `refundedAt`, by the same delta — keeps the
 * lifecycle invariants intact) onto a stratified distribution that
 * gives the chart visible bars in most of the 24 hourly bins:
 *
 *  - 4 orders in "today" (calendar day, working hours)
 *  - 22 orders stratified across the last 22 hours (1-2 per hour
 *    so the chart hardly ever shows 3+ consecutive empty bins —
 *    leaves 2 random hours empty for realistic noise)
 *  - 24 orders in "last 7 days excluding last 24h" (background
 *    fill so the "Orders this week" widget — when added — also has
 *    data without overweighting the chart bucket)
 *
 * Idempotent: re-running picks the same 50 most-recent orders (now
 * the freshly-bumped ones) and re-shuffles them within the same
 * envelope. Total order count stays constant at 1000.
 *
 * Wired into `make fixtures` and `make screenshots` so the demo is
 * fresh whatever clock-day the maintainer is on.
 */
#[AsCommand(
    name: 'app:freshen-orders',
    description: 'Re-date the most recent 50 orders so dashboard widgets always have data.',
)]
final class FreshenOrdersCommand extends Command
{
    private const FRESHEN_COUNT = 50;
    private const TODAY_COUNT = 4;
    private const LAST_24H_COUNT = 22;
    // Remaining 24 orders fall into "last 7 days excluding last 24h"
    // by subtraction.

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new DateTimeImmutable();

        $orders = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(self::FRESHEN_COUNT)
            ->getQuery()
            ->getResult();

        if ($orders === []) {
            $io->warning('No orders found — run `app:fixtures-load` first.');

            return Command::FAILURE;
        }

        // Build the 22-hour stratified schedule once, then iterate
        // over the picked orders consuming hours one at a time.
        $stratifiedHours = $this->stratifiedHourSchedule(self::LAST_24H_COUNT);

        $todayBucket = 0;
        $last24hBucket = 0;
        $last7dBucket = 0;

        foreach ($orders as $i => $order) {
            $newCreatedAt = match (true) {
                $i < self::TODAY_COUNT => $this->somewhereToday($now),
                $i < self::TODAY_COUNT + self::LAST_24H_COUNT => $this->stratifiedHourAgo($now, $stratifiedHours[$i - self::TODAY_COUNT]),
                default => $this->somewhereInLast7DaysExcludingLast24h($now),
            };

            $deltaSeconds = $newCreatedAt->getTimestamp() - $order->getCreatedAt()->getTimestamp();

            $order->setCreatedAt($newCreatedAt);
            $this->shiftIfPresent($order, 'paidAt', $deltaSeconds);
            $this->shiftIfPresent($order, 'shippedAt', $deltaSeconds);
            $this->shiftIfPresent($order, 'deliveredAt', $deltaSeconds);
            $this->shiftIfPresent($order, 'cancelledAt', $deltaSeconds);
            $this->shiftIfPresent($order, 'refundedAt', $deltaSeconds);

            if ($newCreatedAt >= $now->setTime(0, 0)) {
                ++$todayBucket;
            } elseif ($newCreatedAt >= $now->modify('-24 hours')) {
                ++$last24hBucket;
            } else {
                ++$last7dBucket;
            }
        }

        $this->em->flush();

        $io->writeln(\sprintf(
            '  → %d orders re-dated: %d today, %d in last 24h, %d in last 7d',
            \count($orders),
            $todayBucket,
            $last24hBucket,
            $last7dBucket,
        ));

        $io->success('Orders freshened — dashboard widgets ready.');

        return Command::SUCCESS;
    }

    /**
     * Build a list of $count "hours-ago" values stratified across
     * 1..23 hours so the chart's hourly bins are densely populated.
     * For $count=22, exactly 2 hours in [1, 23] are dropped, picked
     * randomly so each run has a slightly different shape — looks
     * organic rather than templated.
     *
     * @return list<int>
     */
    private function stratifiedHourSchedule(int $count): array
    {
        $availableHours = range(1, 23);
        shuffle($availableHours);

        return \array_slice($availableHours, 0, $count);
    }

    private function stratifiedHourAgo(DateTimeImmutable $now, int $hoursAgo): DateTimeImmutable
    {
        $minutesAgo = random_int(0, 59);

        return $now->modify(\sprintf('-%d hours -%d minutes', $hoursAgo, $minutesAgo));
    }

    private function somewhereToday(DateTimeImmutable $now): DateTimeImmutable
    {
        $hour = random_int(8, 22);
        $minute = random_int(0, 59);
        $second = random_int(0, 59);

        $candidate = $now->setTime($hour, $minute, $second);

        // If the random hour is in the future, snap to one minute ago.
        return $candidate <= $now ? $candidate : $now->modify('-1 minute');
    }

    private function somewhereInLast7DaysExcludingLast24h(DateTimeImmutable $now): DateTimeImmutable
    {
        $daysAgo = random_int(2, 7);
        $hoursAgo = random_int(0, 23);

        return $now->modify(\sprintf('-%d days -%d hours', $daysAgo, $hoursAgo));
    }

    /**
     * Shift the property by $deltaSeconds when it is non-null, leave
     * it alone otherwise (preserves the meaning of the lifecycle
     * timestamps — e.g. a CART order has no paidAt, freshening must
     * not invent one).
     */
    private function shiftIfPresent(Order $order, string $property, int $deltaSeconds): void
    {
        $getter = 'get' . ucfirst($property);
        $setter = 'set' . ucfirst($property);

        $current = $order->{$getter}();
        if ($current === null) {
            return;
        }

        $order->{$setter}($current->modify(\sprintf('%+d seconds', $deltaSeconds)));
    }
}
