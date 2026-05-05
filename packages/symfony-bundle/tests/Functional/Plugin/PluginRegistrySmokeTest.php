<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\Plugin;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Plugin\PluginRegistry;
use Polysource\Bundle\PolysourceBundle;
use Polysource\Bundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end smoke test for the Plugin discovery wiring.
 *
 * Verifies that:
 *   - PluginRegistry resolves from the container.
 *   - PolysourceBundle (which uses #[AsPlugin]) is auto-tagged by
 *     PluginCompilerPass and shows up in the registry.
 *   - `polysource:plugins:list` command runs and prints the bundle.
 */
final class PluginRegistrySmokeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony installs an exception handler during kernel boot that is
        // not popped on shutdown. Restoring once leaves the stack as
        // PHPUnit found it. Cf. symfony/phpunit-bridge for the canonical
        // long-term fix (using its SymfonyTestsListener extension).
        restore_exception_handler();
    }

    #[Test]
    public function registryIsAvailableInTheContainer(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get(PluginRegistry::class);

        self::assertInstanceOf(PluginRegistry::class, $registry);
    }

    #[Test]
    public function polysourceBundleIsRegisteredAsAPlugin(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get(PluginRegistry::class);
        \assert($registry instanceof PluginRegistry);

        // The TestKernel registers PolysourceBundle which carries
        // #[AsPlugin(name: 'polysource/symfony-bundle', version: '0.1.0-alpha.1')].
        // The compiler pass should auto-tag it; the registry should
        // list it.
        self::assertTrue(
            $registry->has('polysource/symfony-bundle'),
            'PolysourceBundle should appear in the registry by its #[AsPlugin] name.',
        );

        $plugin = $registry->get('polysource/symfony-bundle');
        self::assertInstanceOf(PolysourceBundle::class, $plugin);
        self::assertSame('polysource/symfony-bundle', $plugin->getPluginName());
        self::assertSame('0.1.0-alpha.1', $plugin->getPluginVersion());
    }

    #[Test]
    public function pluginsListCommandRunsAndShowsTheBundle(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('polysource:plugins:list');

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('polysource/symfony-bundle', $output);
        self::assertStringContainsString('0.1.0-alpha.1', $output);
    }
}
