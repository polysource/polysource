<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Functional\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\DependencyInjection\PolysourceBulkAsyncExtension;
use Polysource\BulkAsync\Mercure\MercureBulkJobBroadcaster;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mercure\HubInterface;

/**
 * Guards the optional-dependency gate of the Mercure broadcaster
 * (ADR-024 §8). The gate must use `interface_exists()` —
 * HubInterface is an interface, so a `class_exists()` check is
 * always false and silently drops the service even when Mercure
 * is installed. That exact regression shipped unnoticed because
 * no test asserted the registration.
 */
final class MercureBroadcasterRegistrationTest extends TestCase
{
    public function testBroadcasterIsRegisteredWhenMercureIsInstalled(): void
    {
        // The test suite runs with symfony/mercure installed — the
        // gate must therefore register the broadcaster.
        self::assertTrue(interface_exists(HubInterface::class), 'symfony/mercure must be a dev dependency of the test environment');

        $container = new ContainerBuilder();
        (new PolysourceBulkAsyncExtension())->load([], $container);

        self::assertTrue(
            $container->hasDefinition(MercureBulkJobBroadcaster::class),
            'MercureBulkJobBroadcaster must be registered when the Mercure component is present',
        );
        self::assertTrue(
            $container->getDefinition(MercureBulkJobBroadcaster::class)->hasTag('kernel.event_subscriber'),
            'The broadcaster must be tagged as an event subscriber to receive BulkJobProgressEvent',
        );
    }
}
