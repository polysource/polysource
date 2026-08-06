<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\DependencyInjection\Loader\BulkActionHistoryLoader;
use Polysource\Filter\DependencyInjection\Loader\ColumnPreferenceLoader;
use Polysource\Filter\DependencyInjection\Loader\FilterTagsLoader;
use Polysource\Filter\DependencyInjection\Loader\FilterUrlTokenLoader;
use Polysource\Filter\DependencyInjection\Loader\PipelineLoader;
use Polysource\Filter\DependencyInjection\Loader\RecentRecordsLoader;
use Polysource\Filter\DependencyInjection\Loader\SavedViewLoader;
use stdClass;

/**
 * Locks each FeatureLoader's supports() gate (ADR-0032): the gate
 * lives ENTIRELY in supports(), so this matrix is the single place
 * that documents which bundle combination activates which feature.
 * (Doctrine's EntityManagerInterface is a require-dev of this repo,
 * so the interface_exists() legs are always true here — the matrix
 * exercises the bundle legs.)
 */
final class FeatureLoaderGateTest extends TestCase
{
    private const NONE = [];
    private const TWIG = ['TwigBundle' => stdClass::class];
    private const DOCTRINE = ['DoctrineBundle' => stdClass::class];
    private const DOCTRINE_SECURITY = ['DoctrineBundle' => stdClass::class, 'SecurityBundle' => stdClass::class];

    #[Test]
    public function pipelineIsUnconditional(): void
    {
        self::assertTrue((new PipelineLoader())->supports(self::NONE));
        self::assertTrue((new PipelineLoader())->supports('not-even-an-array'));
    }

    #[Test]
    public function filterTagsNeedsTwig(): void
    {
        self::assertFalse((new FilterTagsLoader())->supports(self::NONE));
        self::assertTrue((new FilterTagsLoader())->supports(self::TWIG));
    }

    #[Test]
    public function savedViewsActivateOnTwigOrFullStorageStack(): void
    {
        $loader = new SavedViewLoader();

        // Twig alone → the nullable-service Twig extension must
        // register (v0.1.4 bridge-alone parse fix).
        self::assertTrue($loader->supports(self::TWIG));
        // Full storage stack without Twig → services must register.
        self::assertTrue($loader->supports(self::DOCTRINE_SECURITY));
        // Doctrine alone (no Security, no Twig) → nothing to wire.
        self::assertFalse($loader->supports(self::DOCTRINE));
        self::assertFalse($loader->supports(self::NONE));
    }

    #[Test]
    public function columnPreferencesActivateOnTwigOrFullStorageStack(): void
    {
        $loader = new ColumnPreferenceLoader();

        self::assertTrue($loader->supports(self::TWIG));
        self::assertTrue($loader->supports(self::DOCTRINE_SECURITY));
        self::assertFalse($loader->supports(self::DOCTRINE));
        self::assertFalse($loader->supports(self::NONE));
    }

    #[Test]
    public function doctrineSecurityPairGatesHistoryAndRecents(): void
    {
        foreach ([new BulkActionHistoryLoader(), new RecentRecordsLoader()] as $loader) {
            self::assertTrue($loader->supports(self::DOCTRINE_SECURITY), $loader::class);
            self::assertFalse($loader->supports(self::DOCTRINE), $loader::class);
            self::assertFalse($loader->supports(self::TWIG), $loader::class);
        }
    }

    #[Test]
    public function urlTokensNeedDoctrineOnly(): void
    {
        $loader = new FilterUrlTokenLoader();

        // No Security dependency: tokens are user-agnostic by design.
        self::assertTrue($loader->supports(self::DOCTRINE));
        self::assertFalse($loader->supports(self::TWIG));
        self::assertFalse($loader->supports(self::NONE));
    }
}
