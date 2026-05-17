<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Identity;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Identity\IdentifiableInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * `IdentifiableInterface` is a tag contract — its sole purpose is to
 * declare the `getIdentifier(): string` shape consumers can rely on.
 * The interface ships zero behaviour so the test surface is small:
 * confirm the contract is reachable and confirm a typical
 * implementation satisfies it.
 */
#[CoversNothing]
final class IdentifiableInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDeclaresGetIdentifierSignature(): void
    {
        $reflection = new ReflectionClass(IdentifiableInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('getIdentifier'));

        $method = $reflection->getMethod('getIdentifier');
        $returnType = $method->getReturnType();

        self::assertNotNull($returnType, 'getIdentifier() must declare a return type');
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName());
        self::assertFalse($returnType->allowsNull(), 'getIdentifier() must NOT allow null returns');
    }

    #[Test]
    public function typicalImplementationSatisfiesContract(): void
    {
        $impl = new class implements IdentifiableInterface {
            public function getIdentifier(): string
            {
                return 'order-42';
            }
        };

        // PHPStan narrows `$impl` to the anonymous class which it
        // already knows implements the interface — skip the redundant
        // assertInstanceOf and just check the contract surface.
        self::assertSame('order-42', $impl->getIdentifier());
    }
}
