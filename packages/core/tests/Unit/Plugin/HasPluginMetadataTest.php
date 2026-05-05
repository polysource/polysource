<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use RuntimeException;

#[CoversClass(HasPluginMetadata::class)]
#[CoversClass(AsPlugin::class)]
final class HasPluginMetadataTest extends TestCase
{
    #[Test]
    public function readsNameAndVersionFromAttribute(): void
    {
        $plugin = new SimplePluginFixture();

        self::assertSame('polysource/fixture', $plugin->getPluginName());
        self::assertSame('0.1.0', $plugin->getPluginVersion());
    }

    #[Test]
    public function throwsWhenAttributeMissing(): void
    {
        $plugin = new MissingAttributePluginFixture();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('uses HasPluginMetadata but has no #[AsPlugin] attribute');

        $plugin->getPluginName();
    }

    #[Test]
    public function classImplementingTraitSatisfiesInterface(): void
    {
        // Trait + interface together must produce a working
        // AdminPluginInterface — this is the canonical usage shape
        // for plugin authors. Calling each interface method
        // verifies the trait+attribute wiring is complete.
        $plugin = new SimplePluginFixture();

        $caller = static fn (AdminPluginInterface $p): array => [
            $p->getPluginName(),
            $p->getPluginVersion(),
        ];

        self::assertSame(['polysource/fixture', '0.1.0'], $caller($plugin));
    }
}

/**
 * @internal
 */
#[AsPlugin(name: 'polysource/fixture', version: '0.1.0')]
final class SimplePluginFixture implements AdminPluginInterface
{
    use HasPluginMetadata;
}

/**
 * @internal
 */
final class MissingAttributePluginFixture implements AdminPluginInterface
{
    use HasPluginMetadata;
}
