<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Pins the bundle config surface to a known shape.
 *
 * v0.5.7 introduced `auto_register_routes` as the bundle's first
 * configurable knob — opt-out for multi-tenant hosts mounting EA
 * under a custom prefix. Defaults to `true` (back-compat with the
 * zero-config single-tenant install).
 */
final class ConfigurationTest extends TestCase
{
    public function testDefaultAutoRegisterRoutesIsTrue(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertSame(
            ['auto_register_routes' => true],
            $config,
            'A bare bundle config must default to auto-registering routes — the zero-config single-tenant install path.',
        );
    }

    public function testAutoRegisterRoutesCanBeDisabled(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(
            new Configuration(),
            [['auto_register_routes' => false]],
        );

        self::assertFalse(
            $config['auto_register_routes'],
            'Multi-tenant hosts must be able to disable the auto-registration — they import routes themselves under a custom prefix.',
        );
    }

    public function testNonBooleanAutoRegisterRoutesIsRejected(): void
    {
        $this->expectException(InvalidTypeException::class);

        (new Processor())->processConfiguration(
            new Configuration(),
            [['auto_register_routes' => 'not-a-bool']],
        );
    }
}
