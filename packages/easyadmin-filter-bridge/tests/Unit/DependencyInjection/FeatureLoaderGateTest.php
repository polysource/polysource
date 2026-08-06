<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ChipLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ColumnPreferenceLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\EnhancerLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ExportLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\FilterTreeLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\FilterUrlTokenLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ListingUxLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\SavedViewControllerLoader;
use stdClass;

/**
 * Locks each bridge FeatureLoader's supports() gate (ADR-0032).
 * The extension-level C1 EasyAdmin guard runs BEFORE any loader, so
 * "unconditional" here means "whenever the bridge wires at all".
 * (polysource/filter and Doctrine's interfaces are require-dev of
 * this repo, so the class_exists() legs are always true here — the
 * matrix exercises the bundle legs.)
 */
final class FeatureLoaderGateTest extends TestCase
{
    private const NONE = [];
    private const DOCTRINE = ['DoctrineBundle' => stdClass::class];
    private const DOCTRINE_SECURITY = ['DoctrineBundle' => stdClass::class, 'SecurityBundle' => stdClass::class];

    #[Test]
    public function coreLoadersAreUnconditional(): void
    {
        foreach ([
            new EnhancerLoader(),
            new ChipLoader(),
            new ListingUxLoader(),
            new FilterTreeLoader(),
            new ExportLoader(),
        ] as $loader) {
            self::assertTrue($loader->supports(self::NONE), $loader::class);
        }
    }

    #[Test]
    public function savedViewControllerGatesOnTheFilterPackageClassOnly(): void
    {
        // SavedViewService ships in polysource/filter (require) —
        // the Security branch is wiring, not gating.
        self::assertTrue((new SavedViewControllerLoader())->supports(self::NONE));
    }

    #[Test]
    public function urlTokenEndpointsNeedDoctrine(): void
    {
        $loader = new FilterUrlTokenLoader();

        self::assertTrue($loader->supports(self::DOCTRINE));
        self::assertFalse($loader->supports(self::NONE));
    }

    #[Test]
    public function columnPreferenceEndpointsNeedDoctrineAndSecurity(): void
    {
        $loader = new ColumnPreferenceLoader();

        self::assertTrue($loader->supports(self::DOCTRINE_SECURITY));
        self::assertFalse($loader->supports(self::DOCTRINE));
        self::assertFalse($loader->supports(self::NONE));
    }
}
