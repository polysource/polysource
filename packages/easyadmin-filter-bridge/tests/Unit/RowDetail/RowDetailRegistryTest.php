<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\RowDetail;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetail;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailProviderInterface;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use stdClass;

final class RowDetailRegistryTest extends TestCase
{
    #[Test]
    public function indexesProvidersByEntityFqcn(): void
    {
        $provider = self::provider(stdClass::class);
        $registry = new RowDetailRegistry([$provider]);

        self::assertSame($provider, $registry->providerFor(stdClass::class));
    }

    #[Test]
    public function unknownEntityYieldsNull(): void
    {
        $registry = new RowDetailRegistry([]);

        self::assertNull($registry->providerFor(stdClass::class));
    }

    #[Test]
    public function laterRegistrationOverridesEarlier(): void
    {
        $first = self::provider(stdClass::class);
        $second = self::provider(stdClass::class);
        $registry = new RowDetailRegistry([$first, $second]);

        self::assertSame($second, $registry->providerFor(stdClass::class), 'DI override semantics: last wins');
    }

    /**
     * @param class-string $fqcn
     */
    private static function provider(string $fqcn): RowDetailProviderInterface
    {
        return new class($fqcn) implements RowDetailProviderInterface {
            /**
             * @param class-string $fqcn
             */
            public function __construct(private readonly string $fqcn)
            {
            }

            public function getSupportedEntity(): string
            {
                return $this->fqcn;
            }

            public function getPermission(): ?string
            {
                return null;
            }

            public function getRowDetail(object $entity): RowDetail
            {
                return RowDetail::template('detail.html.twig');
            }
        };
    }
}
