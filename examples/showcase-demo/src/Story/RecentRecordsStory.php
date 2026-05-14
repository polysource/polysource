<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\Order;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Filter\RecentRecords\Storage\Doctrine\RecentRecordRecord;
use Zenstruck\Foundry\Story;

/**
 * Seeds 8 entries into `polysource_recent_records` for the admin
 * user so the "Recently viewed orders" dashboard widget renders
 * a populated list out of the box. Without this seed the widget
 * would show "no recent records" on a fresh login until the user
 * had manually opened at least one order detail page.
 *
 * @since 0.5.2 (showcase wiring)
 */
final class RecentRecordsStory extends Story
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function build(): void
    {
        // Pick the most recent 8 orders so the widget links to
        // actual entities the user can navigate to.
        $orders = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult()
        ;

        $now = new DateTimeImmutable();
        $i = 0;
        foreach ($orders as $order) {
            $record = new RecentRecordRecord();
            $record->ownerId = 'admin@shop.co';
            $record->resourceName = 'orders';
            $record->recordId = (string) $order->getId();
            $record->viewedAt = $now->modify(\sprintf('-%d minutes', $i * 15));
            $record->label = \sprintf(
                '%s — %s',
                $order->getReference(),
                $order->getCustomer()?->getEmail() ?? 'no customer',
            );
            $this->em->persist($record);
            ++$i;
        }

        $this->em->flush();
    }
}
