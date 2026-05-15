<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Command\DoctorCommand;
use Polysource\Bundle\Plugin\PluginRegistry;
use Polysource\Core\Plugin\AdminPluginInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(DoctorCommand::class)]
final class DoctorCommandTest extends TestCase
{
    #[Test]
    public function exitsSuccessfullyWhenAllChecksPass(): void
    {
        $kernel = $this->kernelWithBundles(['PolysourceBundle', 'PolysourceFilterBundle']);
        $plugins = $this->pluginRegistry(['polysource/core 0.5.7']);

        $tester = new CommandTester(new DoctorCommand($kernel, $plugins, null));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('PASS', $display);
        self::assertStringContainsString('PolysourceBundle, PolysourceFilterBundle', $display);
    }

    #[Test]
    public function failsWhenNoPolysourceBundleRegistered(): void
    {
        $kernel = $this->kernelWithBundles(['FrameworkBundle', 'TwigBundle']);
        $plugins = $this->pluginRegistry([]);

        $tester = new CommandTester(new DoctorCommand($kernel, $plugins, null));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('FAIL', $tester->getDisplay());
        self::assertStringContainsString('No Polysource bundle is registered', $tester->getDisplay());
    }

    #[Test]
    public function warnsWhenBridgeIsLoadedButEasyAdminIsAbsent(): void
    {
        // Reflects the C1 dogfood scenario: multi-kernel app loaded the
        // bridge bundle globally but EA only on some kernels. v0.5.7's
        // C1 guard makes this safe, but the doctor still flags it as
        // a configuration smell so the user can scope the bundle
        // properly.
        $kernel = $this->kernelWithBundles(['PolysourceEasyAdminFilterBridgeBundle']);
        $plugins = $this->pluginRegistry(['polysource/easyadmin-filter-bridge 0.5.7']);

        $tester = new CommandTester(new DoctorCommand($kernel, $plugins, null));
        $tester->execute([]);

        // WARN doesn't fail the exit code.
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('WARN', $tester->getDisplay());
        self::assertStringContainsString('EasyAdminBundle is NOT', $tester->getDisplay());
    }

    #[Test]
    public function passesEaCoLoadWhenBridgeAndEaAreBothPresent(): void
    {
        $kernel = $this->kernelWithBundles([
            'PolysourceEasyAdminFilterBridgeBundle',
            'EasyAdminBundle',
        ]);
        $plugins = $this->pluginRegistry(['polysource/easyadmin-filter-bridge 0.5.7']);

        $tester = new CommandTester(new DoctorCommand($kernel, $plugins, null));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('EasyAdminBundle is loaded alongside the bridge', $tester->getDisplay());
    }

    #[Test]
    public function skipsDoctrineCheckWhenDoctrineIsNotInstalled(): void
    {
        $kernel = $this->kernelWithBundles(['PolysourceBundle']);
        $plugins = $this->pluginRegistry(['polysource/core 0.5.7']);

        $tester = new CommandTester(new DoctorCommand($kernel, $plugins, null));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        // WARN row on the schema check, but exit code is still 0.
        self::assertStringContainsString('Doctrine not installed', $tester->getDisplay());
    }

    /**
     * @param list<string> $bundleNames
     */
    private function kernelWithBundles(array $bundleNames): KernelInterface
    {
        $bundles = [];
        foreach ($bundleNames as $name) {
            $bundle = $this->createMock(BundleInterface::class);
            $bundle->method('getName')->willReturn($name);
            $bundles[$name] = $bundle;
        }

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($bundles);

        return $kernel;
    }

    /**
     * @param list<string> $pluginEntries each entry formatted as "name version"
     */
    private function pluginRegistry(array $pluginEntries): PluginRegistry
    {
        $plugins = [];
        foreach ($pluginEntries as $entry) {
            $parts = explode(' ', $entry, 2);
            $name = $parts[0];
            $version = $parts[1] ?? 'dev';
            $plugin = $this->createMock(AdminPluginInterface::class);
            $plugin->method('getPluginName')->willReturn($name);
            $plugin->method('getPluginVersion')->willReturn($version);
            $plugins[] = $plugin;
        }

        return new PluginRegistry($plugins);
    }
}
