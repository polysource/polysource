<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Container;

use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\DependencyInjection\PolysourceEasyAdminFilterBridgeExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies that our DI extension auto-registers the form theme into
 * `twig.form_themes` via `PrependExtensionInterface::prepend()`.
 *
 * This is the contract that makes the bridge "drop-in" on the rendering
 * side: a host app installing the package gets the enhanced preset
 * buttons / chips / data-attributes rendered out of the box, with no
 * Twig config to write.
 */
final class PrependFormThemeTest extends TestCase
{
    private const EXPECTED_THEME = '@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig';

    public function testPrependRegistersFormThemeWhenTwigBundleIsLoaded(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
            'TwigBundle' => 'Symfony\\Bundle\\TwigBundle\\TwigBundle',
            'EasyAdminBundle' => 'EasyCorp\\Bundle\\EasyAdminBundle\\EasyAdminBundle',
            'PolysourceEasyAdminFilterBridgeBundle' => 'Polysource\\EasyAdminFilterBridge\\PolysourceEasyAdminFilterBridgeBundle',
        ]);

        (new PolysourceEasyAdminFilterBridgeExtension())->prepend($container);

        $twigConfig = $container->getExtensionConfig('twig');
        self::assertNotEmpty($twigConfig, 'prepend() must inject a twig config block');

        // `prepend()` issues multiple `prependExtensionConfig` calls
        // (form_themes + EasyAdmin namespace splice); the resulting
        // config array is order-dependent on Symfony's prepend stack.
        // Flatten and search.
        $formThemes = [];
        foreach ($twigConfig as $block) {
            if (isset($block['form_themes']) && \is_array($block['form_themes'])) {
                $formThemes = array_merge($formThemes, $block['form_themes']);
            }
        }
        self::assertContains(
            self::EXPECTED_THEME,
            $formThemes,
            'Our form theme must be auto-registered into twig.form_themes so host apps render the enhanced widgets without manual config',
        );
    }

    public function testPrependIsANoopWhenTwigBundleIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
            'EasyAdminBundle' => 'EasyCorp\\Bundle\\EasyAdminBundle\\EasyAdminBundle',
            // No TwigBundle — e.g. an API-only Symfony app that uses
            // EasyAdmin via a custom renderer; we must not crash on
            // missing TwigBundle.
        ]);

        (new PolysourceEasyAdminFilterBridgeExtension())->prepend($container);

        self::assertEmpty(
            $container->getExtensionConfig('twig'),
            'prepend() must skip the twig config injection when TwigBundle is not loaded — otherwise the container fails to compile',
        );
    }

    public function testFormThemeTemplateFileExists(): void
    {
        $absolute = \dirname(__DIR__, 3) . '/Resources/views/form/polysource_filter_theme.html.twig';

        self::assertFileExists(
            $absolute,
            'The Twig template referenced by prepend() must physically exist — otherwise host apps will get a "template not found" error at render time',
        );
    }
}
