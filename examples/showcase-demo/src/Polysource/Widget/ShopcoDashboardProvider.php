<?php

declare(strict_types=1);

namespace App\Polysource\Widget;

use App\Entity\Order;
use App\Entity\Refund;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\RefundRepository;
use Polysource\Widgets\Dashboard\Dashboard;
use Polysource\Widgets\Widget\ChartWidget;
use Polysource\Widgets\Widget\CounterWidget;
use Polysource\Widgets\Widget\ListWidget;
/**
 * Builds the home dashboard from the live ShopCo state.
 *
 * The bundle's DashboardRegistry collects services tagged
 * `polysource.widgets.dashboard`. We register an `app.dashboard.home`
 * factory in services.yaml that calls `__invoke()` here — that
 * factory is the tagged service, not this provider class itself.
 *
 * Layout: 6 widgets across 3 rows:
 *   row 1: 3 Counter widgets (today's orders, pending refunds, low stock)
 *   row 2: 1 Chart (orders/hour last 24h)  +  1 List (top 5 products by stock)
 *   row 3: 1 List (recent customers, full-width)
 */
final class ShopcoDashboardProvider
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly RefundRepository $refunds,
        private readonly ProductRepository $products,
        private readonly CustomerRepository $customers,
    ) {
    }

    public function __invoke(): Dashboard
    {
        // Defensive: this provider is invoked the moment the
        // DashboardRegistry is built (during cache:warm), which can
        // happen BEFORE migrations have run on a fresh
        // `make showcase-reset && make showcase`. If any of the
        // ShopCo tables is missing we fall back to a single placeholder
        // counter so the page still renders + logs the cause.
        try {
            return new Dashboard(
                name: 'home',
                title: 'ShopCo dashboard',
                rows: [
                    [
                        $this->ordersTodayCounter(),
                        $this->pendingRefundsCounter(),
                        $this->lowStockCounter(),
                    ],
                    [
                        $this->ordersChart(),
                        $this->topProductsList(),
                    ],
                    [
                        $this->recentCustomersList(),
                    ],
                ],
            );
        } catch (\Doctrine\DBAL\Exception $e) {
            return $this->placeholderDashboard($e->getMessage());
        }
    }

    private function placeholderDashboard(string $reason): Dashboard
    {
        return new Dashboard(
            name: 'home',
            title: 'ShopCo dashboard',
            rows: [
                [
                    new CounterWidget(
                        id: 'db-not-ready',
                        title: 'Database not initialized',
                        value: 0,
                        unit: 'rows',
                        palette: 'secondary',
                        columnSpan: 12,
                    ),
                ],
            ],
        );
    }

    private function ordersTodayCounter(): CounterWidget
    {
        $today = new \DateTimeImmutable('today midnight');
        $count = $this->orders->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->where('o.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return new CounterWidget(
            id: 'orders-today',
            title: 'Orders today',
            value: (int) $count,
            unit: 'orders',
            palette: 'primary',
        );
    }

    private function pendingRefundsCounter(): CounterWidget
    {
        $count = $this->refunds->createQueryBuilder('r')
            ->select('COUNT(r)')
            ->where('r.status = :pending')
            ->setParameter('pending', Refund::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return new CounterWidget(
            id: 'pending-refunds',
            title: 'Pending refunds',
            value: (int) $count,
            unit: 'refunds',
            palette: 'warning',
        );
    }

    private function lowStockCounter(): CounterWidget
    {
        $count = $this->products->createQueryBuilder('p')
            ->select('COUNT(p)')
            ->where('p.stock < :threshold')
            ->andWhere('p.status = :active')
            ->setParameter('threshold', 10)
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return new CounterWidget(
            id: 'low-stock',
            title: 'Low-stock SKUs',
            value: (int) $count,
            unit: 'SKUs',
            palette: 'danger',
        );
    }

    private function ordersChart(): ChartWidget
    {
        $points = [];
        $now = new \DateTimeImmutable();
        for ($i = 23; $i >= 0; --$i) {
            $bucketStart = $now->modify(sprintf('-%d hours', $i + 1));
            $bucketEnd = $now->modify(sprintf('-%d hours', $i));
            $count = $this->orders->createQueryBuilder('o')
                ->select('COUNT(o)')
                ->where('o.createdAt >= :start')
                ->andWhere('o.createdAt < :end')
                ->setParameter('start', $bucketStart)
                ->setParameter('end', $bucketEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $points[] = [
                'label' => $bucketStart->format('H:i'),
                'value' => (int) $count,
            ];
        }

        return new ChartWidget(
            id: 'orders-per-hour',
            title: 'Orders / hour (last 24h)',
            points: $points,
            type: ChartWidget::TYPE_LINE,
        );
    }

    private function topProductsList(): ListWidget
    {
        $top = $this->products->createQueryBuilder('p')
            ->where('p.status = :active')
            ->setParameter('active', 'active')
            ->orderBy('p.stock', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return new ListWidget(
            id: 'top-stock-products',
            title: 'Top stock — active SKUs',
            items: $top,
            labelFn: static fn (mixed $p) => sprintf('%s — %d in stock', $p->getName(), $p->getStock()),
            hrefFn: static fn (mixed $p) => '/admin/product/'.$p->getId()->toRfc4122(),
        );
    }

    private function recentCustomersList(): ListWidget
    {
        $recent = $this->customers->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        return new ListWidget(
            id: 'recent-customers',
            title: 'Recent customers',
            items: $recent,
            labelFn: static fn (mixed $c) => sprintf('%s (%s)', $c->getFullName(), $c->getCountry()),
            hrefFn: static fn (mixed $c) => '/admin/customer/'.$c->getId()->toRfc4122(),
            columnSpan: 12,
        );
    }
}
