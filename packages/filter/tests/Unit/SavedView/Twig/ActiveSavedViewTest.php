<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\SavedView\Twig\SavedViewExtension;
use Twig\TwigFunction;

#[CoversClass(SavedViewExtension::class)]
final class ActiveSavedViewTest extends TestCase
{
    #[Test]
    public function exposesActiveSavedViewFunction(): void
    {
        $extension = new SavedViewExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_active_saved_view', $names);
    }

    #[Test]
    public function returnsNullWhenServiceIsNotWired(): void
    {
        // Graceful degradation: when the service is null (host without
        // Doctrine + Security bundles), the helper returns null so
        // templates that depend on the active view fall through to
        // the host's defaults without crashing.
        $extension = new SavedViewExtension();

        self::assertNull($extension->activeSavedView('orders'));
    }
}
