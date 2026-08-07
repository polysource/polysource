<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowDetailExtension;
use stdClass;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Render gate for the chevron cell. The backend endpoint re-checks
 * authoritatively — these tests pin the cosmetic-gate contract,
 * especially fail-closed when a permission is declared without a
 * wired security layer.
 */
final class RowDetailExtensionTest extends TestCase
{
    #[Test]
    public function unavailableWithoutProvider(): void
    {
        $extension = new RowDetailExtension(new RowDetailRegistry([]));

        self::assertFalse($extension->isAvailable(stdClass::class, new stdClass()));
    }

    #[Test]
    public function unavailableForNullEntity(): void
    {
        $extension = new RowDetailExtension(new RowDetailRegistry([self::provider(null)]));

        self::assertFalse($extension->isAvailable(stdClass::class, null));
    }

    #[Test]
    public function availableWhenProviderDeclaresNoPermission(): void
    {
        $extension = new RowDetailExtension(new RowDetailRegistry([self::provider(null)]));

        self::assertTrue($extension->isAvailable(stdClass::class, new stdClass()));
    }

    #[Test]
    public function failsClosedWhenPermissionDeclaredWithoutChecker(): void
    {
        $extension = new RowDetailExtension(new RowDetailRegistry([self::provider('SOME_ATTR')]), null);

        self::assertFalse($extension->isAvailable(stdClass::class, new stdClass()));
    }

    #[Test]
    public function delegatesToCheckerWithEntityAsSubject(): void
    {
        $entity = new stdClass();

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())
            ->method('isGranted')
            ->with('SOME_ATTR', $entity)
            ->willReturn(true);

        $extension = new RowDetailExtension(new RowDetailRegistry([self::provider('SOME_ATTR')]), $checker);

        self::assertTrue($extension->isAvailable(stdClass::class, $entity));
    }

    #[Test]
    public function deniedByCheckerHidesTheControl(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $extension = new RowDetailExtension(new RowDetailRegistry([self::provider('SOME_ATTR')]), $checker);

        self::assertFalse($extension->isAvailable(stdClass::class, new stdClass()));
    }

    private static function provider(?string $permission): AbstractRowDetailProvider
    {
        return new class($permission) extends AbstractRowDetailProvider {
            public function __construct(private readonly ?string $permission)
            {
            }

            public function getSupportedEntity(): string
            {
                return stdClass::class;
            }

            public function getPermission(): ?string
            {
                return $this->permission;
            }

            protected function template(): string
            {
                return 'detail.html.twig';
            }
        };
    }
}
