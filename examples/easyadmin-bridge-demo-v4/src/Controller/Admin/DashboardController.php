<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * EA v4 dashboard.
 *
 * EA 4 uses the classic `#[Route]` attribute on `index()` rather
 * than EA 5's `#[AdminDashboard(routePath, routeName)]`. Menu items
 * call `MenuItem::linkToCrud()` (EA 4) instead of `linkTo()` (EA 5).
 * Asset wiring goes through Webpack Encore or `assets:install`
 * symlinks, NOT AssetMapper — EA 4 predates AssetMapper integration.
 */
final class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Polysource — EasyAdmin v4 filter bridge demo');
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Products', 'fa fa-box', \Polysource\Demo\EasyAdminBridge\Entity\Product::class);
        yield MenuItem::linkToCrud('Categories', 'fa fa-tag', \Polysource\Demo\EasyAdminBridge\Entity\Category::class);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->displayUserName(true)
            ->displayUserAvatar(false)
            ->setMenuItems([]);
    }
}
