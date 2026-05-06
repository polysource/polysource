<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Dashboard landing — for the showcase, redirect to the
        // products list (Phase F replaces this with the polysource/widgets
        // Dashboard rendered via Twig Components).
        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => ProductCrudController::class,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ShopCo Admin')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Catalog');
        yield MenuItem::linkTo('Products', 'fa fa-box', ProductCrudController::class)->setAction('index');

        yield MenuItem::section('Sales');
        yield MenuItem::linkTo('Orders', 'fa fa-shopping-cart', OrderCrudController::class)->setAction('index');
        yield MenuItem::linkTo('Refunds', 'fa fa-undo', RefundCrudController::class)->setAction('index');
        yield MenuItem::linkTo('Customers', 'fa fa-user', CustomerCrudController::class)->setAction('index');

        yield MenuItem::section('Polysource Standalone');
        yield MenuItem::linkToUrl('Failed messages', 'fa fa-bug', '/admin/polysource/failed-messages');
        yield MenuItem::linkToUrl('Audit log', 'fa fa-clipboard-list', '/admin/polysource/audit-log');
        yield MenuItem::linkToUrl('Bulk jobs', 'fa fa-tasks', '/admin/polysource/bulk-jobs');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Sign out', 'fa fa-sign-out-alt', '/logout');
    }
}
