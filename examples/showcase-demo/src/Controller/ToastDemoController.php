<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * v0.5.0 #4 — Showcase route that primes the flash bag with a
 * sample of each toast variant + redirects to the orders index.
 *
 * The screenshot pipeline hits this route → 302 to /admin/order
 * → `polysource_toasts()` reads + consumes the flashes → 3
 * alerts render top-right of the page. Without a deterministic
 * way to seed flashes, capturing the toast notification feature
 * in a screenshot would require driving the full bulk-action
 * flow (select row → click button → wait for redirect), which
 * is fragile and slow.
 *
 * Production hosts have no equivalent route — flashes get set
 * by their bulk-action handlers (cf.
 * `OrderCrudController::bulkMarkCancelled`).
 *
 * @since 0.5.2 (showcase wiring)
 */
final class ToastDemoController extends AbstractController
{
    #[Route(
        path: '/admin/showcase/toast-demo',
        name: 'showcase_toast_demo',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function flashAndRedirect(): RedirectResponse
    {
        $this->addFlash('success', 'Marked 12 orders as cancelled. ');
        $this->addFlash('warning', '2 orders skipped — they had open refunds.');
        $this->addFlash('info', 'Audit trail recorded — visit "Bulk action history" to inspect.');

        return $this->redirect('/admin/order');
    }
}
