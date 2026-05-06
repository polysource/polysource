<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase C smoke — every EA index route renders without crashing for
 * an authenticated admin. Filter rendering is exercised by issuing
 * the GET with a `filters[...]` query string, which triggers the
 * polysource/easyadmin-filter-bridge configurators.
 */
final class EasyAdminSmokeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $container = self::getContainer();

        /** @var UserRepository $repo */
        $repo = $container->get(UserRepository::class);
        $admin = $repo->findOneBy(['email' => 'admin@shop.co']);

        if (!$admin instanceof User) {
            // Self-seed when fixtures aren't loaded — keeps the smoke test
            // independent and fast.
            /** @var EntityManagerInterface $em */
            $em = $container->get(EntityManagerInterface::class);
            /** @var UserPasswordHasherInterface $hasher */
            $hasher = $container->get(UserPasswordHasherInterface::class);

            $admin = (new User())
                ->setEmail('admin@shop.co')
                ->setFirstName('Alice')
                ->setLastName('Anderson')
                ->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($hasher->hashPassword($admin, 'shopco'));

            $em->persist($admin);
            $em->flush();
        }

        $this->client->loginUser($admin);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function adminRouteProvider(): iterable
    {
        yield 'dashboard' => ['/admin'];
        yield 'products index' => ['/admin/product'];
        yield 'customers index' => ['/admin/customer'];
        yield 'orders index' => ['/admin/order'];
        yield 'refunds index' => ['/admin/refund'];
        // Polysource standalone resources — auto-discovered via #[AsResource].
        yield 'failed messages' => ['/admin/polysource/failed-messages'];
        yield 'login attempts' => ['/admin/polysource/login-attempts'];
        yield 'audit log' => ['/admin/polysource/audit-log'];
        yield 'bulk jobs' => ['/admin/polysource/bulk-jobs'];
    }

    /**
     * @dataProvider adminRouteProvider
     */
    public function testAdminRouteRenders(string $url): void
    {
        $this->client->request('GET', $url);

        // EA dashboard redirects to ProductCrudController.
        if ($url === '/admin') {
            self::assertResponseRedirects();

            return;
        }

        self::assertResponseIsSuccessful(sprintf('Expected 200 on %s', $url));
    }

    public function testProductFilterEnhancementRenders(): void
    {
        // Hit the render-filters AJAX endpoint to exercise the
        // polysource/easyadmin-filter-bridge configurators.
        $this->client->request('GET', '/admin/product/render-filters');
        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertNotEmpty($body, 'render-filters returned empty body');
    }
}
