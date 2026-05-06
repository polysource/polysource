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
            ->setTitle('<span class="fw-semibold">ShopCo</span> <span class="text-muted">admin</span>')
            ->setFaviconPath('favicon.ico')
            ->setLocales(['en' => 'English']);
    }

    public function configureMenuItems(): iterable
    {
        // EA 5 signature: linkTo(controllerFqcn, label, icon).
        yield MenuItem::linkToDashboard('Home', 'fa fa-house');

        yield MenuItem::section('Catalog');
        yield MenuItem::linkTo(ProductCrudController::class, 'Products', 'fa fa-box')->setAction('index');

        yield MenuItem::section('Sales');
        yield MenuItem::linkTo(OrderCrudController::class, 'Orders', 'fa fa-shopping-cart')->setAction('index');
        yield MenuItem::linkTo(RefundCrudController::class, 'Refunds', 'fa fa-rotate-left')->setAction('index');
        yield MenuItem::linkTo(CustomerCrudController::class, 'Customers', 'fa fa-user')->setAction('index');

        yield MenuItem::section('Polysource Standalone');
        yield MenuItem::linkToUrl('Failed messages', 'fa fa-bug', '/admin/polysource/failed-messages');
        yield MenuItem::linkToUrl('Login attempts', 'fa fa-key', '/admin/polysource/login-attempts');
        yield MenuItem::linkToUrl('Audit log', 'fa fa-clipboard-list', '/admin/polysource/audit-log');
        yield MenuItem::linkToUrl('Bulk jobs', 'fa fa-tasks', '/admin/polysource/bulk-jobs');

        yield MenuItem::section('Adapter resources');
        yield MenuItem::linkToUrl('Redis cache', 'fa fa-bolt', '/admin/polysource/cache-keys');
        yield MenuItem::linkToUrl('S3 files', 'fa fa-folder-open', '/admin/polysource/s3-files');
        yield MenuItem::linkToUrl('Microservices', 'fa fa-cubes', '/admin/polysource/microservices');
        yield MenuItem::linkToUrl('Search index', 'fa fa-magnifying-glass', '/admin/polysource/search-index');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Sign out', 'fa fa-arrow-right-from-bracket', '/logout');
    }
}
