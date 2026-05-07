<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Tests\Functional\App\ActionDisplayedTestKernel;
use Polysource\Bundle\Tests\Functional\App\ConditionallyShownAction;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Asserts that `ActionInterface::isDisplayed()` is honoured by the
 * controller pipeline — when an action returns false, the index
 * view does NOT render its button. Otherwise hosts can't hide
 * actions conditionally (e.g. "Cancel" on a job that's already
 * terminal).
 *
 * Cf. coverage plan Sprint 0 (latent bug found in 2026-05-07 audit).
 */
final class ActionIsDisplayedTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return ActionDisplayedTestKernel::class;
    }

    /** @param array<mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        ConditionallyShownAction::$shown = true;
        parent::tearDown();
        restore_exception_handler();
    }

    #[Test]
    public function actionWithIsDisplayedTrueRendersInIndex(): void
    {
        ConditionallyShownAction::$shown = true;
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create('/admin/gated-actions', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('always-shown-action', $body, 'Action that always returns isDisplayed=true must render');
        self::assertStringContainsString('conditional-action', $body, 'Action with isDisplayed=true must render');
    }

    #[Test]
    public function actionWithIsDisplayedFalseHiddenFromIndex(): void
    {
        ConditionallyShownAction::$shown = false;
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create('/admin/gated-actions', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('always-shown-action', $body, 'Always-shown action must remain visible');
        self::assertStringNotContainsString('conditional-action', $body, 'Action with isDisplayed=false must be hidden');
    }
}
