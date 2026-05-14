<?php

declare(strict_types=1);

namespace App\Twig;

use Polysource\Filter\RecentRecords\Model\RecentRecord;
use Polysource\Filter\RecentRecords\RecentRecordsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `app_recently_viewed_orders(limit)` —
 * fetches the current user's MRU list at render time so the
 * "Recently viewed orders" card on the home dashboard reflects
 * the live security context.
 *
 * Why not via the Polysource\Widgets Dashboard pipeline?
 * `DashboardRegistry::__construct` consumes the tagged_iterator
 * eagerly and caches the resolved Dashboard instances. Even with
 * `shared: false` on the dashboard factory, the Twig extension
 * (`DashboardExtension`) is itself a shared singleton holding a
 * cached registry, so the recently-viewed widget would always
 * render with the items array baked at boot — empty.
 *
 * Bypassing via a per-render Twig function keeps the user-scoped
 * widget data fresh without forking the widgets bundle.
 *
 * @since 0.5.2 (showcase wiring)
 */
final class RecentRecordsExtension extends AbstractExtension
{
    public function __construct(private readonly ?RecentRecordsService $service = null)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_recently_viewed_orders', $this->recentOrders(...)),
        ];
    }

    /**
     * @return list<RecentRecord>
     */
    public function recentOrders(int $limit = 8): array
    {
        return null === $this->service
            ? []
            : $this->service->recentForCurrentUser('orders', $limit);
    }
}
