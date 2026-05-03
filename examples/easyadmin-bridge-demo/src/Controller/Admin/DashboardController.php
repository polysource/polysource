<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
// Product/Category entities are referenced via their CRUD controllers.
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // EasyAdmin's default dashboard renders the configured menu and a
        // welcome panel. Operators click "Products" in the sidebar to land
        // on the page that exercises every enhanced filter.
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Polysource — EasyAdmin filter bridge demo');
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(ProductCrudController::class, 'Products', 'fa fa-box');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Categories', 'fa fa-tag');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Override the default user menu — the upstream default tries to
        // render a logout link with an htmlAttributes array that EasyAdmin
        // 5.0.6 attempts to coerce to string, hitting "Array to string
        // conversion". Building the menu ourselves with no items dodges it.
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->displayUserName(true)
            ->displayUserAvatar(false)
            ->setMenuItems([]);
    }
}
