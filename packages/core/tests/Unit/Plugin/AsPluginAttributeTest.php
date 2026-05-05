<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Plugin;

use Attribute;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use ReflectionClass;

#[CoversClass(AsPlugin::class)]
final class AsPluginAttributeTest extends TestCase
{
    #[Test]
    public function exposesNameAndVersionViaPublicReadonlyProperties(): void
    {
        $attribute = new AsPlugin(name: 'polysource/example', version: '0.1.0');

        self::assertSame('polysource/example', $attribute->name);
        self::assertSame('0.1.0', $attribute->version);
    }

    #[Test]
    public function rejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AsPlugin name cannot be empty.');

        new AsPlugin(name: '', version: '0.1.0');
    }

    #[Test]
    public function rejectsEmptyVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AsPlugin version cannot be empty.');

        new AsPlugin(name: 'polysource/example', version: '');
    }

    #[Test]
    public function targetsClassesOnly(): void
    {
        $reflection = new ReflectionClass(AsPlugin::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        self::assertCount(1, $attributes, 'AsPlugin must declare exactly one Attribute.');

        /** @var Attribute $declaration */
        $declaration = $attributes[0]->newInstance();
        // TARGET_CLASS = 1; assert the flags equal that bit alone — no
        // method/property/parameter targets.
        self::assertSame(Attribute::TARGET_CLASS, $declaration->flags);
    }

    #[Test]
    public function isReadableViaReflectionWhenDeclaredOnAClass(): void
    {
        $attributes = (new ReflectionClass(SamplePlugin::class))->getAttributes(AsPlugin::class);
        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        self::assertInstanceOf(AsPlugin::class, $instance);
        self::assertSame('polysource/sample', $instance->name);
        self::assertSame('1.2.3', $instance->version);
    }
}

/**
 * Fixture used to verify attribute discovery via reflection.
 *
 * @internal
 */
#[AsPlugin(name: 'polysource/sample', version: '1.2.3')]
final class SamplePlugin
{
}
