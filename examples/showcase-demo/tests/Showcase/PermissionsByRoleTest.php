<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase G smoke — proves the 3-role parcours works end to end.
 *
 *   ROLE_VIEWER → can browse every resource (read-only)
 *   ROLE_OPS    → adds POLYSOURCE_RESOURCE_EDIT, BULK_JOB_CANCEL,
 *                 WORKFLOW_TRANSITION, FAILED_MESSAGE_RETRY/DISMISS
 *   ROLE_ADMIN  → adds POLYSOURCE_AUDIT_EXPORT, FAILED_MESSAGE_PURGE
 *
 * The voter is exercised both directly (via $client->getContainer()->
 * get('security.authorization_checker')) and indirectly through HTTP
 * route access — covering both layers without a Panther round-trip.
 */
final class PermissionsByRoleTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->ensureUsers(self::getContainer());
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function rolePermissionMatrix(): iterable
    {
        yield 'viewer can read' => [
            'viewer@shop.co',
            ['POLYSOURCE_RESOURCE_VIEW', 'POLYSOURCE_AUDIT_VIEW', 'POLYSOURCE_BULK_JOB_VIEW', 'POLYSOURCE_LOGIN_ATTEMPTS_VIEW'],
        ];
        yield 'viewer cannot mutate' => [
            'viewer@shop.co',
            ['POLYSOURCE_RESOURCE_EDIT', 'POLYSOURCE_BULK_JOB_CANCEL', 'POLYSOURCE_WORKFLOW_TRANSITION'],
            true, // expect deny
        ];
        yield 'viewer cannot purge' => [
            'viewer@shop.co',
            ['POLYSOURCE_FAILED_MESSAGE_PURGE', 'POLYSOURCE_AUDIT_EXPORT'],
            true,
        ];

        yield 'ops can mutate' => [
            'ops@shop.co',
            ['POLYSOURCE_RESOURCE_VIEW', 'POLYSOURCE_RESOURCE_EDIT', 'POLYSOURCE_BULK_JOB_CANCEL', 'POLYSOURCE_WORKFLOW_TRANSITION', 'POLYSOURCE_FAILED_MESSAGE_RETRY'],
        ];
        yield 'ops cannot purge' => [
            'ops@shop.co',
            ['POLYSOURCE_FAILED_MESSAGE_PURGE', 'POLYSOURCE_AUDIT_EXPORT'],
            true,
        ];

        yield 'admin can purge + export' => [
            'admin@shop.co',
            ['POLYSOURCE_RESOURCE_EDIT', 'POLYSOURCE_BULK_JOB_CANCEL', 'POLYSOURCE_FAILED_MESSAGE_PURGE', 'POLYSOURCE_AUDIT_EXPORT'],
        ];
    }

    /**
     * @dataProvider rolePermissionMatrix
     *
     * @param list<string> $attributes
     */
    public function testVoterDecisionMatrix(string $email, array $attributes, bool $expectDeny = false): void
    {
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        $this->client->loginUser($user);

        $checker = self::getContainer()->get('security.authorization_checker');

        foreach ($attributes as $attribute) {
            $granted = $checker->isGranted($attribute);
            if ($expectDeny) {
                self::assertFalse($granted, sprintf('%s should NOT be granted %s', $email, $attribute));
            } else {
                self::assertTrue($granted, sprintf('%s should be granted %s', $email, $attribute));
            }
        }
    }

    public function testEveryRoleSeesTheHomeDashboard(): void
    {
        foreach (['admin@shop.co', 'ops@shop.co', 'viewer@shop.co'] as $email) {
            $user = self::getContainer()->get('doctrine')->getRepository(User::class)
                ->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $user, sprintf('Missing fixture user %s', $email));

            $this->client->loginUser($user);
            $this->client->request('GET', '/');
            self::assertResponseIsSuccessful(sprintf('%s should reach home', $email));
            self::assertSelectorExists('.polysource-widget', sprintf('%s should see the widgets dashboard', $email));
        }
    }

    private function ensureUsers(\Psr\Container\ContainerInterface $container): void
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $repo = $container->get('doctrine')->getRepository(User::class);

        foreach ([
            ['admin@shop.co', ['ROLE_ADMIN'], 'Alice', 'Anderson'],
            ['ops@shop.co', ['ROLE_OPS'], 'Olivier', 'Operator'],
            ['viewer@shop.co', ['ROLE_VIEWER'], 'Vera', 'Viewer'],
        ] as [$email, $roles, $first, $last]) {
            if ($repo->findOneBy(['email' => $email]) !== null) {
                continue;
            }
            $user = (new User())
                ->setEmail($email)
                ->setFirstName($first)
                ->setLastName($last)
                ->setRoles($roles);
            $user->setPassword($hasher->hashPassword($user, 'shopco'));
            $em->persist($user);
        }
        $em->flush();
    }
}
