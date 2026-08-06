<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\DependencyInjection\FeatureGate;
use stdClass;

#[CoversClass(FeatureGate::class)]
final class FeatureGateTest extends TestCase
{
    #[Test]
    public function hasBundleDetectsRegisteredBundleByName(): void
    {
        $bundles = ['DoctrineBundle' => stdClass::class, 'TwigBundle' => stdClass::class];

        self::assertTrue(FeatureGate::hasBundle($bundles, 'DoctrineBundle'));
        self::assertFalse(FeatureGate::hasBundle($bundles, 'SecurityBundle'));
    }

    #[Test]
    public function hasBundleHandlesNonArrayInput(): void
    {
        // Some test container builders don't seed `kernel.bundles` —
        // hasParameter returns false and the extension defaults to
        // null/false/string. FeatureGate must NOT crash on those.
        self::assertFalse(FeatureGate::hasBundle(null, 'DoctrineBundle'));
        self::assertFalse(FeatureGate::hasBundle(false, 'DoctrineBundle'));
        self::assertFalse(FeatureGate::hasBundle('not-an-array', 'DoctrineBundle'));
    }

    #[Test]
    public function namedShortcutsDelegateToHasBundle(): void
    {
        $bundles = [
            'FrameworkBundle' => stdClass::class,
            'DoctrineBundle' => stdClass::class,
            'SecurityBundle' => stdClass::class,
            'TwigBundle' => stdClass::class,
            'EasyAdminBundle' => stdClass::class,
        ];

        self::assertTrue(FeatureGate::hasFrameworkBundle($bundles));
        self::assertTrue(FeatureGate::hasDoctrineBundle($bundles));
        self::assertTrue(FeatureGate::hasSecurityBundle($bundles));
        self::assertTrue(FeatureGate::hasTwigBundle($bundles));
        self::assertTrue(FeatureGate::hasEasyAdminBundle($bundles));

        self::assertFalse(FeatureGate::hasFrameworkBundle([]));
    }

    #[Test]
    public function savedViewsAvailableRequiresBothDoctrineAndSecurity(): void
    {
        $full = ['DoctrineBundle' => '_', 'SecurityBundle' => '_'];
        self::assertTrue(FeatureGate::savedViewsAvailable($full));

        $doctrineOnly = ['DoctrineBundle' => '_'];
        self::assertFalse(FeatureGate::savedViewsAvailable($doctrineOnly));

        $securityOnly = ['SecurityBundle' => '_'];
        self::assertFalse(FeatureGate::savedViewsAvailable($securityOnly));

        self::assertFalse(FeatureGate::savedViewsAvailable([]));
    }
}
