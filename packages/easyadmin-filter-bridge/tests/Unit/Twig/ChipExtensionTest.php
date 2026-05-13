<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ChipExtension;
use Symfony\Component\Translation\Translator;
use Twig\TwigFunction;

/**
 * Behavioural tests for {@see ChipExtension}.
 *
 * Since v0.1.4 this extension only exposes `polysource_chip_value`.
 * The v0.1.2 stub for `saved_views_dropdown` + the
 * `polysource_saved_views_available()` gate were removed when
 * ownership of `saved_views_dropdown` moved to
 * `polysource/filter::SavedViewExtension` (a transitive dep of the
 * bridge — so the function is always reachable).
 */
final class ChipExtensionTest extends TestCase
{
    public function testGetFunctionsExposesOnlyPolysourceChipValue(): void
    {
        $extension = new ChipExtension($this->makeFormatter());

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains(
            'polysource_chip_value',
            $names,
            'ChipExtension must still expose polysource_chip_value — '
            . 'this is its sole responsibility since v0.1.4.'
        );
        self::assertCount(
            1,
            $names,
            'ChipExtension must expose exactly one Twig function. '
            . 'If you are adding a second helper, reconsider whether '
            . 'it belongs here or in a domain-specific extension.'
        );
    }

    public function testGetFunctionsDoesNotRegisterSavedViewsHelpers(): void
    {
        // Regression guard: the v0.1.2 stub for `saved_views_dropdown`
        // and the `polysource_saved_views_available()` gate were
        // removed in v0.1.4. They should NOT come back here — the
        // function is owned by `polysource/filter::SavedViewExtension`,
        // a transitive dep of the bridge. If either name reappears
        // in this extension, ownership has been re-broken.
        $extension = new ChipExtension($this->makeFormatter());

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertNotContains('saved_views_dropdown', $names);
        self::assertNotContains('polysource_saved_views_available', $names);
    }

    private function makeFormatter(): ChipValueFormatter
    {
        return new ChipValueFormatter(
            self::createStub(AdminContextProviderInterface::class),
            self::createMock(EntityManagerInterface::class),
            new Translator('en'),
        );
    }
}
