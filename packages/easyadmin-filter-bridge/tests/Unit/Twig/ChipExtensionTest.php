<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ChipExtension;
use ReflectionMethod;
use Symfony\Component\Translation\Translator;
use Twig\TwigFunction;

/**
 * Behavioural tests for {@see ChipExtension}.
 *
 * The extension exposes two Twig helpers used by
 * `Resources/views/crud/index.html.twig`:
 *
 * - `polysource_chip_value(property, rawValue)` — delegates to
 *   {@see ChipValueFormatter} (covered by its own test).
 * - `polysource_saved_views_available()` — boolean probe used
 *   by the template to gate the call to `saved_views_dropdown()`.
 *
 * **Regression coverage for the v0.1.1 install blocker**:
 *
 * Under a vanilla `composer require polysource/easyadmin-filter-bridge`
 * install, `saved_views_dropdown()` is NOT registered as a Twig
 * function (it ships from `polysource/symfony-bundle`, which is
 * not a dependency of the bridge). The previous gate (presence
 * of `SavedViewExtension` class + `EntityManagerInterface`)
 * returned true regardless — because the bridge pulls
 * `polysource/filter` transitively (SavedViewExtension class
 * is loaded) and EasyAdmin itself requires Doctrine ORM. The
 * template then crashed at render time with
 * "Unknown saved_views_dropdown function".
 *
 * The fix gates strictly on `Polysource\Bundle\PolysourceBundle`
 * presence — the only honest signal that the function is
 * registered. These tests pin the new contract.
 *
 * Note on test environment: the polysource monorepo loads ALL
 * sibling packages via path repositories, so
 * `Polysource\Bundle\PolysourceBundle::class` IS class_exists()
 * here. The behavioural assertion ("must return false on bare
 * install") therefore can't run inside the monorepo test suite —
 * it belongs in `scripts/smoke-packagist.sh`, which installs the
 * bridge alone on a vanilla Symfony skeleton. The tests here
 * pin the **structural** contract (which class the gate checks)
 * via reflection — that catches a logic-level regression even
 * when all classes are loadable.
 */
final class ChipExtensionTest extends TestCase
{
    public function testGateIsBoundToPolysourceBundleClassExistence(): void
    {
        $extension = new ChipExtension($this->makeFormatter());

        self::assertSame(
            class_exists(\Polysource\Bundle\PolysourceBundle::class),
            $extension->savedViewsAvailable(),
            'savedViewsAvailable() must mirror PolysourceBundle availability '
            . 'one-to-one — no other signals.'
        );
    }

    public function testGateUsesPolysourceBundleAsSoleSignal(): void
    {
        // Structural / source-level assertion. The monorepo test
        // env can't distinguish the new gate from the old one at
        // behaviour level (every class is loadable here), so we
        // pin the IMPLEMENTATION instead: the method body must
        // reference PolysourceBundle and must NOT reference the
        // old signals (SavedViewExtension class or
        // EntityManagerInterface interface).
        //
        // Without this guard, a maintainer reverting the v0.1.2
        // fix would silently re-introduce the install-blocking bug
        // because no behavioural test inside this package would
        // catch it.
        $body = $this->readMethodBody(ChipExtension::class, 'savedViewsAvailable');

        self::assertStringContainsString(
            'PolysourceBundle',
            $body,
            'The gate must reference PolysourceBundle — that is the only ' .
            'class whose presence implies saved_views_dropdown() is registered.'
        );
        self::assertStringNotContainsString(
            'SavedViewExtension',
            $body,
            'The gate must NOT use SavedViewExtension presence as a signal ' .
            '(cf. v0.1.2 fix — that class loads transitively under any ' .
            'vanilla bridge install, but the Twig function it documents is ' .
            'registered by polysource/symfony-bundle, not by the package ' .
            'that defines it).'
        );
        self::assertStringNotContainsString(
            'EntityManagerInterface',
            $body,
            'The gate must NOT use EntityManagerInterface presence as a ' .
            'signal — Doctrine ORM is required transitively by EA itself, ' .
            'so this interface is always loadable and contributes nothing.'
        );
    }

    public function testGetFunctionsExposesBothHelpers(): void
    {
        $extension = new ChipExtension($this->makeFormatter());

        $names = $this->functionNames($extension);

        self::assertContains('polysource_chip_value', $names);
        self::assertContains('polysource_saved_views_available', $names);
    }

    public function testStubIsNotRegisteredWhenSymfonyBundleIsPresent(): void
    {
        // In the monorepo test env, polysource/symfony-bundle IS
        // autoloadable, so the stub branch is skipped and the real
        // function (provided by symfony-bundle's
        // PolysourceFilterExtension) wins. Asserting the stub absence
        // here pins the "no duplicate function registration" invariant
        // — registering the stub unconditionally would throw at Twig
        // boot when both bundles wire.
        self::assertTrue(
            class_exists(\Polysource\Bundle\PolysourceBundle::class),
            'Premise broken: monorepo test env should have symfony-bundle autoloadable.'
        );

        $extension = new ChipExtension($this->makeFormatter());

        self::assertNotContains(
            'saved_views_dropdown',
            $this->functionNames($extension),
            'When symfony-bundle is present, the bridge MUST NOT register the stub — ' .
            'the real function from PolysourceFilterExtension owns the name.'
        );
    }

    public function testGetFunctionsSourceConditionallyRegistersStubOnBundleAbsence(): void
    {
        // Source-level assertion: the monorepo test env can't run the
        // "bundle absent" branch behaviourally (every class is loadable
        // here), so we pin the IMPLEMENTATION via reflection. Without
        // this guard, a maintainer removing the conditional stub would
        // silently re-introduce the v0.1.1 install blocker (Twig
        // compile error on every EA index page with filters).
        $body = $this->readMethodBody(ChipExtension::class, 'getFunctions');

        self::assertStringContainsString(
            "'saved_views_dropdown'",
            $body,
            'getFunctions() must reference saved_views_dropdown so a stub ' .
            'can be registered when symfony-bundle is absent — otherwise ' .
            'crud/index.html.twig fails to parse on the bridge-alone install.'
        );
        self::assertStringContainsString(
            'PolysourceBundle',
            $body,
            'The stub registration must be gated on PolysourceBundle absence — ' .
            'registering unconditionally would clash with symfony-bundle\'s real ' .
            'function declaration when both packages are installed.'
        );
        self::assertMatchesRegularExpression(
            '/!\s*class_exists\(\\\\?Polysource.+PolysourceBundle/',
            $body,
            'The stub must be registered ONLY when PolysourceBundle is absent ' .
            '(negative class_exists check).'
        );
    }

    /**
     * @return list<string>
     */
    private function functionNames(ChipExtension $extension): array
    {
        return array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );
    }

    private function makeFormatter(): ChipValueFormatter
    {
        return new ChipValueFormatter(
            self::createStub(AdminContextProviderInterface::class),
            self::createMock(EntityManagerInterface::class),
            new Translator('en'),
        );
    }

    private function readMethodBody(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if (false === $file || false === $start || false === $end) {
            self::fail("Could not locate source of {$class}::{$method}().");
        }

        $lines = file($file);
        if (false === $lines) {
            self::fail("Could not read source file {$file}.");
        }

        return implode('', \array_slice($lines, $start - 1, $end - $start + 1));
    }
}
