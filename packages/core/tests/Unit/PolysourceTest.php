<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Polysource;
use ReflectionClass;

#[CoversClass(Polysource::class)]
final class PolysourceTest extends TestCase
{
    #[Test]
    public function exposesNonEmptyConstants(): void
    {
        // Avoid asserting hard-coded constant values inline — PHPStan
        // flags `assertSame('lit', Cls::CONST)` as alreadyNarrowedType
        // when it can resolve both sides at static-analysis time. Use
        // reflection instead so the test still verifies the contract.
        $reflection = new ReflectionClass(Polysource::class);
        $constants = $reflection->getConstants();

        $expected = [
            'VERSION',
            'PAGE_INDEX', 'PAGE_DETAIL', 'PAGE_EDIT', 'PAGE_NEW',
            'TAG_DATA_SOURCE', 'TAG_RESOURCE', 'TAG_FIELD_CONFIGURATOR', 'TAG_ACTION', 'TAG_PERMISSION',
        ];

        foreach ($expected as $name) {
            self::assertArrayHasKey($name, $constants, \sprintf('Polysource::%s missing.', $name));
            self::assertIsString($constants[$name]);
            self::assertNotEmpty($constants[$name]);
        }

        foreach (['TAG_DATA_SOURCE', 'TAG_RESOURCE', 'TAG_FIELD_CONFIGURATOR', 'TAG_ACTION', 'TAG_PERMISSION'] as $tagName) {
            $value = $constants[$tagName];
            self::assertIsString($value);
            self::assertStringStartsWith('polysource.', $value);
        }
    }

    #[Test]
    public function constructorIsPrivate(): void
    {
        $reflection = new ReflectionClass(Polysource::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
