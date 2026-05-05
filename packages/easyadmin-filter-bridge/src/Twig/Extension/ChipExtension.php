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
        return [
            new TwigFunction('polysource_chip_value', $this->formatter->format(...)),
            new TwigFunction('polysource_saved_views_available', $this->savedViewsAvailable(...)),
        ];
    }

    /**
     * Returns true when the saved-views feature is wired in the
     * current host (polysource/filter SavedView classes shipped AND
     * the DI extension registered them, gated on Doctrine ORM
     * availability per ADR-019 §4).
     *
     * Used by `crud/index.html.twig` to skip the dropdown render in
     * hosts that lack Doctrine — calling `saved_views_dropdown()`
     * unconditionally would crash with "Twig function not found".
     */
    public function savedViewsAvailable(): bool
    {
        return class_exists(\Polysource\Filter\SavedView\Twig\SavedViewExtension::class)
            && interface_exists(\Doctrine\ORM\EntityManagerInterface::class);
    }
}
