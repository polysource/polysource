<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\ColumnPreference\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Polysource\Filter\ColumnPreference\Model\ColumnPreference;
use Polysource\Filter\ColumnPreference\Storage\InMemoryColumnPreferenceStorage;
use Polysource\Filter\ColumnPreference\Twig\ColumnPreferenceExtension;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\TwigFunction;

#[CoversClass(ColumnPreferenceExtension::class)]
final class ColumnPreferenceExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheTwoExpectedTwigFunctions(): void
    {
        $extension = new ColumnPreferenceExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_column_hidden', $names);
        self::assertContains('polysource_hidden_columns', $names);
        self::assertCount(2, $names);
    }

    #[Test]
    public function returnsSafeDefaultsWhenServiceIsNull(): void
    {
        $extension = new ColumnPreferenceExtension();

        self::assertFalse($extension->isHidden('orders', 'paidAt'));
        self::assertSame([], $extension->hiddenColumns('orders'));
    }

    #[Test]
    public function reflectsTheServicesHiddenList(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $storage->save(new ColumnPreference('alice', 'orders', ['paidAt']));

        $tokenStorage = new TokenStorage();
        $user = new InMemoryUser('alice', null, ['ROLE_USER']);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $service = new ColumnPreferenceService($storage, $tokenStorage);
        $extension = new ColumnPreferenceExtension($service);

        self::assertTrue($extension->isHidden('orders', 'paidAt'));
        self::assertFalse($extension->isHidden('orders', 'reference'));
        self::assertSame(['paidAt'], $extension->hiddenColumns('orders'));
        // Different resource — empty list
        self::assertSame([], $extension->hiddenColumns('customers'));
    }
}
