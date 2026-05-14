<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * v0.5.0 #9 — Showcase "Preview bulk count" page.
 *
 * Calls `UrlFilterApplier` the same way `MatchingCountController`
 * does — but renders an HTML preview page with the count + first
 * 10 samples instead of returning JSON. Demonstrates the
 * filter-aware preview flow end-to-end in the showcase:
 *
 *   1. The Orders index has a "Preview bulk count" button next
 *      to the export actions (added to OrderCrudController).
 *   2. The user clicks → lands on this page, which reads the
 *      current `?filters[...]` URL slice and shows the number of
 *      orders that would be touched + a 10-row sample.
 *
 * In real production hosts the JSON endpoint
 * (`MatchingCountController` shipped by the bridge) is wired to
 * a modal opened via a tiny JS controller; we render server-side
 * here so the feature is screenshot-able + works without JS.
 *
 * @since 0.5.2 (showcase wiring)
 */
final class MatchingCountPreviewController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlFilterApplier $filterApplier,
    ) {
    }

    #[Route(
        path: '/admin/showcase/matching-count-preview/orders',
        name: 'showcase_matching_count_preview_orders',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function previewOrders(Request $request): Response
    {
        $metadata = $this->em->getClassMetadata(Order::class);

        // Count.
        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(e)')
            ->from(Order::class, 'e')
        ;
        $this->filterApplier->apply($countQb, $request->query->all(), $metadata, 'e');
        $count = (int) $countQb->getQuery()->getSingleScalarResult();

        // Sample — up to 10 rows.
        $sampleQb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Order::class, 'e')
            ->setMaxResults(10)
        ;
        $this->filterApplier->apply($sampleQb, $request->query->all(), $metadata, 'e');
        /** @var list<Order> $samples */
        $samples = $sampleQb->getQuery()->getResult();

        return $this->render('showcase/matching_count_preview.html.twig', [
            'count' => $count,
            'samples' => $samples,
            'filters' => $request->query->all()['filters'] ?? [],
        ]);
    }
}
