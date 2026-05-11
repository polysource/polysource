<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing the bridge's small Twig helpers — chip
 * value formatting + saved-views availability probe.
 *
 * Lives as a Twig extension (rather than services injected directly
 * into the template) because chip rendering happens inside an
 * EA-managed Twig include — the Twig namespace is the only seam
 * where we can expose a method without changing the template
 * authoring contract.
 */
final class ChipExtension extends AbstractExtension
{
    public function __construct(private readonly ChipValueFormatter $formatter)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        $functions = [
            new TwigFunction('polysource_chip_value', $this->formatter->format(...)),
            new TwigFunction('polysource_saved_views_available', $this->savedViewsAvailable(...)),
        ];

        // Twig resolves function names at PARSE time (not at runtime),
        // independently of the `{% if %}` guard inside the template.
        // So even though `crud/index.html.twig` gates
        // `saved_views_dropdown(...)` behind `polysource_saved_views_available()`,
        // Twig still refuses to compile the template if no extension
        // declares the function — `{% if false %}{{ unknown() }}{% endif %}`
        // still throws `Twig\Error\SyntaxError: Unknown "unknown" function`.
        //
        // `saved_views_dropdown` is owned by `polysource/symfony-bundle`.
        // When that bundle is installed, its `PolysourceFilterExtension`
        // registers the real function (rendering the dropdown via
        // `SavedViewExtension::renderDropdown`).
        //
        // When that bundle is NOT installed (the bridge-alone install
        // path), we register a silent stub here so the template
        // compiles. The runtime gate `polysource_saved_views_available()`
        // still evaluates to false in that case, so the stub is never
        // actually called — but Twig's compile-time check is satisfied.
        //
        // We only register the stub when symfony-bundle is absent: if
        // we registered it unconditionally, Twig would throw on
        // duplicate function registration once symfony-bundle's real
        // function is also declared.
        if (!class_exists(\Polysource\Bundle\PolysourceBundle::class)) {
            $functions[] = new TwigFunction(
                'saved_views_dropdown',
                static fn (): string => '',
            );
        }

        return $functions;
    }

    /**
     * Returns true when the `saved_views_dropdown()` Twig function
     * is registered in the current host.
     *
     * The function is owned exclusively by `polysource/symfony-bundle`'s
     * `Polysource\Bundle\Twig\PolysourceFilterExtension` (cf. its
     * `getFunctions()`; the `polysource/filter` package's
     * `SavedViewExtension` deliberately does NOT register it to
     * avoid name collisions at Twig boot when both bundles wire).
     *
     * So the only honest signal that `saved_views_dropdown()` will
     * resolve at render time is: is `polysource/symfony-bundle`
     * installed AND its bundle class loadable?
     *
     * Used by `crud/index.html.twig` to skip the dropdown render
     * in hosts that have the bridge alone — calling
     * `saved_views_dropdown()` unconditionally would crash with
     * "Twig function not found".
     *
     * The previous gate (class_exists(SavedViewExtension) &&
     * interface_exists(EntityManagerInterface)) was wrong: both
     * conditions are true under a vanilla `composer require
     * polysource/easyadmin-filter-bridge` install — the bridge
     * pulls `polysource/filter` transitively (SavedViewExtension
     * loadable) and EA itself requires Doctrine ORM — yet the
     * Twig function is not registered, so the template crashed
     * on the documented happy-path install.
     */
    public function savedViewsAvailable(): bool
    {
        return class_exists(\Polysource\Bundle\PolysourceBundle::class);
    }
}
