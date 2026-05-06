<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Dashboard landing — redirect to the products list using EA 5's
        // AdminUrlGenerator. `redirectToRoute('admin', [...])` would loop
        // infinitely on EA 5 because the dashboard subscriber re-enters
        // this index() before the CRUD dispatcher runs. The url generator
        // builds the same URL but with the `signature` token EA needs
        // to short-circuit the loop and hand off to the CRUD controller.
        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(ProductCrudController::class)
            ->setAction('index')
            ->generateUrl();

        return $this->redirect($url);
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
